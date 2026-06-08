<?php
if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__, 2));
    require_once ROOT . '/config/app.php';
}
require_once ROOT . '/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$token = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
$nome  = trim($_POST['nome'] ?? '');
$cpf   = preg_replace('/\D/', '', $_POST['cpf'] ?? '');

if (!$token || !$nome || strlen($cpf) !== 11) {
    echo json_encode(['success' => false, 'message' => 'Preencha nome e CPF corretamente.']);
    exit;
}

$pdo = getDbConnection();

$st = $pdo->prepare("SELECT id, assinado_em FROM professor_contratos WHERE token = ? LIMIT 1");
$st->execute([$token]);
$contrato = $st->fetch(PDO::FETCH_ASSOC);

if (!$contrato) {
    echo json_encode(['success' => false, 'message' => 'Link inválido ou expirado.']);
    exit;
}
if (!empty($contrato['assinado_em'])) {
    echo json_encode(['success' => false, 'message' => 'Este contrato já foi assinado.']);
    exit;
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

$pdo->prepare("
    UPDATE professor_contratos
    SET assinado_nome = ?, assinado_cpf = ?, assinado_em = NOW(), assinado_ip = ?
    WHERE id = ?
")->execute([$nome, $cpf, $ip, $contrato['id']]);

echo json_encode(['success' => true]);
