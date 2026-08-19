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
require_once dirname(__FILE__, 3) . '/config/batebola.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';
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
        //
        // O manifest do HMAC usa o ID do pagamento — e ele precisa ser o ID REALMENTE recebido,
        // não só o da query string. O MP nem sempre manda `data.id` na URL: quando a notificação
        // vem só com o id no corpo JSON, $dataIdGet fica vazio e o manifest sai como "id:;...",
        // que nunca bate. Era o que fazia notificações legítimas serem descartadas — pagamento
        // aprovado no MP e fatura seguia pendente no sistema.
        if ($secret !== '') {
            $xSignature  = $_SERVER['HTTP_X_SIGNATURE']   ?? '';
            $xRequestId  = $_SERVER['HTTP_X_REQUEST_ID']  ?? '';
            $assinaturaValida = mpValidarAssinaturaWebhook($secret, $xSignature, $xRequestId, (string) $paymentId);
        }

        if ($assinaturaValida !== false) {
            // A baixa em si vive em mpConfirmarPagamentoAprovado() (config/mercadopago.php),
            // compartilhada com o retorno do navegador — as duas redes usam exatamente a
            // mesma regra, e chamar as duas é inofensivo (a função é idempotente).
            $payment = mpConsultarPagamento($accessToken, (string) $paymentId);
            mpConfirmarPagamentoAprovado($pdo, $payment);
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
