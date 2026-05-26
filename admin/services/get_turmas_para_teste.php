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
        t.id, t.nome, t.nivel, t.max_alunos,
        q.nome AS quadra_nome,
        (SELECT COUNT(*) FROM turma_alunos ta
            WHERE ta.turma_id = t.id AND ta.status = 'ativo') AS alunos_ativos,
        (SELECT COUNT(*) FROM aulas_experimentais ae
            WHERE ae.turma_id = t.id AND ae.status = 'agendada') AS agendadas
    FROM turmas t
    JOIN quadras q ON q.id = t.quadra_id
    WHERE t.status = 'ativa'
    ORDER BY q.nome ASC, t.nome ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$t) {
    $t['id']            = (int) $t['id'];
    $t['max_alunos']    = $t['max_alunos'] !== null ? (int) $t['max_alunos'] : null;
    $t['alunos_ativos'] = (int) $t['alunos_ativos'];
    $t['agendadas']     = (int) $t['agendadas'];

    if ($t['max_alunos'] !== null) {
        $t['vagas_teste'] = max(0, $t['max_alunos'] - $t['alunos_ativos'] - $t['agendadas']);
    } else {
        $t['vagas_teste'] = null; // sem limite
    }
}

echo json_encode(['success' => true, 'turmas' => $rows]);
