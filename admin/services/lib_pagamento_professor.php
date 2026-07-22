<?php
require_once dirname(__FILE__, 2) . '/pages/frequencia-professor/frequencia_helpers.php';

/**
 * Saldo devido a um professor: soma do saldo de todos os meses do extrato (aulas concluídas +
 * ajuda de custo mensal, menos pagamentos já registrados). Reaproveita calcularExtratoMensal()
 * pra não duplicar a lógica de cálculo.
 */
function calcularValorDevido(PDO $pdo, int $profId): array {
    $meses = calcularExtratoMensal($pdo, $profId);

    $ganho = array_sum(array_column($meses, 'ganho'));
    $pago  = array_sum(array_column($meses, 'pago'));

    return [
        'total_ganho'  => round($ganho, 2),
        'total_pago'   => round($pago, 2),
        'valor_devido' => round($ganho - $pago, 2),
    ];
}

/**
 * Extrato mês a mês: quanto foi ganho (aulas concluídas × valor da aula, + ajuda de custo
 * mensal fixa, quando o professor tiver uma cadastrada) e quanto foi pago em cada competência,
 * pra saber exatamente quais meses já foram quitados — modelo pré-pago inverso (o professor
 * trabalha no mês e recebe no mês seguinte). A ajuda de custo é somada em todo mês em que o
 * professor tem aula agendada, independente de quantas aulas foram de fato concluídas — é um
 * valor fixo por contrato, não por aula.
 */
function calcularExtratoMensal(PDO $pdo, int $profId): array {
    [, $porMes, ] = buildFrequencia($pdo, $profId, '2020-01-01', date('Y-m-d'));

    $stProf = $pdo->prepare("SELECT valor_aula_90min, valor_aula_120min, bonus_valor FROM professores WHERE id = ?");
    $stProf->execute([$profId]);
    $prof = $stProf->fetch(PDO::FETCH_ASSOC) ?: [];

    $rate90  = (float) ($prof['valor_aula_90min']  ?? 0);
    $rate120 = (float) ($prof['valor_aula_120min'] ?? 0);
    $bonus   = (float) ($prof['bonus_valor']       ?? 0);

    $ganhoPorMes = [];
    foreach ($porMes as $mes => $aulasDoMes) {
        $ganho = $bonus;
        foreach ($aulasDoMes as $a) {
            if ($a['status'] !== 'concluida') continue;
            $ganho += $a['dur'] >= 110 ? $rate120 : $rate90;
        }
        if ($ganho > 0) $ganhoPorMes[$mes] = $ganho;
    }

    $stPago = $pdo->prepare("
        SELECT competencia, COALESCE(SUM(valor), 0) AS total
        FROM professor_pagamentos
        WHERE professor_id = ? AND competencia IS NOT NULL
        GROUP BY competencia
    ");
    $stPago->execute([$profId]);
    $pagoPorMes = [];
    foreach ($stPago->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pagoPorMes[$row['competencia']] = (float) $row['total'];
    }

    $meses = [];
    foreach (array_unique(array_merge(array_keys($ganhoPorMes), array_keys($pagoPorMes))) as $mes) {
        $ganho = $ganhoPorMes[$mes] ?? 0.0;
        $pago  = $pagoPorMes[$mes] ?? 0.0;
        $status = ($ganho > 0 && $pago >= $ganho) ? 'pago' : ($pago > 0 ? 'parcial' : 'pendente');

        $meses[] = [
            'mes'    => $mes,
            'ganho'  => round($ganho, 2),
            'pago'   => round($pago, 2),
            'saldo'  => round($ganho - $pago, 2),
            'status' => $status,
        ];
    }

    usort($meses, fn($a, $b) => strcmp($b['mes'], $a['mes']));

    return $meses;
}
