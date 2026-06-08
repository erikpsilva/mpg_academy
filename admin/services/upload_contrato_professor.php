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

$profId = (int) ($_POST['professor_id'] ?? 0);
if ($profId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Professor inválido.']);
    exit;
}

if (empty($_FILES['contrato']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES['contrato'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo.']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    echo json_encode(['success' => false, 'message' => 'Apenas arquivos PDF são aceitos.']);
    exit;
}
if ($file['size'] > 20 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande (máx. 20 MB).']);
    exit;
}

$uploadDir = dirname(__FILE__, 3) . '/uploads/contratos_professor/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$filename = 'prof_' . $profId . '_' . time() . '.pdf';
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar o arquivo no servidor.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$token = bin2hex(random_bytes(32));

try {
    $pdo->prepare("
        INSERT INTO professor_contratos (professor_id, arquivo, token)
        VALUES (?, ?, ?)
    ")->execute([$profId, $filename, $token]);

    echo json_encode(['success' => true, 'token' => $token]);
} catch (PDOException $e) {
    @unlink($uploadDir . $filename);
    error_log('[upload_contrato_professor] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar contrato no banco de dados.']);
}
