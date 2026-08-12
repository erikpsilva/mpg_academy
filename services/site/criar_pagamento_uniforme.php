<?php

/**
 * Pagamento do pedido de uniforme — cartão (Brick) ou PIX, mesmo padrão de
 * criar_pagamento.php (mensalidade). O pedido só é confirmado quando o pagamento é
 * aprovado: no cartão, aqui mesmo; no PIX, pelo webhook (metadata.pedido_uniforme_id).
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Ver comentário em criar_pagamento.php: qualquer saída fora do JSON quebra o parse do
// Brick e trava a barra de carregamento sem mostrar erro nenhum ao aluno.
@ini_set('display_errors', '0');

$respostaErroServidor = function (string $tag, string $detalhe) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    error_log('[' . $tag . '] ' . $detalhe);
    echo json_encode([
        'success' => false,
        'status'  => 'erro_servidor',
        'message' => 'Erro interno ao processar o pagamento. Tente novamente ou use o PIX.',
    ]);
};

register_shutdown_function(function () use ($respostaErroServidor) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $respostaErroServidor('uniforme-pagamento-fatal', $e['message'] . ' em ' . $e['file'] . ':' . $e['line']);
    }
});

set_exception_handler(function (Throwable $ex) use ($respostaErroServidor) {
    $respostaErroServidor('uniforme-pagamento-exception', $ex->getMessage());
});

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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$pedidoId = (int) ($input['pedido_id'] ?? 0);
$isPix    = ($input['payment_method_id'] ?? '') === 'pix';
$token    = trim($input['token'] ?? '');

if ($pedidoId <= 0 || (!$isPix && empty($token))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados insuficientes.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo     = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];

uniformeExpirarReservas($pdo);

$stPedido = $pdo->prepare("
    SELECT p.id, p.genero, p.modelo, p.nome_camisa, p.numero, p.tamanho_camisa, p.tamanho_shorts, p.valor,
           p.status_pagamento, p.pix_qr_code, p.pix_qr_code_base64,
           a.email AS aluno_email, a.nome AS aluno_nome, a.cpf AS aluno_cpf
    FROM pedidos_uniforme p
    JOIN alunos a ON a.id = p.aluno_id
    WHERE p.id = ? AND p.aluno_id = ?
");
$stPedido->execute([$pedidoId, $alunoId]);
$pedido = $stPedido->fetch();

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    exit;
}

if ($pedido['status_pagamento'] === 'pago') {
    echo json_encode(['success' => true, 'status' => 'approved', 'ja_pago' => true]);
    exit;
}

if ($pedido['status_pagamento'] !== 'aguardando') {
    http_response_code(409);
    echo json_encode([
        'success'  => false,
        'expirado' => true,
        'message'  => 'A reserva do número expirou. Refaça o pedido para escolher o número novamente.',
    ]);
    exit;
}

$total       = (float) $pedido['valor'];
$descricao   = 'MPG Academy — Uniforme ' . $pedido['nome_camisa'] . ' #' . $pedido['numero'];
$accessToken = mpAccessToken($pdo);

// PIX já gerado pra esse pedido: reaproveita o QR em vez de criar outra cobrança.
if ($isPix && !empty($pedido['pix_qr_code'])) {
    echo json_encode([
        'success'        => true,
        'status'         => 'pix_pending',
        'qr_code'        => $pedido['pix_qr_code'],
        'qr_code_base64' => $pedido['pix_qr_code_base64'],
        'valor_pago'     => $total,
    ]);
    exit;
}

if ($isPix) {
    $paymentData = [
        'transaction_amount' => $total,
        'payment_method_id'  => 'pix',
        'description'        => $descricao,
        'payer'              => ['email' => $pedido['aluno_email']],
        'metadata'           => ['pedido_uniforme_id' => $pedidoId],
        'external_reference' => 'uniforme-' . $pedidoId,
    ];
} else {
    $paymentData = [
        'transaction_amount' => $total,
        'token'              => $token,
        'description'        => $descricao,
        'installments'       => (int) ($input['installments'] ?? 1),
        'payment_method_id'  => $input['payment_method_id'] ?? '',
        'payer'              => [
            'email'          => $input['payer']['email'] ?? $pedido['aluno_email'],
            'identification' => [
                'type'   => $input['payer']['identification']['type']   ?? 'CPF',
                'number' => $input['payer']['identification']['number'] ?? preg_replace('/\D/', '', $pedido['aluno_cpf'] ?? ''),
            ],
        ],
        'metadata'           => ['pedido_uniforme_id' => $pedidoId],
        'external_reference' => 'uniforme-' . $pedidoId,
    ];
    if (!empty($input['issuer_id'])) {
        $paymentData['issuer_id'] = (int) $input['issuer_id'];
    }
}

$result      = mpCriarPagamento($accessToken, $paymentData);
$body        = $result['body'];
$status      = $body['status'] ?? '';
$mpPaymentId = $body['id'] ?? null;

if (!in_array($status, ['approved', 'pending', 'in_process'], true)) {
    $statusDetail = $body['status_detail'] ?? null;
    $detail       = $statusDetail ?? ($body['message'] ?? 'Pagamento recusado.');

    mpRegistrarErroPagamento($pdo, [
        'aluno_id'         => $alunoId,
        'aluno_nome'       => $pedido['aluno_nome']  ?? null,
        'aluno_email'      => $pedido['aluno_email'] ?? null,
        'contexto'         => 'uniforme',
        'referencia_id'    => $pedidoId,
        'referencia_label' => $pedido['nome_camisa'] . ' #' . $pedido['numero']
                              . ' (camisa ' . $pedido['tamanho_camisa'] . ' / shorts ' . $pedido['tamanho_shorts'] . ')',
        'valor'            => $total,
        'metodo'           => $isPix ? 'pix' : ($input['payment_method_id'] ?? 'cartao'),
        'parcelas'         => $isPix ? null : (int) ($input['installments'] ?? 1),
        'origem'           => 'site',
        'mp_payment_id'    => $mpPaymentId,
        'mp_status'        => $status ?: 'rejected',
        'mp_status_detail' => $statusDetail,
        'http_code'        => $result['http_code'] ?? null,
        'mensagem'         => mpMotivoPt($statusDetail, $body['message'] ?? null),
        'detalhe_tecnico'  => mpExtrairErroApi($body, $statusDetail),
    ]);

    echo json_encode(['success' => false, 'status' => $status ?: 'rejected', 'message' => $detail]);
    exit;
}

if ($mpPaymentId) {
    try {
        $pdo->prepare("UPDATE pedidos_uniforme SET mp_payment_id = ? WHERE id = ?")
            ->execute([$mpPaymentId, $pedidoId]);
    } catch (PDOException $e) {}
}

if ($status === 'approved') {
    uniformeConfirmarPedido($pdo, $pedidoId, (string) $mpPaymentId, $body);

    echo json_encode([
        'success'    => true,
        'status'     => 'approved',
        'payment_id' => $mpPaymentId,
        'valor_pago' => $total,
    ]);
    exit;
}

if ($isPix) {
    $txData    = $body['point_of_interaction']['transaction_data'] ?? [];
    $qrCode    = $txData['qr_code']        ?? '';
    $qrCodeB64 = $txData['qr_code_base64'] ?? '';

    // Guarda o QR pra reaproveitar se o aluno recarregar a página antes de pagar.
    try {
        $pdo->prepare("UPDATE pedidos_uniforme SET pix_qr_code = ?, pix_qr_code_base64 = ? WHERE id = ?")
            ->execute([$qrCode, $qrCodeB64, $pedidoId]);
    } catch (PDOException $e) {}

    echo json_encode([
        'success'        => true,
        'status'         => 'pix_pending',
        'payment_id'     => $mpPaymentId,
        'qr_code'        => $qrCode,
        'qr_code_base64' => $qrCodeB64,
        'valor_pago'     => $total,
    ]);
    exit;
}

echo json_encode([
    'success'    => true,
    'status'     => $status,
    'payment_id' => $mpPaymentId,
    'valor_pago' => $total,
]);
