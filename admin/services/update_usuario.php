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

if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$id           = (int) ($_POST['id']           ?? 0);
$nomeCompleto = trim($_POST['nome_completo']   ?? '');
$email        = trim($_POST['email']           ?? '');
$nivel        = trim($_POST['nivel_acesso']    ?? '');
$novaSenha    = $_POST['nova_senha']           ?? '';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

if (mb_strlen($nomeCompleto) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome deve ter no mínimo 3 caracteres.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

if (!in_array($nivel, ['admin', 'editor', 'leitor'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nível de acesso inválido.']);
    exit;
}

$pdo = getDbConnection();

// Check email unique (excluding self)
$check = $pdo->prepare("SELECT id FROM admin_usuarios WHERE email = ? AND id != ? LIMIT 1");
$check->execute([$email, $id]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'E-mail já em uso por outro usuário.']);
    exit;
}

if ($novaSenha !== '') {
    if (strlen($novaSenha) < 6 || strlen($novaSenha) > 20) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nova senha deve ter entre 6 e 20 caracteres.']);
        exit;
    }
    $senhaHash = password_hash($novaSenha, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("
        UPDATE admin_usuarios SET nome_completo = ?, email = ?, nivel_acesso = ?, senha = ?
        WHERE id = ?
    ");
    $stmt->execute([$nomeCompleto, $email, $nivel, $senhaHash, $id]);
} else {
    $stmt = $pdo->prepare("
        UPDATE admin_usuarios SET nome_completo = ?, email = ?, nivel_acesso = ?
        WHERE id = ?
    ");
    $stmt->execute([$nomeCompleto, $email, $nivel, $id]);
}

// Refresh session if editing self
if ((int) $_SESSION['usuario']['id'] === $id) {
    $_SESSION['usuario']['nome_completo'] = $nomeCompleto;
    $_SESSION['usuario']['email']         = $email;
    $_SESSION['usuario']['nivel_acesso']  = $nivel;
}

echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso.']);
