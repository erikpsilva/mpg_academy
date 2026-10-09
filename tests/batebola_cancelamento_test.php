<?php
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/config/batebola_cancelamento.php';
if(!APP_IS_LOCAL)throw new RuntimeException('Somente local.');
$pdo=getDbConnection();$id=0;
try{
    $j=(int)$pdo->query('SELECT id FROM jogadores_batebola ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO batebola_especiais(titulo,inicio,fim,valor,vagas) VALUES('QA cancelamento','2099-01-01 19:00:00','2099-01-01 21:00:00',15,18)");$id=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO batebola_especial_inscricoes(evento_id,jogador_id,valor,chave_pagamento,mp_payment_id) VALUES(?,?,15,'qa-cancel','qa-cancel')")->execute([$id,$j]);$ins=(int)$pdo->lastInsertId();
    $body=['id'=>'qa-cancel','external_reference'=>'bb-especial-'.$ins,'currency_id'=>'BRL','transaction_amount'=>15];
    $mock=fn($method,$mp)=>['http_code'=>200,'body'=>$body+['status'=>$method==='GET'?'pending':'cancelled']];
    foreach(['approved','unknown','mismatch'] as $case){
        $failed=false;
        try{bbCancelarIndividual($pdo,$j,'especial:'.$id,fn()=>['http_code'=>200,'body'=>array_merge($body,['status'=>$case==='mismatch'?'cancelled':$case,'id'=>$case==='mismatch'?'wrong':'qa-cancel'])]);}catch(RuntimeException $e){$failed=true;}
        if(!$failed)throw new RuntimeException('Proteção falhou: '.$case);echo "OK bloqueado $case\n";
    }
    bbCancelarIndividual($pdo,$j,'especial:'.$id,$mock);
    $st=$pdo->prepare('SELECT status FROM batebola_especial_inscricoes WHERE id=?');$st->execute([$ins]);if($st->fetchColumn()!=='cancelado')throw new RuntimeException('Não cancelou');
    echo "OK cancelamento confirmado libera inscrição\n";
    bbCancelarIndividual($pdo,$j,'especial:'.$id,$mock);echo "OK repetição segura\n";
}finally{if($id){$pdo->prepare('DELETE FROM batebola_especial_inscricoes WHERE evento_id=?')->execute([$id]);$pdo->prepare('DELETE FROM batebola_especiais WHERE id=?')->execute([$id]);}}
