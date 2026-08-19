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

$input  = json_decode(file_get_contents('php://input'), true);
$ativar = !empty($input['ativar']);

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';

// Cobrança automática desligada (ver MP_COBRANCA_AUTOMATICA_ATIVA em config/mercadopago.php).
// O endpoint continua existindo pra não quebrar chamada antiga, mas não faz mais nada.
if (!MP_COBRANCA_AUTOMATICA_ATIVA) {
    http_response_code(410);
    echo json_encode(['success' => false, 'message' => 'O pagamento automático foi desativado.']);
    exit;
}


$pdo     = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];

$st = $pdo->prepare("SELECT mp_card_id FROM alunos WHERE id = ?");
$st->execute([$alunoId]);
$aluno = $st->fetch();

if ($ativar && empty($aluno['mp_card_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cadastre um cartão antes de ativar o pagamento automático.']);
    exit;
}

$pdo->prepare("UPDATE alunos SET auto_pagamento = ? WHERE id = ?")->execute([$ativar ? 1 : 0, $alunoId]);

echo json_encode(['success' => true, 'auto_pagamento' => $ativar]);
