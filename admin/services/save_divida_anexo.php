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

$dividaId = (int) ($_POST['divida_id'] ?? 0);
if ($dividaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de dívida inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$check = $pdo->prepare("SELECT id FROM dividas WHERE id = ?");
$check->execute([$dividaId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Dívida não encontrada.']);
    exit;
}

$allowedMimes = [
    'application/pdf',
    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
];

$uploadDir = dirname(__FILE__, 3) . '/uploads/dividas/' . $dividaId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$files = $_FILES['anexos'] ?? [];
if (empty($files['name'][0])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado.']);
    exit;
}

$salvos = [];
$erros  = [];

$count = count($files['name']);
for ($i = 0; $i < $count; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $erros[] = $files['name'][$i] . ': erro no upload.';
        continue;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $files['tmp_name'][$i]);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes)) {
        $erros[] = $files['name'][$i] . ': tipo não permitido.';
        continue;
    }

    if ($files['size'][$i] > 10 * 1024 * 1024) {
        $erros[] = $files['name'][$i] . ': arquivo muito grande (máx 10 MB).';
        continue;
    }

    $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
    $filename = uniqid('anx_') . ($ext ? '.' . strtolower($ext) : '');
    $destino  = $uploadDir . $filename;

    if (!move_uploaded_file($files['tmp_name'][$i], $destino)) {
        $erros[] = $files['name'][$i] . ': falha ao salvar.';
        continue;
    }

    $caminho = 'uploads/dividas/' . $dividaId . '/' . $filename;
    $pdo->prepare("
        INSERT INTO dividas_anexos (divida_id, nome_original, caminho, tipo_mime, tamanho)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$dividaId, $files['name'][$i], $caminho, $mime, $files['size'][$i]]);

    $salvos[] = [
        'id'           => (int) $pdo->lastInsertId(),
        'nome_original'=> $files['name'][$i],
        'caminho'      => $caminho,
        'tipo_mime'    => $mime,
    ];
}

echo json_encode([
    'success' => count($salvos) > 0,
    'salvos'  => $salvos,
    'erros'   => $erros,
    'message' => count($erros) > 0 ? implode(' / ', $erros) : null,
]);
