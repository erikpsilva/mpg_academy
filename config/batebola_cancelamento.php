<?php
require_once __DIR__.'/batebola_checkout.php';
require_once __DIR__.'/mercadopago.php';

function bbCancelarIndividual(PDO $pdo,int $jogador,string $chave,?callable $consulta=null): void {
    if(!preg_match('/^(especial:[1-9][0-9]*|domingo:[0-9]{4}-[0-9]{2}-[0-9]{2})$/D',$chave))throw new RuntimeException('Evento inválido.');
    // Transporte simulado disponível somente para testes CLI locais.
    if($consulta && (!APP_IS_LOCAL || PHP_SAPI!=='cli'))throw new LogicException('Simulação não permitida.');
    $especial=strpos($chave,'especial:')===0;
    $t=$especial?'batebola_especial_inscricoes':'batebola_inscricoes';
    $col=$especial?'evento_id':'data_evento';$key=substr($chave,strpos($chave,':')+1);
    if((int)$pdo->query("SELECT GET_LOCK('mpg_bb_reservas',10)")->fetchColumn()!==1)throw new RuntimeException('Tente novamente em instantes.');
    try {
        $pdo->beginTransaction();
        $st=$pdo->prepare("SELECT * FROM $t WHERE jogador_id=? AND $col=? FOR UPDATE");$st->execute([$jogador,$key]);$ins=$st->fetch(PDO::FETCH_ASSOC);
        if(!$ins)throw new RuntimeException('Inscrição não encontrada.');
        if($ins['status']==='pago')throw new RuntimeException('Esta vaga já está paga e não pode ser cancelada por aqui.');
        if($ins['status']==='cancelado'){$pdo->commit();return;}
        if(empty($ins['mp_payment_id']))throw new RuntimeException('Não foi possível identificar o PIX. Procure a equipe para verificar a cobrança antes de liberar uma nova.');
        if(!$consulta){
            if(APP_IS_LOCAL)throw new RuntimeException('Cancelamento externo desabilitado no ambiente local.');
            $token=mpAccessToken($pdo);
            $consulta=fn($method,$id)=>mpRequest($token,$method,'/v1/payments/'.rawurlencode($id),$method==='PUT'?['status'=>'cancelled']:null);
        }
        $mpId=(string)$ins['mp_payment_id'];
        $validar=function(array $r)use($ins,$especial,$mpId):array{
            $p=$r['body']??[];
            if(($r['http_code']??0)<200 || ($r['http_code']??0)>=300 || (string)($p['id']??'')!==$mpId || ($p['external_reference']??'')!==($especial?'bb-especial-':'batebola-').$ins['id'] || ($p['currency_id']??'')!=='BRL' || (int)round(($p['transaction_amount']??-1)*100)!==(int)round($ins['valor']*100))throw new RuntimeException('Não foi possível confirmar a cobrança no Mercado Pago. Nenhuma nova cobrança foi liberada; tente novamente.');
            return $p;
        };
        $p=$validar($consulta('GET',$mpId));
        if(in_array($p['status']??'',['pending','in_process','authorized'],true))$p=$validar($consulta('PUT',$mpId));
        if(($p['status']??'')==='approved')throw new RuntimeException('Este PIX já foi pago. Sua vaga será mantida; volte à sua área para atualizar a confirmação.');
        if(!in_array($p['status']??'',['cancelled','rejected'],true))throw new RuntimeException('O cancelamento ainda não foi confirmado. Aguarde e tente novamente antes de gerar outro PIX.');
        $pdo->prepare("UPDATE $t SET status='cancelado',pix_qr_code=NULL,pix_qr_code_base64=NULL WHERE id=? AND jogador_id=? AND status='pendente'")->execute([$ins['id'],$jogador]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    finally{$pdo->query("SELECT RELEASE_LOCK('mpg_bb_reservas')");}
}
