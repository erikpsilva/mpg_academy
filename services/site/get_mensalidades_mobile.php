<?php
require_once __DIR__ . '/mobile_auth.php';
$aluno = mobileAuth();

$pdo   = getDbConnection();
$hoje  = new DateTime('today');

$stmt = $pdo->prepare("
    SELECT m.id, m.referencia, m.valor, m.vencimento, m.data_pagamento, m.status,
           t.nome AS turma_nome
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.aluno_id = ?
    ORDER BY m.referencia DESC
");
$stmt->execute([$aluno['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$m) {
    $m['id']    = (int)   $m['id'];
    $m['valor'] = (float) $m['valor'];
    $venc       = new DateTime($m['vencimento']);

    if ($m['status'] === 'atrasado') {
        $dias  = (int) $venc->diff($hoje)->days;
        $multa = $m['valor'] * 0.05;
        $base  = $m['valor'] + $multa;
        $juros = $base * 0.005 * $dias;
        $m['dias_atraso']    = $dias;
        $m['multa']          = round($multa, 2);
        $m['juros']          = round($juros, 2);
        $m['total_devido']   = round($base + $juros, 2);
        $m['display_status'] = 'atrasado';
    } elseif ($m['status'] === 'pendente') {
        // A VENCER: vencimento ainda no futuro
        $m['display_status'] = $venc >= $hoje ? 'a_vencer' : 'atrasado';
    } else {
        $m['display_status'] = $m['status']; // pago
    }
}

echo json_encode(['success'=>true, 'data'=>$rows]);
