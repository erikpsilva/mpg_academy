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

require_once dirname(__FILE__, 3) . '/config/database.php';

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if (empty($email) || empty($senha)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail e senha são obrigatórios.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

$pdo = getDbConnection();

// ── 1. Tenta admin_usuarios primeiro ─────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id, nome_completo, email, cpf, nivel_acesso, senha FROM admin_usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if ($usuario && password_verify($senha, $usuario['senha'])) {
    unset($usuario['senha']);
    $_SESSION['usuario'] = $usuario;
    echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso.', 'data' => $usuario]);
    exit;
}

// ── 2. Tenta tabela professores ───────────────────────────────────────────────
$stProf = $pdo->prepare("SELECT id, nome, sobrenome, email, cpf, celular, dia_pagamento, valor_aula_90min, valor_aula_120min, status, senha FROM professores WHERE email = ? LIMIT 1");
$stProf->execute([$email]);
$prof = $stProf->fetch();

if ($prof && password_verify($senha, $prof['senha'])) {
    if ($prof['status'] !== 'ativo') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Conta de professor inativa.']);
        exit;
    }
    $_SESSION['usuario'] = [
        'id'           => $prof['id'],
        'nome_completo'=> $prof['nome'] . ' ' . $prof['sobrenome'],
        'email'        => $prof['email'],
        'cpf'          => $prof['cpf'],
        'nivel_acesso' => 'professor',
        'professor_id' => $prof['id'],
    ];
    echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso.', 'data' => $_SESSION['usuario']]);
    exit;
}

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos.']);
