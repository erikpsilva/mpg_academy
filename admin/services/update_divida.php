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

$id        = (int) ($_POST['id']        ?? 0);
$descricao = trim($_POST['descricao']   ?? '');
$credor    = trim($_POST['credor']      ?? '') ?: null;
$categoria = trim($_POST['categoria']   ?? 'outros');
$observacao= trim($_POST['observacao']  ?? '') ?: null;

if ($id <= 0 || !$descricao) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória.']);
    exit;
}

$categoriasValidas = ['outros','equipamento','reforma','aluguel','administrativo'];
if (!in_array($categoria, $categoriasValidas)) $categoria = 'outros';

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$check = $pdo->prepare("SELECT id FROM dividas WHERE id = ?");
$check->execute([$id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Dívida não encontrada.']);
    exit;
}

$pdo->prepare("
    UPDATE dividas SET descricao = ?, credor = ?, categoria = ?, observacao = ? WHERE id = ?
")->execute([$descricao, $credor, $categoria, $observacao, $id]);

echo json_encode(['success' => true]);
