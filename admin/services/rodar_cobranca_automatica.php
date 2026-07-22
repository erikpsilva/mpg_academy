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

if (empty($_SESSION['usuario']) || ($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';

$pdo = getDbConnection();
$res = mpExecutarCobrancaAutomatica($pdo);

if ($res['sucesso'] === 0 && $res['falha'] === 0) {
    $msg = 'Nenhuma fatura elegível pra cobrar agora.';
} else {
    $msg = "{$res['sucesso']} cobrança(s) com sucesso";
    if ($res['falha'] > 0) $msg .= ", {$res['falha']} falha(s)";
    $msg .= '.';

    // Mostra o motivo de cada falha direto na tela — sem precisar consultar o banco.
    foreach ($res['detalhes_falha'] as $d) {
        $msg .= "\n\n❌ {$d['aluno']}: {$d['motivo']}";
    }
}

echo json_encode([
    'success'        => true,
    'sucesso'        => $res['sucesso'],
    'falha'          => $res['falha'],
    'detalhes_falha' => $res['detalhes_falha'],
    'message'        => $msg,
]);
