<?php

/**
 * Avança (ou corrige) o status de produção de um pedido de uniforme:
 * pendente → enviado → pronto → finalizado → entregue.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

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

$pedidoId = (int) ($_POST['pedido_id'] ?? 0);
$status   = trim($_POST['status'] ?? '');

if ($pedidoId <= 0 || !in_array($status, UNIFORME_STATUS_FLUXO, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$pdo = getDbConnection();

$st = $pdo->prepare("SELECT id, status_pagamento FROM pedidos_uniforme WHERE id = ?");
$st->execute([$pedidoId]);
$pedido = $st->fetch();

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    exit;
}

if ($pedido['status_pagamento'] !== 'pago') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Só é possível mover pedidos com pagamento confirmado.']);
    exit;
}

$pdo->prepare("UPDATE pedidos_uniforme SET status_pedido = ? WHERE id = ?")
    ->execute([$status, $pedidoId]);

echo json_encode([
    'success'      => true,
    'status'       => $status,
    'status_label' => UNIFORME_STATUS_LABEL[$status] ?? $status,
]);
