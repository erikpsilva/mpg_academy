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

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';

$pdo     = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];

$st = $pdo->prepare("SELECT mp_customer_id, mp_card_id FROM alunos WHERE id = ?");
$st->execute([$alunoId]);
$aluno = $st->fetch();

if ($aluno && !empty($aluno['mp_customer_id']) && !empty($aluno['mp_card_id'])) {
    $accessToken = mpAccessToken($pdo);
    mpRemoverCartaoCustomer($accessToken, $aluno['mp_customer_id'], $aluno['mp_card_id']);
}

$pdo->prepare("
    UPDATE alunos
    SET mp_card_id = NULL, cartao_bandeira = NULL, cartao_final4 = NULL, auto_pagamento = 0
    WHERE id = ?
")->execute([$alunoId]);

echo json_encode(['success' => true, 'message' => 'Pagamento automático desativado.']);
