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

if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$inscricaoId = (int) ($_POST['inscricao_id'] ?? 0);
if ($inscricaoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Inscrição inválida.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$st = $pdo->prepare("SELECT id FROM batebola_inscricoes WHERE id = ? AND status = 'pago'");
$st->execute([$inscricaoId]);
if (!$st->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Confirmação não encontrada.']);
    exit;
}

$pdo->prepare("UPDATE batebola_inscricoes SET status = 'cancelado' WHERE id = ?")->execute([$inscricaoId]);

echo json_encode(['success' => true]);
