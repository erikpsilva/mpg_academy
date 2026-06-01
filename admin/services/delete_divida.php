<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID inválido.']); exit; }

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Impede exclusão se já há parcelas pagas
$stCheck = $pdo->prepare("SELECT COUNT(*) FROM parcelas_dividas WHERE divida_id = ? AND status != 'pendente'");
$stCheck->execute([$id]);
if ((int)$stCheck->fetchColumn() > 0) {
    echo json_encode(['success'=>false,'message'=>'Esta dívida possui parcelas já pagas e não pode ser excluída.']);
    exit;
}

// CASCADE remove as parcelas automaticamente
$pdo->prepare("DELETE FROM dividas WHERE id = ?")->execute([$id]);
echo json_encode(['success'=>true]);
