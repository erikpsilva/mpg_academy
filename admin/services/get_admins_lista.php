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

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->query("SELECT id, nome_completo, nivel_acesso FROM admin_usuarios ORDER BY nome_completo ASC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'admins' => $admins]);
