<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/config/database.php';require_once dirname(__DIR__).'/config/batebola_checkout.php';
if(!APP_IS_LOCAL)throw new RuntimeException('Somente banco local.');
$p=getDbConnection();$players=[];$events=[];$token=bin2hex(random_bytes(6));
function cartCheck($ok,$msg){if(!$ok)throw new RuntimeException($msg);echo 'OK '.$msg.PHP_EOL;}
try {
    for($n=0;$n<3;$n++){$p->prepare('INSERT INTO jogadores_batebola(nome,email,celular,senha) VALUES(?,?,?,?)')->execute(['QA checkout '.$token,'qa-'.$token.'-'.$n.'@example.invalid','11900000000',password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);$players[]=(int)$p->lastInsertId();}
    foreach([15,17] as $price){$p->prepare("INSERT INTO batebola_especiais(titulo,inicio,fim,valor,vagas) VALUES(?,'2099-01-05 19:00:00','2099-01-05 21:00:00',?,2)")->execute(['QA checkout '.$token,$price]);$events[]=(int)$p->lastInsertId();}
    $opts=bbOpcoes($p,$players[0]);$sunday=array_values(array_filter($opts,fn($o)=>$o['tipo']==='domingo'))[0];
    $first=$sunday['disponivel']?$sunday['chave']:'especial:'.$events[1];
    $keys=[$first,'especial:'.$events[0]];$expected=15+($sunday['disponivel']?$sunday['valor']:17);
    $id=bbPreparar($p,$players[0],$keys);$pedido=bbPedido($p,$id,$players[0]);
    cartCheck((float)$pedido['valor']===$expected,'total calculado no servidor');
    cartCheck(bbPreparar($p,$players[0],array_reverse($keys))===$id,'repetição recupera o mesmo pedido');
    $p->prepare("UPDATE batebola_pedidos SET status='pendente' WHERE id=?")->execute([$id]);
    $payment=['id'=>'qa-cart-'.$token,'status'=>'approved','currency_id'=>'BRL','external_reference'=>'bb-pedido-'.$id,'metadata'=>['batebola_pedido_id'=>$id],'transaction_amount'=>$expected];
    $bad=$payment;$bad['transaction_amount']=1;cartCheck(!bbConfirmarPedido($p,$id,$bad),'valor adulterado não confirma');
    $bad=$payment;$bad['external_reference']='bb-pedido-0';cartCheck(!bbConfirmarPedido($p,$id,$bad),'referência errada não confirma');
    cartCheck(bbConfirmarPedido($p,$id,$payment),'PIX único confirma todos os itens');
    cartCheck(!bbConfirmarPedido($p,$id,$payment),'reenvio de webhook não duplica');
    foreach(bbItens($p,$id) as $item){$t=$item['tipo']==='domingo'?'batebola_inscricoes':'batebola_especial_inscricoes';$st=$p->prepare("SELECT status,valor FROM $t WHERE id=? AND jogador_id=?");$st->execute([$item['inscricao_id'],$players[0]]);$i=$st->fetch();cartCheck($i['status']==='pago' && $i['valor']===$item['valor'],'lista '.$item['tipo'].' recebe seu próprio valor');}
    cartCheck(bbPedido($p,$id,$players[1])===null,'pedido pertence apenas ao jogador correto');
    $invalid=false;try{bbPreparar($p,$players[1],['especial:'.$events[1],'especial:99999999']);}catch(RuntimeException $e){$invalid=true;}
    cartCheck($invalid && bbReservas($p,'especial:'.$events[1])===0,'seleção inválida não reserva parcialmente');
    $second=bbPreparar($p,$players[1],['especial:'.$events[0]]);
    $full=false;try{bbPreparar($p,$players[2],['especial:'.$events[0]]);}catch(RuntimeException $e){$full=true;}
    cartCheck($full,'última vaga reservada bloqueia outro pedido');
    $p->prepare("UPDATE batebola_pedidos SET status='cancelado' WHERE id=? AND status='reservado'")->execute([$second]);
    $third=bbPreparar($p,$players[2],['especial:'.$events[0]]);
    cartCheck($third>0,'cancelamento antes do PIX libera a vaga');
    $p->prepare("UPDATE batebola_pedidos SET status='cancelado' WHERE id=?")->execute([$third]);
    $pair=bbPreparar($p,$players[2],['especial:'.$events[0],'especial:'.$events[1]]);
    $p->prepare("UPDATE batebola_pedidos SET status='pendente' WHERE id=?")->execute([$pair]);
    $p->prepare("INSERT INTO batebola_especial_inscricoes(evento_id,jogador_id,valor,status,chave_pagamento) VALUES(?,?,17,'pago',?)")->execute([$events[1],$players[2],'qa-conflict-'.$token]);$conflict=(int)$p->lastInsertId();
    $payment=['id'=>'qa-pair-'.$token,'status'=>'approved','currency_id'=>'BRL','external_reference'=>'bb-pedido-'.$pair,'metadata'=>['batebola_pedido_id'=>$pair],'transaction_amount'=>32];
    $rolled=false;try{bbConfirmarPedido($p,$pair,$payment);}catch(RuntimeException $e){$rolled=true;}
    $st=$p->prepare('SELECT COUNT(*) FROM batebola_especial_inscricoes WHERE evento_id=? AND jogador_id=?');$st->execute([$events[0],$players[2]]);
    cartCheck($rolled && (int)$st->fetchColumn()===0 && bbPedido($p,$pair,$players[2])['status']==='pendente','conflito no segundo item reverte toda a confirmação');
    $p->prepare('DELETE FROM batebola_especial_inscricoes WHERE id=?')->execute([$conflict]);
    cartCheck(bbConfirmarPedido($p,$pair,$payment),'um PIX também confirma dois especiais');
} finally {
    if($p->inTransaction())$p->rollBack();
    foreach($players as $j){$p->prepare('DELETE i FROM batebola_pedido_itens i JOIN batebola_pedidos p ON p.id=i.pedido_id WHERE p.jogador_id=?')->execute([$j]);$p->prepare('DELETE FROM batebola_pedidos WHERE jogador_id=?')->execute([$j]);$p->prepare('DELETE FROM batebola_inscricoes WHERE jogador_id=?')->execute([$j]);$p->prepare('DELETE FROM batebola_especial_inscricoes WHERE jogador_id=?')->execute([$j]);$p->prepare('DELETE FROM jogadores_batebola WHERE id=?')->execute([$j]);}
    foreach($events as $e)$p->prepare('DELETE FROM batebola_especiais WHERE id=?')->execute([$e]);
}
echo 'Fixtures locais removidas; nenhum pagamento externo foi criado.'.PHP_EOL;
