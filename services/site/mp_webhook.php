<?php
/**
 * Webhook do Mercado Pago — endpoint público, chamado pelo servidor do MP
 * (não pelo navegador do aluno), por isso NÃO passa por validateApiAccess()
 * (que valida Origin/Referer de chamadas de browser, MP não envia esses headers).
 *
 * Segurança: nunca confiamos no corpo da notificação. Ao receber um aviso de
 * "payment", reconsultamos o pagamento direto na API do MP com nosso próprio
 * access token antes de marcar qualquer coisa como paga.
 */

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';
require_once dirname(__FILE__, 3) . '/config/app.php';

$assinaturaValida = null; // null = não verificada (sem segredo configurado pra esse modo)

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $tipo      = $input['type'] ?? $input['topic'] ?? ($_GET['type'] ?? $_GET['topic'] ?? '');
    $dataIdGet = $_GET['data.id'] ?? $_GET['id'] ?? '';
    $paymentId = $input['data']['id'] ?? ($dataIdGet ?: null);

    if ($tipo === 'payment' && !empty($paymentId)) {
        $pdo         = getDbConnection();
        $accessToken = mpAccessToken($pdo);
        $secret      = mpWebhookSecret($pdo);

        // Se houver segredo configurado pro modo atual (teste/produção), exige assinatura válida.
        if ($secret !== '') {
            $xSignature  = $_SERVER['HTTP_X_SIGNATURE']   ?? '';
            $xRequestId  = $_SERVER['HTTP_X_REQUEST_ID']  ?? '';
            $assinaturaValida = mpValidarAssinaturaWebhook($secret, $xSignature, $xRequestId, (string) $dataIdGet);
        }

        if ($assinaturaValida !== false) {
            $payment = mpConsultarPagamento($accessToken, (string) $paymentId);

            if ($payment && ($payment['status'] ?? '') === 'approved') {
                $mensalidadeId = (int) ($payment['metadata']['mensalidade_id'] ?? 0);
                if ($mensalidadeId > 0) {
                    mpMarcarMensalidadePaga($pdo, $mensalidadeId, (string) $payment['id']);
                }
            }
        } else {
            error_log('[mp_webhook] Assinatura inválida — notificação ignorada (payment_id=' . $paymentId . ')');
        }
    }
} catch (Throwable $e) {
    error_log('[mp_webhook] ' . $e->getMessage());
}

if ($assinaturaValida === false) {
    http_response_code(401);
    echo json_encode(['received' => false, 'message' => 'Assinatura inválida.']);
    exit;
}

// Sempre confirma recebimento com 200, mesmo se não era relevante ou deu erro —
// senão o MP fica reenviando a mesma notificação indefinidamente.
http_response_code(200);
echo json_encode(['received' => true]);
