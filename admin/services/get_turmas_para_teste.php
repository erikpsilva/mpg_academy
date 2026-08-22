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
require_once dirname(__FILE__, 3) . '/config/aulas_teste.php';
$pdo = getDbConnection();

// A vaga de teste e por DATA (ver config/aulas_teste.php). Sem data, devolve so a
// capacidade da turma; com data, desconta quem ja esta marcado NAQUELE dia.
$data = trim($_GET['data'] ?? '');
if ($data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) $data = '';

$stmt = $pdo->prepare("
    SELECT
        t.id, t.nome, t.nivel, t.max_alunos,
        q.nome AS quadra_nome,
        (SELECT COUNT(*) FROM turma_alunos ta
            WHERE ta.turma_id = t.id AND ta.status = 'ativo') AS alunos_ativos
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

    $t['vagas_teste'] = aulaTesteVagasNaData($pdo, $t['id'], $data ?: null, $t['max_alunos']);
}
unset($t);

echo json_encode(['success' => true, 'data' => $data ?: null, 'turmas' => $rows]);
