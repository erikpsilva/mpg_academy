<?php
require_once __DIR__ . '/batebola.php';

function especialAgora(): string { return (new DateTimeImmutable('now', new DateTimeZone(BATEBOLA_FUSO)))->format('Y-m-d H:i:s'); }
function especialHtml($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function especialDisponivel(PDO $pdo): bool {
    try { $pdo->query('SELECT id FROM batebola_especiais LIMIT 0'); return true; }
    catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) === 1146) return false; throw $e; }
}
function especialBuscar(PDO $pdo, int $id, bool $lock = false): ?array {
    $st=$pdo->prepare('SELECT * FROM batebola_especiais WHERE id=?'.($lock?' FOR UPDATE':'')); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
function especialEstado(array $evento): string {
    return $evento['fim'] <= especialAgora() ? 'encerrado' : $evento['status'];
}
function especialAberto(array $evento): bool { return especialEstado($evento)==='aberto' && $evento['inicio']>especialAgora(); }
function especialData(array $evento): string {
    $d=new DateTimeImmutable($evento['inicio']);
    $dias=['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
    return $dias[(int)$d->format('w')].', '.$d->format('d/m/Y').' • '.$d->format('H:i').' às '.(new DateTimeImmutable($evento['fim']))->format('H:i');
}
function especialInscritos(PDO $pdo, int $id): array {
    $st=$pdo->prepare('SELECT i.*, j.nome, j.nivel, j.altura_cm, j.sexo FROM batebola_especial_inscricoes i JOIN jogadores_batebola j ON j.id=i.jogador_id WHERE i.evento_id=? ORDER BY j.nome,i.id'); $st->execute([$id]); return $st->fetchAll(PDO::FETCH_ASSOC);
}
function especialTimes(PDO $pdo, array $evento): array {
    if (!$evento['seed']) return [];
    $jogadores=[];
    foreach(especialInscritos($pdo,(int)$evento['id']) as $i) if($i['status']==='pago') { $i['id']=$i['jogador_id']; $jogadores[]=$i; }
    return batebolaAplicarTrocas(batebolaSortearTimes($jogadores,(int)$evento['seed']),json_decode($evento['trocas']??'[]',true)?:[]);
}
/** Confirma apenas pagamentos consultados na API, com referência e valor corretos. */
function especialConfirmar(PDO $pdo, int $id, array $payment): bool {
    if (($payment['status']??'')!=='approved' || (int)($payment['metadata']['batebola_especial_inscricao_id']??0)!==$id) return false;
    $st=$pdo->prepare('SELECT * FROM batebola_especial_inscricoes WHERE id=?'); $st->execute([$id]); $i=$st->fetch();
    if (!$i || ($payment['external_reference']??'')!=='bb-especial-'.$id || (int)round((float)($payment['transaction_amount']??0)*100)!==(int)round((float)$i['valor']*100) || ($payment['currency_id']??'')!=='BRL') return false;
    if($i['mp_payment_id'] && (string)$i['mp_payment_id']!==(string)$payment['id']) return false;
    $st=$pdo->prepare("UPDATE batebola_especial_inscricoes SET status='pago',mp_payment_id=?,pago_em=? WHERE id=? AND status='pendente'");
    $st->execute([(string)$payment['id'],especialAgora(),$id]); return $st->rowCount()>0;
}
