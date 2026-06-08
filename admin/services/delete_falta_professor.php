<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); exit; }

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$pdo->prepare("DELETE FROM professor_faltas WHERE id = ?")->execute([$id]);
echo json_encode(['success' => true]);
