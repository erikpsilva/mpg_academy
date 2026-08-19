<?php

/**
 * Manda de uma vez todos os pedidos pendentes para confecção.
 *
 * É o passo que acompanha a lista impressa: o admin gera o PDF, envia pro fornecedor e
 * marca tudo como enviado. Fazer isso pedido a pedido com 26 linhas é onde erro acontece —
 * alguém pula uma linha e aquele uniforme nunca é produzido.
 *
 * Só mexe em quem está em 'pendente' e com pagamento confirmado. Pedido que já avançou
 * (pronto, finalizado, entregue) não volta pra trás.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e) {
    error_log('[uniforme-enviar-todos] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar: ' . $e->getMessage()]);
});

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (empty($_SESSION['usuario']) || !in_array($nivel, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo = getDbConnection();

$st = $pdo->prepare("
    UPDATE pedidos_uniforme
       SET status_pedido = 'enviado', atualizado_em = NOW()
     WHERE status_pedido = 'pendente'
       AND status_pagamento = 'pago'
");
$st->execute();

$total = $st->rowCount();

error_log(sprintf('[uniforme-enviar-todos] usuario %d enviou %d pedido(s) para confecção',
    (int) ($_SESSION['usuario']['id'] ?? 0), $total));

echo json_encode([
    'success' => true,
    'total'   => $total,
    'message' => $total === 0
        ? 'Nenhum pedido pendente para enviar.'
        : $total . ' pedido' . ($total === 1 ? '' : 's') . ' enviado' . ($total === 1 ? '' : 's') . ' para confecção.',
]);
