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
    SELECT t.id, t.nome, t.valor_mensalidade, q.nome AS quadra_nome
    FROM turmas t
    LEFT JOIN quadras q ON q.id = t.quadra_id
    WHERE t.status = 'ativa' OR t.status IS NULL
    ORDER BY q.nome, t.nome
");

echo json_encode(['success' => true, 'turmas' => $stmt->fetchAll()]);
