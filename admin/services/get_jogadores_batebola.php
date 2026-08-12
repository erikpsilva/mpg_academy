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

$stmt = $pdo->query("SELECT id, nome, email, celular, altura_cm, foto, nivel FROM jogadores_batebola ORDER BY nome ASC");
$jogadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'jogadores' => $jogadores]);
