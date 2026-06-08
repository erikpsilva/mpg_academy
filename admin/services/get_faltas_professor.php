<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$profId = (int) ($_GET['professor_id'] ?? 0);
if ($profId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stTurmas = $pdo->prepare("
    SELECT pt.turma_id, t.nome AS turma_nome
    FROM professor_turmas pt
    JOIN turmas t ON t.id = pt.turma_id
    WHERE pt.professor_id = ?
    ORDER BY t.nome
");
$stTurmas->execute([$profId]);
$turmas = $stTurmas->fetchAll(PDO::FETCH_ASSOC);

$stFaltas = $pdo->prepare("
    SELECT pf.id, pf.turma_id, pf.data, pf.tipo, pf.observacao, t.nome AS turma_nome
    FROM professor_faltas pf
    JOIN turmas t ON t.id = pf.turma_id
    WHERE pf.professor_id = ?
    ORDER BY pf.data DESC
    LIMIT 200
");
$stFaltas->execute([$profId]);
$faltas = $stFaltas->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'turmas' => $turmas, 'faltas' => $faltas]);
