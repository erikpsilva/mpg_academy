<?php

header('Content-Type: application/json');

require_once dirname(__FILE__, 2) . '/../config/app.php';

if (!APP_IS_LOCAL) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Teste de e-mail permitido apenas em ambiente local.']);
    exit;
}

require_once __DIR__ . '/email_template.php';

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$nome  = trim($_GET['nome'] ?? $_POST['nome'] ?? 'Teste MPG Academy');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Informe um e-mail válido. Exemplo: /services/site/test_email.php?email=seu@email.com',
    ]);
    exit;
}

if (isset($_GET['preview'])) {
    header('Content-Type: text/html; charset=UTF-8');
    echo buildMpgSignupEmail($nome);
    exit;
}

$sent = sendMpgSignupConfirmation($email, $nome);

echo json_encode([
    'success' => $sent,
    'message' => $sent
        ? 'E-mail de teste enviado com sucesso (salvo em storage/emails_teste/ no ambiente local).'
        : 'Falha ao enviar. Verifique as configurações SMTP em config/mail.php ou o php.ini do XAMPP.',
]);
