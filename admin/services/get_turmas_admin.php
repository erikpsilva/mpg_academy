<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT
        t.id, t.nome, t.nivel, t.genero, t.status,
        t.valor_mensalidade, t.promo_valor, t.promo_meses, t.max_alunos,
        q.id AS quadra_id, q.nome AS quadra_nome,
        GROUP_CONCAT(
            DISTINCT CONCAT(qh.dia_semana, '~', qh.hora_inicio, '~', qh.hora_fim)
            ORDER BY qh.dia_semana, qh.hora_inicio
            SEPARATOR '|'
        ) AS horarios_raw,
        (
            SELECT COUNT(*) FROM turma_alunos ta
            WHERE ta.turma_id = t.id AND ta.status = 'ativo'
        ) AS alunos_ativos,
        (
            SELECT GROUP_CONCAT(CONCAT(ta.aluno_id, '~', a.nome) ORDER BY a.nome SEPARATOR '|')
            FROM turma_alunos ta
            JOIN alunos a ON a.id = ta.aluno_id
            WHERE ta.turma_id = t.id AND ta.status = 'ativo'
        ) AS alunos_raw
    FROM turmas t
    JOIN quadras q ON q.id = t.quadra_id
    LEFT JOIN turma_horarios th ON th.turma_id = t.id
    LEFT JOIN quadra_horarios qh ON qh.id = th.horario_id
    GROUP BY t.id
    ORDER BY q.nome ASC, t.nome ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$t) {
    $t['id']                = (int) $t['id'];
    $t['quadra_id']         = (int) $t['quadra_id'];
    $t['alunos_ativos']     = (int) $t['alunos_ativos'];
    $t['max_alunos']        = $t['max_alunos'] !== null ? (int) $t['max_alunos'] : null;
    $t['valor_mensalidade'] = $t['valor_mensalidade'] !== null ? (float) $t['valor_mensalidade'] : null;
    $t['promo_valor']       = $t['promo_valor']       !== null ? (float) $t['promo_valor']       : null;
    $t['promo_meses']       = $t['promo_meses']       !== null ? (int)   $t['promo_meses']       : null;

    if ($t['alunos_raw']) {
        $t['alunos'] = array_map(function ($entry) {
            [$id, $nome] = explode('~', $entry, 2);
            return ['id' => (int) $id, 'nome' => $nome];
        }, explode('|', $t['alunos_raw']));
    } else {
        $t['alunos'] = [];
    }
    unset($t['alunos_raw']);

    if ($t['horarios_raw']) {
        $t['horarios'] = array_map(function ($h) {
            [$dia, $inicio, $fim] = explode('~', $h, 3);
            return ['dia_semana' => (int) $dia, 'hora_inicio' => $inicio, 'hora_fim' => $fim];
        }, explode('|', $t['horarios_raw']));
    } else {
        $t['horarios'] = [];
    }
    unset($t['horarios_raw']);

    if ($t['max_alunos'] !== null) {
        $t['vagas'] = max(0, $t['max_alunos'] - $t['alunos_ativos']);
    } else {
        $t['vagas'] = null;
    }
}

echo json_encode(['success' => true, 'turmas' => $rows]);
