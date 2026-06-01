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

// Apenas lançamentos manuais podem ser excluídos
$st = $pdo->prepare("SELECT origem FROM lancamentos_financeiros WHERE id = ?");
$st->execute([$id]);
$l = $st->fetch();
if (!$l) { echo json_encode(['success'=>false,'message'=>'Lançamento não encontrado.']); exit; }
if ($l['origem'] === 'auto') { echo json_encode(['success'=>false,'message'=>'Lançamentos automáticos não podem ser excluídos.']); exit; }

$pdo->prepare("DELETE FROM lancamentos_financeiros WHERE id = ?")->execute([$id]);
echo json_encode(['success'=>true]);
