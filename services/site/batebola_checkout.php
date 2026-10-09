<?php
session_start();header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/batebola_checkout.php';
require_once dirname(__DIR__,2).'/config/mercadopago.php';
try {
    if(empty($_SESSION['jogador']['id'])){http_response_code(401);throw new RuntimeException('Entre na sua conta para continuar.');}
    if($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST['csrf']) || !hash_equals($_SESSION['bb_checkout_csrf']??'',(string)$_POST['csrf'])){http_response_code(403);throw new RuntimeException('Atualize a página e tente novamente.');}
    $pdo=getDbConnection();if(!bbCheckoutDisponivel($pdo))throw new RuntimeException('A seleção de eventos está sendo atualizada. Tente novamente em instantes.');
    $j=(int)$_SESSION['jogador']['id'];$acao=$_POST['acao']??'';
    if($acao==='cancelar_individual'){
        require_once dirname(__DIR__,2).'/config/batebola_cancelamento.php';
        bbCancelarIndividual($pdo,$j,(string)($_POST['evento']??''));
        echo json_encode(['success'=>true,'status'=>'cancelado']);exit;
    }
    if($acao==='preparar'){
        $selecionados=$_POST['eventos']??[];if(!is_array($selecionados))throw new RuntimeException('Selecione os eventos.');
        $id=bbPreparar($pdo,$j,$selecionados);echo json_encode(['success'=>true,'redirect'=>BASE_URL.'/batebolaselecao?pedido='.$id]);exit;
    }
    $id=(int)($_POST['pedido']??0);$pedido=bbPedido($pdo,$id,$j);if(!$pedido){http_response_code(404);throw new RuntimeException('Pagamento não encontrado.');}
    if($acao==='cancelar'){
        $st=$pdo->prepare("UPDATE batebola_pedidos SET status='cancelado' WHERE id=? AND jogador_id=? AND status='reservado'");$st->execute([$id,$j]);
        if(!$st->rowCount())throw new RuntimeException('O PIX já foi solicitado. Verifique o pagamento antes de fazer outra seleção.');
        echo json_encode(['success'=>true,'redirect'=>BASE_URL.'/batebolaselecao']);exit;
    }
    if(!in_array($acao,['pagar','status'],true))throw new RuntimeException('Ação inválida.');
    if($pedido['status']==='pago'){echo json_encode(['success'=>true,'status'=>'pago']);exit;}
    if($pedido['status']==='cancelado')throw new RuntimeException('Esta seleção foi cancelada. Volte e escolha os eventos novamente.');
    if($pedido['mp_payment_id'] && !APP_IS_LOCAL){
        $payment=mpConsultarPagamento(mpAccessToken($pdo),$pedido['mp_payment_id']);
        if($payment && bbConfirmarPedido($pdo,$id,$payment)){echo json_encode(['success'=>true,'status'=>'pago']);exit;}
        if(in_array($payment['status']??'',['cancelled','rejected'],true)){
            $pdo->prepare("UPDATE batebola_pedidos SET status='cancelado' WHERE id=? AND status='pendente'")->execute([$id]);
            throw new RuntimeException('O PIX expirou ou foi cancelado. Volte à seleção para gerar um novo pagamento.');
        }
    }
    if($acao==='status'){echo json_encode(['success'=>true,'status'=>bbPedido($pdo,$id,$j)['status']]);exit;}
    if(!$pedido['pix_qr_code']){
        if(APP_IS_LOCAL)throw new RuntimeException('Geração de cobrança externa desabilitada no ambiente local.');
        $pdo->beginTransaction();$st=$pdo->prepare('SELECT * FROM batebola_pedidos WHERE id=? FOR UPDATE');$st->execute([$id]);$pedido=$st->fetch();
        if($pedido['status']==='reservado'){
            $opcoes=array_column(bbOpcoes($pdo,$j),null,'chave');
            foreach(bbItens($pdo,$id) as $i)if(empty($opcoes[$i['evento_chave']]['aberto'])){
                $pdo->prepare("UPDATE batebola_pedidos SET status='cancelado' WHERE id=?")->execute([$id]);$pdo->commit();throw new RuntimeException('Um dos eventos encerrou as inscrições. Faça uma nova seleção.');
            }
            $pdo->prepare("UPDATE batebola_pedidos SET status='pendente' WHERE id=?")->execute([$id]);
        }elseif($pedido['status']!=='pendente'){throw new RuntimeException('A situação do pedido mudou. Atualize a página.');}
        $pdo->commit();
        $st=$pdo->prepare('SELECT email FROM jogadores_batebola WHERE id=?');$st->execute([$j]);$email=$st->fetchColumn();if(!$email)throw new RuntimeException('Cadastro não encontrado.');
        $result=mpCriarPagamento(mpAccessToken($pdo),[
            'transaction_amount'=>(float)$pedido['valor'],'payment_method_id'=>'pix',
            'description'=>'MPG Academy — inscrição em '.count(bbItens($pdo,$id)).' bate-bola(s)',
            'payer'=>['email'=>$email], 'metadata'=>['batebola_pedido_id'=>$id], 'external_reference'=>'bb-pedido-'.$id,
        ],$pedido['chave_pagamento']);
        $payment=$result['body'];if(empty($payment['id'])) {
            if(in_array((int)$result['http_code'],[400,401,403,404,422],true)) {
                $pdo->prepare("UPDATE batebola_pedidos SET status='cancelado' WHERE id=? AND status='pendente' AND mp_payment_id IS NULL")->execute([$id]);
                throw new RuntimeException('O provedor não aceitou a cobrança. Volte à seleção e tente novamente.');
            }
            throw new RuntimeException('Não foi possível confirmar a geração. Tente novamente para recuperar o mesmo PIX.');
        }
        $tx=$payment['point_of_interaction']['transaction_data']??[];
        $pdo->prepare("UPDATE batebola_pedidos SET mp_payment_id=?,pix_qr_code=?,pix_qr_code_base64=? WHERE id=? AND status IN ('pendente','pago') AND (mp_payment_id IS NULL OR mp_payment_id=?)")->execute([(string)$payment['id'],$tx['qr_code']??'',$tx['qr_code_base64']??'',$id,(string)$payment['id']]);
        bbConfirmarPedido($pdo,$id,$payment);$pedido=bbPedido($pdo,$id,$j);
        if(in_array($payment['status']??'',['cancelled','rejected'],true)) {
            $pdo->prepare("UPDATE batebola_pedidos SET status='cancelado' WHERE id=? AND status='pendente'")->execute([$id]);
            throw new RuntimeException('O pagamento foi recusado. Volte à seleção para tentar novamente.');
        }
        if($pedido['status']==='pago'){echo json_encode(['success'=>true,'status'=>'pago']);exit;}
        if(!$pedido['pix_qr_code'])throw new RuntimeException('Pagamento em processamento. Verifique o status em instantes.');
    }
    echo json_encode(['success'=>true,'status'=>'pendente','qr_code'=>$pedido['pix_qr_code'],'qr_code_base64'=>$pedido['pix_qr_code_base64']]);
}catch(Throwable $e){
    if(isset($pdo) && $pdo->inTransaction())$pdo->rollBack();
    if(http_response_code()<400)http_response_code(400);
    error_log('[bb-checkout] '.$e->getMessage());echo json_encode(['success'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'Não foi possível concluir. Tente novamente.']);
}
