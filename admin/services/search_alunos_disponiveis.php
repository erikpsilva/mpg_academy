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

$turmaId = (int) ($_GET['turma_id'] ?? 0);
$busca   = trim($_GET['busca'] ?? '');

if ($turmaId <= 0 || strlen($busca) < 2) {
    echo json_encode(['success' => true, 'alunos' => []]);
    exit;
}

$pdo  = getDbConnection();
$stmt = $pdo->prepare("
    SELECT a.id, a.nome, a.email, a.celular
    FROM alunos a
    WHERE a.status = 'ativo'
      AND a.id NOT IN (SELECT ta.aluno_id FROM turma_alunos ta WHERE ta.turma_id = ?)
      AND (a.nome LIKE ? OR a.email LIKE ?)
    ORDER BY a.nome
    LIMIT 8
");
$stmt->execute([$turmaId, "%$busca%", "%$busca%"]);

echo json_encode(['success' => true, 'alunos' => $stmt->fetchAll()]);
