<?php

/**
 * Exclui um pedido de uniforme feito errado.
 *
 * "Excluir" aqui é cancelar, não apagar a linha: o pedido some das telas (fila de produção
 * e Pagamentos Uniformes, que só olham pedidos pagos), o número volta a ficar livre pra
 * turma, e o registro continua no banco pra quem precisar auditar o que aconteceu — um
 * pedido que teve dinheiro envolvido não pode evaporar sem deixar rastro.
 *
 * Reversível por SQL, se for o caso: basta devolver status_pagamento para 'pago'.
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

if ($pedidoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Pedido inválido.']);
    exit;
}

$st = $pdo->prepare("
    SELECT id, turma_id, genero, numero, status_pagamento, status_pedido
    FROM pedidos_uniforme WHERE id = ?
");
$st->execute([$pedidoId]);
$pedido = $st->fetch();

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    exit;
}

if ($pedido['status_pagamento'] === 'cancelado') {
    echo json_encode(['success' => true, 'message' => 'Esse pedido já estava excluído.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE pedidos_uniforme
        SET status_pagamento = 'cancelado', atualizado_em = NOW()
        WHERE id = ?
    ")->execute([$pedidoId]);

    // O número deixou o balde: se ele estava marcado como duplicado, quem ficou com ele
    // não está mais em conflito.
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
    error_log('[uniforme-excluir] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir o pedido.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Pedido excluído. O número voltou a ficar livre na turma.',
]);
