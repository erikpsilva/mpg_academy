<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] !== 'professor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo      = getDbConnection();
$profId   = (int) $_SESSION['usuario']['professor_id'];

// Dados do professor
$stProf = $pdo->prepare("
    SELECT id, nome, sobrenome, email, cpf, celular, data_nascimento,
           dia_pagamento, valor_aula_90min, valor_aula_120min, status,
           bonus_titulo, bonus_valor
    FROM professores WHERE id = ?
");
$stProf->execute([$profId]);
$prof = $stProf->fetch(PDO::FETCH_ASSOC);

if (!$prof) {
    echo json_encode(['success' => false, 'message' => 'Professor não encontrado.']);
    exit;
}

// Turmas + horários do professor
$stTurmas = $pdo->prepare("
    SELECT pt.turma_id, pt.data_inicio,
           t.nome AS turma_nome, t.status AS turma_status,
           qh.dia_semana, qh.hora_inicio, qh.hora_fim
    FROM professor_turmas pt
    JOIN turmas t ON t.id = pt.turma_id
    JOIN turma_horarios th ON th.turma_id = pt.turma_id
    JOIN quadra_horarios qh ON qh.id = th.horario_id
    WHERE pt.professor_id = ?
    ORDER BY t.nome, qh.dia_semana, qh.hora_inicio
");
$stTurmas->execute([$profId]);
$rows = $stTurmas->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ───────────────────────────────────────────────────────────────────
function minsFromTime(string $t): int {
    list($h, $m) = explode(':', $t);
    return (int)$h * 60 + (int)$m;
}
function countWeekdays(string $from, string $to, int $dow): int {
    if ($from > $to) return 0;
    $count = 0;
    $cur = strtotime($from);
    $end = strtotime($to);
    while ($cur <= $end) {
        if ((int)date('w', $cur) === $dow) $count++;
        $cur = strtotime('+1 day', $cur);
    }
    return $count;
}

$hoje      = date('Y-m-d');
$diaPgto   = (int)($prof['dia_pagamento'] ?? 0);
$diaAtual  = (int) date('j');

// Determina próxima data de pagamento
if ($diaPgto > 0) {
    if ($diaPgto >= $diaAtual) {
        $pgtoDate = date('Y-m-') . str_pad($diaPgto, 2, '0', STR_PAD_LEFT);
    } else {
        $pgtoDate = date('Y-m-', strtotime('+1 month')) . str_pad($diaPgto, 2, '0', STR_PAD_LEFT);
    }
} else {
    $pgtoDate = null;
}

$DIAS_PT = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

// MySQL DAYOFWEEK: 1=Dom, 2=Seg ... 7=Sáb → dia_semana PHP (0-6) + 1
$stFaltaCount = $pdo->prepare("
    SELECT COUNT(*) FROM professor_faltas
    WHERE professor_id = ? AND turma_id = ?
      AND data BETWEEN ? AND ?
      AND DAYOFWEEK(data) = ?
");

// Agrupa por turma
$turmas = [];
foreach ($rows as $row) {
    $tid = $row['turma_id'];
    if (!isset($turmas[$tid])) {
        $turmas[$tid] = [
            'id'            => $tid,
            'nome'          => $row['turma_nome'],
            'status'        => $row['turma_status'],
            'data_inicio'   => $row['data_inicio'],
            'horarios'      => [],
            'ganhos_hoje'   => 0.0,
            'projecao'      => 0.0,
        ];
    }

    $minutos = minsFromTime($row['hora_fim']) - minsFromTime($row['hora_inicio']);
    $valor   = $minutos >= 110
        ? (float)($prof['valor_aula_120min'] ?? 0)
        : (float)($prof['valor_aula_90min']  ?? 0);

    $dataInicio = $row['data_inicio'] ?? date('Y-m-01');
    $from = max($dataInicio, date('Y-m-01'));
    $dow  = (int) $row['dia_semana'];

    $aulasFeitas = countWeekdays($from, $hoje, $dow);

    // Projeção: de amanhã até o pagamento, respeitando também a data de início da turma
    $projFrom  = date('Y-m-d', strtotime('+1 day'));
    if ($dataInicio > $projFrom) $projFrom = $dataInicio;
    $aulasProj = $pgtoDate ? countWeekdays($projFrom, $pgtoDate, $dow) : 0;

    // Desconta faltas passadas (já ocorridas)
    $stFaltaCount->execute([$profId, $tid, $from, $hoje, $dow + 1]);
    $faltas = (int) $stFaltaCount->fetchColumn();
    $aulasFeitas = max(0, $aulasFeitas - $faltas);

    // Desconta faltas futuras (planejadas) da projeção
    $faltasProj = 0;
    if ($aulasProj > 0 && $pgtoDate) {
        $stFaltaCount->execute([$profId, $tid, $projFrom, $pgtoDate, $dow + 1]);
        $faltasProj = (int) $stFaltaCount->fetchColumn();
        $aulasProj  = max(0, $aulasProj - $faltasProj);
    }

    $turmas[$tid]['ganhos_hoje'] += $aulasFeitas * $valor;
    $turmas[$tid]['projecao']    += $aulasProj   * $valor;
    $turmas[$tid]['horarios'][]   = [
        'dia_semana'   => $dow,
        'dia_nome'     => $DIAS_PT[$dow] ?? '',
        'hora_inicio'  => substr($row['hora_inicio'], 0, 5),
        'hora_fim'     => substr($row['hora_fim'],    0, 5),
        'duracao_min'  => $minutos,
        'valor_aula'   => $valor,
        'aulas_feitas' => $aulasFeitas,
        'faltas'       => $faltas,
    ];
}

$turmasArr      = array_values($turmas);
$totalGanhos    = array_sum(array_column($turmasArr, 'ganhos_hoje'));
$totalProjecao  = array_sum(array_column($turmasArr, 'projecao'));
$bonusValor     = (float)($prof['bonus_valor'] ?? 0);

echo json_encode([
    'success'         => true,
    'professor'       => [
        'id'               => $prof['id'],
        'nome'             => $prof['nome'],
        'sobrenome'        => $prof['sobrenome'],
        'email'            => $prof['email'],
        'celular'          => $prof['celular'] ?? '',
        'dia_pagamento'    => $diaPgto ?: null,
        'pgto_date'        => $pgtoDate,
        'valor_90min'      => (float)($prof['valor_aula_90min']  ?? 0),
        'valor_120min'     => (float)($prof['valor_aula_120min'] ?? 0),
        'bonus_titulo'     => $prof['bonus_titulo'] ?? null,
        'bonus_valor'      => $bonusValor > 0 ? $bonusValor : null,
    ],
    'turmas'          => $turmasArr,
    'total_ganhos'    => $totalGanhos,
    'total_projecao'  => $totalProjecao,
    'total_esperado'  => $totalGanhos + $totalProjecao + $bonusValor,
]);
