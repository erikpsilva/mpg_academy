<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/batebola_especial.php';
require_once dirname(__DIR__,2).'/config/batebola_checkout.php';
require_once dirname(__DIR__,2).'/config/mercadopago.php';
try {
    if(empty($_SESSION['jogador']['id'])) { http_response_code(401); throw new RuntimeException('Entre na sua conta do bate-bola.'); }
    if($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals($_SESSION['especial_csrf']??'', (string)($_POST['csrf']??'')) || empty($_POST['csrf'])) { http_response_code(403); throw new RuntimeException('Atualize a página e tente novamente.'); }
    $pdo=getDbConnection(); $id=(int)($_POST['evento_id']??0); $jogador=(int)$_SESSION['jogador']['id'];
    $st=$pdo->prepare('SELECT email FROM jogadores_batebola WHERE id=?'); $st->execute([$jogador]); $email=$st->fetchColumn();
    if(!$email) throw new RuntimeException('Cadastro não encontrado. Entre novamente.');
    $pdo->beginTransaction();
    $evento=especialBuscar($pdo,$id,true);
    if(!$evento) throw new RuntimeException('Evento não encontrado.');
    $st=$pdo->prepare('SELECT * FROM batebola_especial_inscricoes WHERE evento_id=? AND jogador_id=? FOR UPDATE'); $st->execute([$id,$jogador]); $ins=$st->fetch();
    $consultar=($_POST['acao']??'')==='status';
    if(!$consultar && bbCheckoutDisponivel($pdo) && (!$ins || $ins['status']==='cancelado')) throw new RuntimeException('Use a nova tela de seleção de bate-bolas para gerar o pagamento.');
    if(!$ins && $consultar) { $pdo->commit(); echo json_encode(['success'=>true,'status'=>'nenhuma']); exit; }
    if(!$ins || (!$consultar && $ins['status']==='cancelado')) {
        if(!especialAberto($evento)) throw new RuntimeException('As inscrições deste evento estão encerradas.');
        $st=$pdo->prepare("SELECT COUNT(*) FROM batebola_especial_inscricoes WHERE evento_id=? AND status IN ('pago','pendente')"); $st->execute([$id]);
        if((int)$st->fetchColumn()>=(int)$evento['vagas']) throw new RuntimeException('Todas as vagas estão confirmadas ou reservadas para pagamento.');
        $key='bb-especial-'.bin2hex(random_bytes(16));
        if($ins) {
            $pdo->prepare("UPDATE batebola_especial_inscricoes SET status='pendente',chave_pagamento=?,mp_payment_id=NULL,pix_qr_code=NULL,pix_qr_code_base64=NULL WHERE id=? AND status='cancelado'")->execute([$key,$ins['id']]);
            $ins['status']='pendente';$ins['chave_pagamento']=$key;$ins['mp_payment_id']=null;$ins['pix_qr_code']=null;$ins['pix_qr_code_base64']=null;
        } else {
            $pdo->prepare('INSERT INTO batebola_especial_inscricoes(evento_id,jogador_id,valor,chave_pagamento) VALUES(?,?,?,?)')->execute([$id,$jogador,$evento['valor'],$key]);
            $st=$pdo->prepare('SELECT * FROM batebola_especial_inscricoes WHERE id=?'); $st->execute([$pdo->lastInsertId()]); $ins=$st->fetch();
        }
    }
    $pdo->commit();
    if($ins['status']==='pago') { echo json_encode(['success'=>true,'status'=>'pago']); exit; }
    if($ins['status']==='cancelado') throw new RuntimeException('Esta inscrição está cancelada. Procure a equipe.');
    if($ins['mp_payment_id'] && !APP_IS_LOCAL) {
        $payment=mpConsultarPagamento(mpAccessToken($pdo),$ins['mp_payment_id']);
        if($payment && especialConfirmar($pdo,(int)$ins['id'],$payment)) { echo json_encode(['success'=>true,'status'=>'pago']); exit; }
        if(in_array($payment['status']??'', ['cancelled','rejected','refunded','charged_back'],true)) {
            $pdo->prepare("UPDATE batebola_especial_inscricoes SET status='cancelado' WHERE id=? AND status='pendente'")->execute([$ins['id']]);
            throw new RuntimeException('O pagamento foi cancelado ou recusado. Procure a equipe para uma nova inscrição.');
        }
    }
    if($consultar) { echo json_encode(['success'=>true,'status'=>'pendente']); exit; }
    if(!especialAberto($evento)) throw new RuntimeException('As inscrições deste evento estão encerradas.');
    if(!$ins['pix_qr_code']) {
        // Em desenvolvimento, nunca gera cobrança real, mesmo se houver credenciais locais.
        if(APP_IS_LOCAL) throw new RuntimeException('Pagamento externo desabilitado no ambiente local.');
        $result=mpCriarPagamento(mpAccessToken($pdo),[
            'transaction_amount'=>(float)$ins['valor'],'payment_method_id'=>'pix',
            'description'=>'MPG Academy — '.$evento['titulo'], 'payer'=>['email'=>$email],
            'metadata'=>['batebola_especial_inscricao_id'=>(int)$ins['id']],
            'external_reference'=>'bb-especial-'.$ins['id'],
        ],$ins['chave_pagamento']);
        $body=$result['body'];
        if(empty($body['id'])) throw new RuntimeException('Não foi possível confirmar a geração do PIX. Tente novamente; a mesma cobrança será recuperada.');
        $tx=$body['point_of_interaction']['transaction_data']??[];
        $ins['pix_qr_code']=$tx['qr_code']??''; $ins['pix_qr_code_base64']=$tx['qr_code_base64']??'';
        $pdo->prepare('UPDATE batebola_especial_inscricoes SET mp_payment_id=?,pix_qr_code=?,pix_qr_code_base64=? WHERE id=?')->execute([(string)$body['id'],$ins['pix_qr_code'],$ins['pix_qr_code_base64'],$ins['id']]);
        if(especialConfirmar($pdo,(int)$ins['id'],$body)) { echo json_encode(['success'=>true,'status'=>'pago']); exit; }
        if(!$ins['pix_qr_code']) throw new RuntimeException('Pagamento em processamento. Consulte o status em instantes.');
    }
    echo json_encode(['success'=>true,'status'=>'pendente','qr_code'=>$ins['pix_qr_code'],'qr_code_base64'=>$ins['pix_qr_code_base64']]);
} catch(Throwable $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if(http_response_code()<400) http_response_code(400);
    error_log('[bb-especial] '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'Não foi possível concluir. Tente novamente.']);
}
