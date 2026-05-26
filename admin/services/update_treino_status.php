<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$id     = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($id <= 0 || !in_array($status, ['agendado', 'realizado', 'cancelado'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$pdo = getDbConnection();
$pdo->prepare("UPDATE turma_treinos SET status = ? WHERE id = ?")->execute([$status, $id]);

echo json_encode(['success' => true]);
