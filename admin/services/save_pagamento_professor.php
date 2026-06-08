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

$profId    = (int)   trim($_POST['professor_id']  ?? '');
$valorRaw  =         trim($_POST['valor']          ?? '');
$dataPgto  =         trim($_POST['data_pagamento'] ?? '');
$referencia =        trim($_POST['referencia']     ?? '') ?: null;
$obs        =        trim($_POST['observacao']     ?? '') ?: null;

$valor = (float) str_replace(['.', ','], ['', '.'], $valorRaw);

if ($profId <= 0 || $valor <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPgto)) {
    echo json_encode(['success' => false, 'message' => 'Preencha professor, valor e data corretamente.']);
    exit;
}

// Upload do comprovante
$comprovanteFile = null;
if (!empty($_FILES['comprovante']['name'])) {
    $file = $_FILES['comprovante'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erro no envio do arquivo.']);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
        echo json_encode(['success' => false, 'message' => 'Comprovante: apenas PDF, JPG ou PNG são aceitos.']);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Comprovante: tamanho máximo é 5MB.']);
        exit;
    }

    $uploadDir = dirname(__FILE__, 3) . '/uploads/comprovantes/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $comprovanteFile = 'prof_' . $profId . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $comprovanteFile)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar comprovante no servidor.']);
        exit;
    }
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

try {
    $pdo->prepare("
        INSERT INTO professor_pagamentos (professor_id, valor, data_pagamento, referencia, observacao, comprovante)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$profId, $valor, $dataPgto, $referencia, $obs, $comprovanteFile]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($comprovanteFile && isset($uploadDir)) {
        @unlink($uploadDir . $comprovanteFile);
    }
    error_log('[save_pagamento_professor] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar pagamento.']);
}
