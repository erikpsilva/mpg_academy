<?php

header('Content-Type: application/json');

require_once dirname(__FILE__, 2) . '/../config/api_security.php';
require_once __DIR__ . '/email_template.php';

validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

require_once dirname(__FILE__, 2) . '/../config/database.php';

$nome    = trim($_POST['nome'] ?? '');
$email   = trim($_POST['email'] ?? '');
$celular = preg_replace('/[^\d]/', '', $_POST['celular'] ?? '');

if (mb_strlen($nome) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe um nome com pelo menos 3 caracteres.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe um e-mail válido.']);
    exit;
}

if (strlen($celular) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe um celular válido com DDD.']);
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT id FROM site_interessados WHERE email = ? LIMIT 1');
$stmt->execute([$email]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado na lista de interesse.']);
    exit;
}

$stmt = $pdo->prepare('
    INSERT INTO site_interessados (nome_completo, email, celular)
    VALUES (?, ?, ?)
');
$stmt->execute([$nome, $email, $celular]);

require_once dirname(__FILE__, 2) . '/../config/avisos.php';

// Com o aviso desligado o cadastro segue normal, só não sai o e-mail — e a mensagem na tela
// não pode prometer um e-mail que não vai chegar, nem falar em falha que não houve.
$avisoLigado = avisoAtivo($pdo, 'aviso_interesse_email');
$emailSent   = $avisoLigado ? sendMpgSignupConfirmation($email, $nome) : false;

http_response_code(201);
echo json_encode([
    'success' => true,
    'message' => !$avisoLigado
        ? 'Cadastro realizado com sucesso! Em breve entraremos em contato.'
        : ($emailSent
            ? 'Cadastro realizado com sucesso! Enviamos uma confirmação para o seu e-mail.'
            : 'Cadastro realizado com sucesso! Não foi possível enviar o e-mail de confirmação agora.'),
    'email_sent' => $emailSent,
]);
