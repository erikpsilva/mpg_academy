<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] === 'professor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$contratoId = (int) ($_POST['contrato_id'] ?? 0);
$nome       = trim($_POST['nome'] ?? '');
$cpf        = preg_replace('/\D/', '', $_POST['cpf'] ?? '');

if (!$contratoId || strlen($nome) < 3) {
    echo json_encode(['success' => false, 'message' => 'Informe o nome completo do signatário.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$st = $pdo->prepare("
    UPDATE professor_contratos
    SET assinado_empresa_nome = ?,
        assinado_empresa_cpf  = ?,
        assinado_empresa_em   = NOW(),
        assinado_empresa_ip   = ?
    WHERE id = ? AND assinado_empresa_em IS NULL
");
$st->execute([$nome, $cpf ?: null, $ip, $contratoId]);

if ($st->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Contrato não encontrado ou já assinado pela empresa.']);
    exit;
}

echo json_encode(['success' => true]);
