<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Acesso não autorizado.']); exit; }

$tipo        = in_array($_POST['tipo'] ?? '', ['receita','despesa']) ? $_POST['tipo'] : 'despesa';
$categoria   = trim($_POST['categoria']   ?? '');
$descricao   = trim($_POST['descricao']   ?? '');
$data        = trim($_POST['data']        ?? date('Y-m-d'));
$competencia = trim($_POST['competencia'] ?? date('Y-m'));
$observacao  = trim($_POST['observacao']  ?? '');
$valorRaw    = trim($_POST['valor']       ?? '');
$valor       = (float) str_replace(['.', ','], ['', '.'], $valorRaw);

if ($descricao === '' || $categoria === '' || $valor <= 0) {
    echo json_encode(['success'=>false,'message'=>'Descrição, categoria e valor são obrigatórios.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) $data = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();
$st = $pdo->prepare("
    INSERT INTO lancamentos_financeiros (competencia, data, tipo, categoria, descricao, valor, origem, observacao)
    VALUES (?, ?, ?, ?, ?, ?, 'manual', ?)
");
$st->execute([$competencia, $data, $tipo, $categoria, $descricao, $valor, $observacao ?: null]);
echo json_encode(['success'=>true, 'id'=>(int)$pdo->lastInsertId()]);
