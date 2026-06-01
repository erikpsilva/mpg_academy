<?php
require_once __DIR__ . '/mobile_auth.php';
$aluno = mobileAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$base64 = $body['foto_base64'] ?? '';
$tipo   = $body['tipo']        ?? 'image/jpeg';

if (empty($base64)) {
    echo json_encode(['success' => false, 'message' => 'Nenhuma imagem enviada.']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$tipo])) {
    echo json_encode(['success' => false, 'message' => 'Formato inválido.']);
    exit;
}

$imageData = base64_decode($base64);
if ($imageData === false || strlen($imageData) > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Imagem inválida ou muito grande (máx 5 MB).']);
    exit;
}

$ext     = $allowed[$tipo];
$dir     = dirname(__FILE__, 3) . '/uploads/alunos/';
$name    = 'aluno_' . $aluno['id'] . '_' . time() . '.' . $ext;
$relPath = 'uploads/alunos/' . $name;

if (!is_dir($dir)) mkdir($dir, 0755, true);

if (file_put_contents($dir . $name, $imageData) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar imagem.']);
    exit;
}

$pdo = getDbConnection();
$pdo->prepare("UPDATE alunos SET foto = ? WHERE id = ?")->execute([$relPath, $aluno['id']]);

require_once dirname(__FILE__, 3) . '/config/app.php';
echo json_encode([
    'success'   => true,
    'foto_url'  => appBaseUrl() . '/' . $relPath,
    'foto_path' => $relPath,
]);
