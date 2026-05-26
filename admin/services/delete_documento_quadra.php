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

require_once dirname(__FILE__, 3) . '/config/database.php';

$id  = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$pdo  = getDbConnection();
$stmt = $pdo->prepare("SELECT caminho FROM quadra_documentos WHERE id = ?");
$stmt->execute([$id]);
$doc  = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Documento não encontrado.']);
    exit;
}

$fp = ROOT . '/' . $doc['caminho'];
if (file_exists($fp)) unlink($fp);

$pdo->prepare("DELETE FROM quadra_documentos WHERE id = ?")->execute([$id]);

echo json_encode(['success' => true, 'message' => 'Documento excluído.']);
