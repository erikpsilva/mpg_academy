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

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mensalidades.php';

// Modelo pré-pago: fatura do mês atual, vencendo dia 10. Isso é só o fallback que garante que
// todo aluno tenha fatura pro mês em que já estamos — o disparo normal acontece assim que a
// fatura anterior é paga (ver gerarMensalidadeRecorrente() chamado a partir de
// mpMarcarMensalidadePaga() e update_mensalidade_status.php), pra nunca ter duas faturas em
// aberto simultâneas sem necessidade.
$pdo = getDbConnection();
$res = gerarMensalidadesMesAtual($pdo);

echo json_encode([
    'success'    => true,
    'referencia' => $res['referencia'],
    'vencimento' => (new DateTime($res['referencia'] . '-10'))->format('d/m/Y'),
    'geradas'    => $res['geradas'],
]);
