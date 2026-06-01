<?php
/**
 * Endpoint de autenticação mobile — retorna Bearer token.
 * Aceita JSON: { "email": "...", "senha": "..." }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Método não permitido.']); exit; }

require_once dirname(__FILE__, 3) . '/config/database.php';

$body = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($body['email'] ?? ''));
$senha = trim($body['senha'] ?? '');

if (!$email || !$senha) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'E-mail e senha são obrigatórios.']);
    exit;
}

$pdo = getDbConnection();

// Cria tabela de tokens se não existir
$pdo->exec("
    CREATE TABLE IF NOT EXISTS mobile_tokens (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        aluno_id  INT NOT NULL,
        token     VARCHAR(64) NOT NULL UNIQUE,
        expire_at DATETIME NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token   (token),
        INDEX idx_aluno   (aluno_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$stmt = $pdo->prepare("SELECT * FROM alunos WHERE email = ? AND status = 'ativo' LIMIT 1");
$stmt->execute([$email]);
$aluno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aluno || !password_verify($senha, $aluno['senha'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'E-mail ou senha inválidos.']);
    exit;
}

// Gera token e salva
$token     = bin2hex(random_bytes(32));
$expireAt  = date('Y-m-d H:i:s', strtotime('+30 days'));

$pdo->prepare("DELETE FROM mobile_tokens WHERE aluno_id = ? AND expire_at < NOW()")->execute([$aluno['id']]);
$pdo->prepare("INSERT INTO mobile_tokens (aluno_id, token, expire_at) VALUES (?, ?, ?)")->execute([$aluno['id'], $token, $expireAt]);

unset($aluno['senha']);

echo json_encode(['success'=>true, 'token'=>$token, 'aluno'=>$aluno]);
