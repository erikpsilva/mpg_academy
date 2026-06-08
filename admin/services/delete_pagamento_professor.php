<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); exit; }

if (($_SESSION['usuario']['nivel_acesso'] ?? '') === 'professor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Busca comprovante antes de deletar para remover o arquivo
$st = $pdo->prepare("SELECT comprovante FROM professor_pagamentos WHERE id = ?");
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);

$pdo->prepare("DELETE FROM professor_pagamentos WHERE id = ?")->execute([$id]);

if (!empty($row['comprovante'])) {
    $path = dirname(__FILE__, 3) . '/uploads/comprovantes/' . $row['comprovante'];
    if (file_exists($path)) @unlink($path);
}

echo json_encode(['success' => true]);
