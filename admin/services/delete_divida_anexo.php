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

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT * FROM dividas_anexos WHERE id = ?");
$stmt->execute([$id]);
$anexo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$anexo) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Anexo não encontrado.']);
    exit;
}

$arquivo = dirname(__FILE__, 3) . '/' . $anexo['caminho'];
if (file_exists($arquivo)) {
    @unlink($arquivo);
}

$pdo->prepare("DELETE FROM dividas_anexos WHERE id = ?")->execute([$id]);

echo json_encode(['success' => true]);
