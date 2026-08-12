<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__) . '/lib_pagamento_professor.php';
$pdo = getDbConnection();

$ids = $pdo->query("SELECT id FROM professores WHERE status = 'ativo'")->fetchAll(PDO::FETCH_COLUMN);

$valores = [];
foreach ($ids as $profId) {
    $valores[$profId] = calcularValorDevido($pdo, (int) $profId)['valor_devido'];
}

echo json_encode(['success' => true, 'valores' => $valores]);
