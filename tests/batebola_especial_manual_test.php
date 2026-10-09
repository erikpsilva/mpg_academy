<?php
if(PHP_SAPI!=='cli')exit;
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/config/batebola_especial_manual.php';
if(!APP_IS_LOCAL)throw new RuntimeException('Somente local.');
$pdo=getDbConnection();
function manualCheck($condition,$message){if(!$condition)throw new RuntimeException($message);echo "OK $message\n";}
function manualReject(callable $fn,$message){try{$fn();}catch(RuntimeException $e){echo "OK $message\n";return;}throw new RuntimeException($message);}
$pdo->query("SELECT GET_LOCK('mpg_bb_reservas',10)");
$pdo->beginTransaction();
try{
    $players=$pdo->query('SELECT id FROM jogadores_batebola ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
    manualCheck(count($players)===3,'jogadores disponíveis');
    $pdo->exec("INSERT INTO batebola_especiais(titulo,inicio,fim,valor,vagas) VALUES('QA manual rollback','2099-01-01 19:00:00','2099-01-01 21:00:00',15,2)");
    $id=(int)$pdo->lastInsertId();$evento=especialBuscar($pdo,$id,true);
    especialAdicionarPago($pdo,$evento,(int)$players[0]);
    $rows=especialInscritos($pdo,$id);
    manualCheck(count($rows)===1 && $rows[0]['status']==='pago' && (float)$rows[0]['valor']===15.0 && !$rows[0]['presente'],'inclusão paga com valor do evento e presença separada');
    manualReject(fn()=>especialAdicionarPago($pdo,$evento,(int)$players[0]),'duplicidade bloqueada');
    manualReject(fn()=>especialAdicionarPago($pdo,$evento,0),'jogador inexistente bloqueado');
    $pdo->prepare("INSERT INTO batebola_especial_inscricoes(evento_id,jogador_id,valor,chave_pagamento,mp_payment_id) VALUES(?,?,15,'qa-manual','qa-pix')")->execute([$id,$players[1]]);
    manualReject(fn()=>especialAdicionarPago($pdo,$evento,(int)$players[1]),'PIX existente protegido');
    manualReject(fn()=>especialAdicionarPago($pdo,$evento,(int)$players[2]),'lotação protegida incluindo pendentes');
    $pdo->prepare('UPDATE batebola_especial_inscricoes SET mp_payment_id=NULL WHERE evento_id=?')->execute([$id]);
    especialAdicionarPago($pdo,$evento,(int)$players[1]);
    manualCheck(count(especialInscritos($pdo,$id))===2,'pendente sem PIX reutilizado sem duplicar');
    $first=(int)$rows[0]['id'];
    especialEditarConfirmado($pdo,$evento,$first,'tipo_confirmacao','isento');
    $st=$pdo->prepare('SELECT valor FROM batebola_especial_inscricoes WHERE id=?');$st->execute([$first]);
    manualCheck((float)$st->fetchColumn()===0.0,'pago convertido em isento sem receita');
    $evento['seed']=123;
    manualCheck(count(especialTimes($pdo,$evento))>0,'isento participa do sorteio');
    especialEditarConfirmado($pdo,$evento,$first,'tipo_confirmacao','pago');
    $st->execute([$first]);manualCheck((float)$st->fetchColumn()===15.0,'correção restaura valor do evento');
    especialEditarConfirmado($pdo,$evento,$first,'excluir_confirmado');
    especialAdicionarPago($pdo,$evento,(int)$players[2],true);
    $confirmed=array_filter(especialInscritos($pdo,$id),fn($i)=>$i['status']==='pago');
    manualCheck(count($confirmed)===2 && array_sum(array_column($confirmed,'valor'))===15.0,'exclusão libera vaga e inclusão isenta preserva total');
    $pdo->prepare("UPDATE batebola_especial_inscricoes SET mp_payment_id='qa-online' WHERE id=?")->execute([$first]);
    $pdo->prepare("UPDATE batebola_especial_inscricoes SET status='pago' WHERE id=?")->execute([$first]);
    manualReject(fn()=>especialEditarConfirmado($pdo,$evento,$first,'tipo_confirmacao','isento'),'pagamento online não é reclassificado');
    $evento['status']='encerrado';
    manualReject(fn()=>especialAdicionarPago($pdo,$evento,(int)$players[2]),'histórico encerrado protegido');
}finally{$pdo->rollBack();$pdo->query("SELECT RELEASE_LOCK('mpg_bb_reservas')");}
