<?php
require_once __DIR__ . '/mobile_auth.php';
$aluno = mobileAuth();

$pdo  = getDbConnection();
$hoje = new DateTime('today');

$cols = "m.id, m.referencia, m.valor, m.vencimento, m.data_pagamento, m.status,
         t.nome AS turma_nome";

// Próxima fatura a pagar (mais antiga pendente/atrasada)
$stNext = $pdo->prepare("
    SELECT $cols
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.aluno_id = ? AND m.status IN ('pendente', 'atrasado')
    ORDER BY m.vencimento ASC
    LIMIT 1
");
$stNext->execute([$aluno['id']]);
$next = $stNext->fetch(PDO::FETCH_ASSOC);

// Última fatura paga
$stPaid = $pdo->prepare("
    SELECT $cols
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.aluno_id = ? AND m.status = 'pago'
    ORDER BY m.referencia DESC
    LIMIT 1
");
$stPaid->execute([$aluno['id']]);
$paid = $stPaid->fetch(PDO::FETCH_ASSOC);

$rows = array_values(array_filter([$next, $paid]));

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
        $m['display_status'] = $venc >= $hoje ? 'a_vencer' : 'atrasado';
    } else {
        $m['display_status'] = 'pago';
    }
}
unset($m);

echo json_encode(['success' => true, 'data' => $rows]);
