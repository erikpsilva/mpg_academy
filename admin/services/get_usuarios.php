<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$pdo = getDbConnection();

$stmt = $pdo->query("
    SELECT id, nome_completo, email, cpf, nivel_acesso, created_at, updated_at
    FROM admin_usuarios
    ORDER BY id ASC
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'usuarios' => $usuarios]);
