<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/app.php';

if (empty($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado.']);
    exit;
}

$file     = $_FILES['imagem'];
$maxBytes = 5 * 1024 * 1024; // 5 MB

if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'Imagem muito grande (máx 5 MB).']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime    = mime_content_type($file['tmp_name']);

if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Formato inválido. Use JPG, PNG ou WebP.']);
    exit;
}

$ext     = $allowed[$mime];
$dir     = dirname(__FILE__, 3) . '/uploads/comunicados/';
$name    = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destAbs = $dir . $name;
$relPath = 'uploads/comunicados/' . $name;

if (!is_dir($dir)) mkdir($dir, 0755, true);

if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo.']);
    exit;
}

echo json_encode([
    'success' => true,
    'path'    => $relPath,
    'url'     => appBaseUrl() . '/' . $relPath,
]);
