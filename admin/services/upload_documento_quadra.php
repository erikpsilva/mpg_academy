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

$quadraId = (int) ($_POST['quadra_id'] ?? 0);
if ($quadraId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da quadra inválido.']);
    exit;
}

if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
    $erros = [1=>'Arquivo muito grande (php.ini)',2=>'Arquivo muito grande (formulário)',3=>'Upload incompleto',4=>'Nenhum arquivo enviado'];
    $msg = $erros[$_FILES['documento']['error'] ?? 4] ?? 'Erro no upload.';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$file = $_FILES['documento'];

if ($file['size'] > 20 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo 20 MB.']);
    exit;
}

$allowedMimes = [
    'application/pdf',
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

if (!in_array($mime, $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido.']);
    exit;
}

$uploadDir = ROOT . '/uploads/quadras/' . $quadraId . '/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = uniqid('doc_') . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar o arquivo no servidor.']);
    exit;
}

$caminho = 'uploads/quadras/' . $quadraId . '/' . $filename;
$pdo     = getDbConnection();
$stmt    = $pdo->prepare("INSERT INTO quadra_documentos (quadra_id, nome_original, caminho, tipo_mime, tamanho) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$quadraId, $file['name'], $caminho, $mime, $file['size']]);

echo json_encode([
    'success'    => true,
    'message'    => 'Documento enviado com sucesso.',
    'documento'  => [
        'id'           => (int) $pdo->lastInsertId(),
        'nome_original'=> $file['name'],
        'caminho'      => $caminho,
        'tipo_mime'    => $mime,
        'tamanho'      => $file['size'],
    ],
]);
