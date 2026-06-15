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

$data    = trim($_POST['data']     ?? '');
$turmaId = (int) ($_POST['turma_id'] ?? 0);
$motivo  = trim($_POST['motivo']   ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$turmaIdSave = $turmaId > 0 ? $turmaId : null;

try {
    // Verifica duplicidade antes de inserir (turma_id pode ser NULL)
    $chkSt = $pdo->prepare("
        SELECT id FROM aulas_canceladas
        WHERE data = ? AND (
            (turma_id IS NULL AND ? IS NULL) OR turma_id = ?
        )
    ");
    $chkSt->execute([$data, $turmaIdSave, $turmaIdSave]);
    if ($chkSt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Esta data já está cancelada para esta turma.']);
        exit;
    }

    $pdo->prepare("
        INSERT INTO aulas_canceladas (turma_id, data, motivo)
        VALUES (?, ?, ?)
    ")->execute([$turmaIdSave, $data, $motivo ?: null]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
}
