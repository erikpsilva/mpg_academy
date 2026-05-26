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
if ($turmaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$pdo  = getDbConnection();
$stmt = $pdo->prepare("SELECT id, data_treino, status, observacao FROM turma_treinos WHERE turma_id = ? ORDER BY data_treino ASC");
$stmt->execute([$turmaId]);
$treinos = $stmt->fetchAll();

echo json_encode(['success' => true, 'treinos' => $treinos]);
