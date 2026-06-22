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

if (empty($_SESSION['aluno'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$cardToken = trim($input['token'] ?? '');

if (empty($cardToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados do cartão inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';

$pdo     = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];

$st = $pdo->prepare("SELECT id, nome, email, mp_customer_id FROM alunos WHERE id = ?");
$st->execute([$alunoId]);
$aluno = $st->fetch();

if (!$aluno) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}

$accessToken = mpAccessToken($pdo);

$customerId = $aluno['mp_customer_id'];
if (empty($customerId)) {
    $customerId = mpObterOuCriarCustomer($accessToken, $aluno['email']);
}

if (empty($customerId)) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Não foi possível registrar o cliente no Mercado Pago.']);
    exit;
}

$cartao = mpSalvarCartaoCustomer($accessToken, $customerId, $cardToken);

if (!$cartao) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Não foi possível salvar o cartão. Verifique os dados e tente novamente.']);
    exit;
}

$bandeira = $cartao['payment_method']['id'] ?? ($cartao['payment_method_id'] ?? '');
$final4   = $cartao['last_four_digits'] ?? '';

$pdo->prepare("
    UPDATE alunos
    SET mp_customer_id = ?, mp_card_id = ?, cartao_bandeira = ?, cartao_final4 = ?, auto_pagamento = 1
    WHERE id = ?
")->execute([$customerId, $cartao['id'], $bandeira, $final4, $alunoId]);

echo json_encode([
    'success'  => true,
    'message'  => 'Cartão salvo com sucesso! O pagamento automático foi ativado.',
    'bandeira' => $bandeira,
    'final4'   => $final4,
]);
