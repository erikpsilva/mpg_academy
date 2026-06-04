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

$stmt = $pdo->query("
    SELECT
        t.id, t.nome, t.max_alunos,
        q.nome AS quadra_nome,
        (SELECT COUNT(*) FROM turma_alunos ta
            WHERE ta.turma_id = t.id AND ta.status = 'ativo') AS alunos_ativos
    FROM turmas t
    JOIN quadras q ON q.id = t.quadra_id
    WHERE t.status = 'ativa'
    ORDER BY q.nome ASC, t.nome ASC
");
$turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($turmas as &$t) {
    $t['id']           = (int) $t['id'];
    $t['alunos_ativos']= (int) $t['alunos_ativos'];
    $t['max_alunos']   = $t['max_alunos'] !== null ? (int) $t['max_alunos'] : null;
    $t['vagas']        = $t['max_alunos'] !== null
        ? max(0, $t['max_alunos'] - $t['alunos_ativos'])
        : null;
    $t['lotada']       = $t['max_alunos'] !== null && $t['vagas'] === 0;
}
unset($t);

echo json_encode(['success' => true, 'turmas' => $turmas]);
