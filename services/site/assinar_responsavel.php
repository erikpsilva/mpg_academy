<?php
header('Content-Type: application/json');
require_once dirname(__FILE__, 3) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$token = trim($_POST['token'] ?? '');
$nome  = trim($_POST['nome']  ?? '');
$cpf   = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));

if (!$token || !$nome || strlen($cpf) < 11) {
    echo json_encode(['success' => false, 'message' => 'Preencha nome e CPF corretamente.']);
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT id, assinado_responsavel_em FROM termo_assinaturas WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$termo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$termo) {
    echo json_encode(['success' => false, 'message' => 'Link inválido ou expirado.']);
    exit;
}
if ($termo['assinado_responsavel_em']) {
    echo json_encode(['success' => false, 'message' => 'Este termo já foi assinado pelo responsável.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$pdo->prepare("
    UPDATE termo_assinaturas
    SET responsavel_nome_assinado = ?, responsavel_cpf_assinado = ?,
        assinado_responsavel_em = NOW(), assinado_responsavel_ip = ?
    WHERE id = ?
")->execute([$nome, $cpf, $ip, $termo['id']]);

echo json_encode(['success' => true]);
