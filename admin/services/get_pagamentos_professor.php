<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) { http_response_code(403); exit; }

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';

if ($nivel === 'professor') {
    $profId = (int) $_SESSION['usuario']['professor_id'];
} else {
    $profId = (int) ($_GET['professor_id'] ?? 0);
}

if ($profId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$st = $pdo->prepare("
    SELECT id, valor, data_pagamento, referencia, observacao, comprovante, criado_em
    FROM professor_pagamentos
    WHERE professor_id = ?
    ORDER BY data_pagamento DESC, id DESC
");
$st->execute([$profId]);
$pagamentos = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'pagamentos' => $pagamentos]);
