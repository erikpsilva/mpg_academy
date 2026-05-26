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

$id              = (int) ($_POST['id'] ?? 0);
$valorMensalidade = isset($_POST['valor_mensalidade']) && $_POST['valor_mensalidade'] !== ''
    ? (float) $_POST['valor_mensalidade']
    : null;
$dataInicio = trim($_POST['data_inicio'] ?? '');
$dataInicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) ? $dataInicio : null;
$status     = in_array($_POST['status'] ?? '', ['ativa', 'inativa']) ? $_POST['status'] : 'ativa';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

$pdo  = getDbConnection();
$stmt = $pdo->prepare("UPDATE turmas SET valor_mensalidade=?, data_inicio=?, status=?, created_at=created_at WHERE id=?");
$stmt->execute([$valorMensalidade, $dataInicio, $status, $id]);

echo json_encode(['success' => true, 'message' => 'Configurações salvas.']);
