<?php
// Executar apenas no XAMPP: php tests/batebola_especial_test.php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/config/batebola_especial.php';
if(!APP_IS_LOCAL) throw new RuntimeException('Teste permitido apenas no banco local.');
$pdo=getDbConnection();
function checkSpecial(bool $ok,string $label): void { if(!$ok) throw new RuntimeException($label); echo 'OK '.$label.PHP_EOL; }
$before=$pdo->query('SELECT COUNT(*) FROM batebola_inscricoes')->fetchColumn();
$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO batebola_especiais(titulo,inicio,fim,valor,vagas,seed) VALUES('QA rollback','2099-01-04 19:00:00','2099-01-04 21:00:00',25,24,12345)");
    $evento=(int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO batebola_especiais(titulo,inicio,fim,valor,vagas) VALUES('QA isolado','2099-01-04 19:00:00','2099-01-04 21:00:00',30,24)");
    $outro=(int)$pdo->lastInsertId();
    $players=$pdo->query('SELECT id FROM jogadores_batebola ORDER BY id LIMIT 13')->fetchAll(PDO::FETCH_COLUMN);
    checkSpecial(count($players)===13,'13 jogadores locais disponíveis');
    foreach($players as $n=>$player) {
        $pdo->prepare("INSERT INTO batebola_especial_inscricoes(evento_id,jogador_id,valor,chave_pagamento) VALUES(?,?,25,?)")->execute([$evento,$player,'qa-'.$n]);
        $ins=(int)$pdo->lastInsertId();
        $payment=['id'=>'qa-special-'.$ins,'status'=>'approved','metadata'=>['batebola_especial_inscricao_id'=>$ins],'external_reference'=>'bb-especial-'.$ins,'transaction_amount'=>25,'currency_id'=>'BRL'];
        if($n===0){$bad=$payment;$bad['transaction_amount']=1;checkSpecial(!especialConfirmar($pdo,$ins,$bad),'valor incorreto não confirma');$bad=$payment;$bad['external_reference']='batebola-'.$ins;checkSpecial(!especialConfirmar($pdo,$ins,$bad),'referência tradicional não confirma especial');}
        checkSpecial(especialConfirmar($pdo,$ins,$payment),'confirmação especial '.$n);
        checkSpecial(!especialConfirmar($pdo,$ins,$payment),'webhook repetido é idempotente '.$n);
    }
    $e=especialBuscar($pdo,$evento);$times=especialTimes($pdo,$e);
    checkSpecial(count($times)===3,'13 participantes geram 3 times');
    $ids=[];foreach($times as $time){checkSpecial(count($time['jogadores'])<=6,'máximo 6 por time');foreach($time['jogadores'] as $j)$ids[]=$j['id'];}
    checkSpecial(count(array_unique($ids))===13,'cada participante aparece uma vez');
    checkSpecial(especialTimes($pdo,$e)===$times,'sorteio persistente');
    checkSpecial(especialInscritos($pdo,$outro)===[],'evento no mesmo horário tem lista independente');
    checkSpecial(especialAberto($e),'evento futuro aceita inscrição');
    $e['fim']='2000-01-01 21:00:00';checkSpecial(especialEstado($e)==='encerrado' && !especialAberto($e),'encerramento automático');
    checkSpecial($pdo->query('SELECT COUNT(*) FROM batebola_inscricoes')->fetchColumn()===$before,'inscrições tradicionais preservadas');
} finally { $pdo->rollBack(); }
echo 'Todos os dados de teste foram revertidos.'.PHP_EOL;
