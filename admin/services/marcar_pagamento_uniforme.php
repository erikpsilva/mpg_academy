<?php

/**
 * Marca um pedido de uniforme como pago ou de volta como não pago.
 *
 * Pedido lançado pelo admin nasce aguardando pagamento: a pessoa combinou o uniforme, mas o
 * dinheiro entra depois (PIX na mão, dinheiro, link externo). Quando entra, é aqui que o
 * pedido vira pago — e passa a contar em Pagamentos Uniformes, que é a tela do que entrou
 * em caixa.
 *
 * Só mexe em pedido lançado pelo admin. Pedido que veio do site com pagamento confirmado
 * pelo Mercado Pago não se desmarca por aqui: ali existe transação de verdade, e "desfazer"
 * seria inventar um estado que não corresponde ao dinheiro.
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

$pdo      = getDbConnection();
$pedidoId = (int) ($_POST['pedido_id'] ?? 0);
$pago     = ($_POST['pago'] ?? '') === '1';

if ($pedidoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Pedido inválido.']);
    exit;
}

$st = $pdo->prepare("
    SELECT id, status_pagamento, mp_payment_id, criado_por_usuario_id, turma_id, genero, numero
    FROM pedidos_uniforme WHERE id = ?
");
$st->execute([$pedidoId]);
$pedido = $st->fetch();

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    exit;
}

// Pagamento do Mercado Pago é fato consumado: o dinheiro está lá.
if (!$pago && !empty($pedido['mp_payment_id'])) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'Esse pedido foi pago pelo site (Mercado Pago) — não dá pra marcar como não pago.',
    ]);
    exit;
}

if (!in_array($pedido['status_pagamento'], ['pago', 'aguardando'], true)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Esse pedido está cancelado ou expirado.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // `reserva_expira_em` continua nulo nos dois sentidos: pedido do admin não tem janela de
    // 30 minutos pra expirar, então voltar pra "não pago" não faz o número evaporar.
    $pdo->prepare("
        UPDATE pedidos_uniforme
        SET status_pagamento = ?, pago_em = ?, reserva_expira_em = NULL, atualizado_em = NOW()
        WHERE id = ?
    ")->execute([
        $pago ? 'pago' : 'aguardando',
        $pago ? date('Y-m-d H:i:s') : null,
        $pedidoId,
    ]);

    // O alerta de número duplicado olha só pedidos pagos: mudar o status pode criar ou
    // desfazer um conflito.
    if ($pedido['turma_id'] !== null && $pedido['numero'] !== null) {
        uniformeRecalcularConflito(
            $pdo,
            (int) $pedido['turma_id'],
            (string) $pedido['genero'],
            (int) $pedido['numero']
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[uniforme-pagamento] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao mudar o status de pagamento.']);
    exit;
}

echo json_encode([
    'success' => true,
    'pago'    => $pago,
    'message' => $pago ? 'Pedido marcado como pago.' : 'Pedido voltou para não pago.',
]);
