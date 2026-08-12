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

$id    = (int) ($_POST['id'] ?? 0);
$nivel = (int) ($_POST['nivel'] ?? 0);

if ($id <= 0 || $nivel < 1 || $nivel > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$check = $pdo->prepare("SELECT id FROM jogadores_batebola WHERE id = ?");
$check->execute([$id]);
if (!$check->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Jogador não encontrado.']);
    exit;
}

$pdo->prepare("UPDATE jogadores_batebola SET nivel = ?, atualizado_em = NOW() WHERE id = ?")
    ->execute([$nivel, $id]);

echo json_encode(['success' => true]);
