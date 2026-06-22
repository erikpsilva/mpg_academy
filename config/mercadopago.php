<?php

// ─── Credenciais de Produção ──────────────────────────────────────────────────
define('MP_PUBLIC_KEY_PROD',   'APP_USR-5b1fa14f-4426-4495-8b6b-6b6eb690fa7e');
define('MP_ACCESS_TOKEN_PROD', 'APP_USR-6171189951122609-053113-bbae49b17db317cd8892d8db3c4248f8-131746200');

// ─── Credenciais de Teste ─────────────────────────────────────────────────────
define('MP_PUBLIC_KEY_TEST',   'APP_USR-74177cfa-58ad-461d-a342-dcd0b122887a');
define('MP_ACCESS_TOKEN_TEST', 'APP_USR-3700588200978728-053113-3b92864eb4a9feb78a47e32a145414e0-3260785637');

// ─── Assinatura secreta dos Webhooks (valida que a notificação veio do MP) ────
define('MP_WEBHOOK_SECRET_PROD', '8ece70705c82ef0423d89b0099e6df8bef86804614d1608e22e892078538b658');
define('MP_WEBHOOK_SECRET_TEST', '');

// ─── Helpers ──────────────────────────────────────────────────────────────────

function mpModoTeste(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $st = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'pagamento_modo_teste'");
    $st->execute();
    $row   = $st->fetch();
    $cache = $row && $row['valor'] === '1';
    return $cache;
}

function mpPublicKey(PDO $pdo): string
{
    return mpModoTeste($pdo) ? MP_PUBLIC_KEY_TEST : MP_PUBLIC_KEY_PROD;
}

function mpAccessToken(PDO $pdo): string
{
    return mpModoTeste($pdo) ? MP_ACCESS_TOKEN_TEST : MP_ACCESS_TOKEN_PROD;
}

function mpWebhookSecret(PDO $pdo): string
{
    return mpModoTeste($pdo) ? MP_WEBHOOK_SECRET_TEST : MP_WEBHOOK_SECRET_PROD;
}

/**
 * Valida a assinatura HMAC do webhook do MP (header x-signature), conforme
 * https://www.mercadopago.com.br/developers — garante que a notificação
 * realmente veio do Mercado Pago e não foi forjada por terceiros.
 */
function mpValidarAssinaturaWebhook(string $secret, string $xSignature, string $xRequestId, string $dataId): bool
{
    if ($secret === '' || $xSignature === '') return false;

    $partes = [];
    foreach (explode(',', $xSignature) as $par) {
        $kv = explode('=', trim($par), 2);
        if (count($kv) === 2) $partes[trim($kv[0])] = trim($kv[1]);
    }
    $ts = $partes['ts'] ?? '';
    $v1 = $partes['v1'] ?? '';
    if ($ts === '' || $v1 === '') return false;

    $manifest = 'id:' . strtolower($dataId) . ';request-id:' . $xRequestId . ';ts:' . $ts . ';';
    $hash     = hash_hmac('sha256', $manifest, $secret);

    return hash_equals($hash, strtolower($v1));
}

/**
 * Cria um pagamento via Mercado Pago API v1/payments.
 * Retorna ['http_code' => int, 'body' => array].
 */
function mpCriarPagamento(string $accessToken, array $dados): array
{
    $ch = curl_init('https://api.mercadopago.com/v1/payments');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($dados),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'X-Idempotency-Key: mpg-' . uniqid('', true),
        ],
        // Em produção, SSL é sempre verificado.
        // Em local (XAMPP/Windows) pode falhar sem CA bundle — desativa verificação.
        CURLOPT_SSL_VERIFYPEER => !APP_IS_LOCAL,
        CURLOPT_SSL_VERIFYHOST => APP_IS_LOCAL ? 0 : 2,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => json_decode($resp ?: '{}', true) ?? []];
}

/**
 * Helper genérico para chamadas à API do Mercado Pago (customers/cards/card_tokens).
 * Retorna ['http_code' => int, 'body' => array].
 */
function mpRequest(string $accessToken, string $method, string $path, ?array $body = null): array
{
    $ch = curl_init('https://api.mercadopago.com' . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_SSL_VERIFYPEER => !APP_IS_LOCAL,
        CURLOPT_SSL_VERIFYHOST => APP_IS_LOCAL ? 0 : 2,
        CURLOPT_TIMEOUT        => 30,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => json_decode($resp ?: '{}', true) ?? []];
}

/**
 * Busca um customer do MP pelo e-mail, criando um novo caso não exista.
 * Necessário pra poder anexar cartões salvos e cobrar depois sem o aluno presente.
 */
function mpObterOuCriarCustomer(string $accessToken, string $email): ?string
{
    $busca   = mpRequest($accessToken, 'GET', '/v1/customers/search?email=' . urlencode($email));
    $achados = $busca['body']['results'] ?? [];
    if (!empty($achados[0]['id'])) {
        return $achados[0]['id'];
    }

    $criado = mpRequest($accessToken, 'POST', '/v1/customers', ['email' => $email]);
    return $criado['body']['id'] ?? null;
}

/**
 * Anexa um cartão tokenizado (token gerado no front via Brick) a um customer do MP.
 * Retorna os dados do cartão salvo (id, last_four_digits, payment_method...) ou null em falha.
 */
function mpSalvarCartaoCustomer(string $accessToken, string $customerId, string $cardToken): ?array
{
    $resp = mpRequest($accessToken, 'POST', '/v1/customers/' . urlencode($customerId) . '/cards', ['token' => $cardToken]);
    return !empty($resp['body']['id']) ? $resp['body'] : null;
}

/**
 * Remove um cartão salvo de um customer do MP.
 */
function mpRemoverCartaoCustomer(string $accessToken, string $customerId, string $cardId): bool
{
    $resp = mpRequest($accessToken, 'DELETE', '/v1/customers/' . urlencode($customerId) . '/cards/' . urlencode($cardId));
    return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
}

/**
 * Gera um novo token de pagamento a partir de um cartão já salvo, sem precisar do CVV
 * nem do aluno presente — usado pela cobrança automática recorrente (cron).
 */
function mpGerarTokenCartaoSalvo(string $accessToken, string $cardId, string $customerId): ?string
{
    $resp = mpRequest($accessToken, 'POST', '/v1/card_tokens', [
        'card_id'     => $cardId,
        'customer_id' => $customerId,
    ]);
    return $resp['body']['id'] ?? null;
}

/**
 * Consulta um pagamento direto na API do MP pelo ID. Usado pelo webhook e pela
 * sincronização manual — nunca confiamos no status que vem na notificação, sempre
 * reconsultamos com nosso próprio access token.
 */
function mpConsultarPagamento(string $accessToken, string $paymentId): ?array
{
    $resp = mpRequest($accessToken, 'GET', '/v1/payments/' . urlencode($paymentId));
    return !empty($resp['body']['id']) ? $resp['body'] : null;
}

/**
 * Marca uma mensalidade como paga e lança no financeiro. Idempotente: se já estiver
 * paga, não faz nada (protege contra notificações duplicadas do MP, que reenvia
 * webhooks até receber 200).
 */
function mpMarcarMensalidadePaga(PDO $pdo, int $mensalidadeId, string $mpPaymentId): bool
{
    $st = $pdo->prepare("SELECT id, valor, referencia, aluno_id, status FROM mensalidades WHERE id = ?");
    $st->execute([$mensalidadeId]);
    $mens = $st->fetch();
    if (!$mens || $mens['status'] === 'pago') return false;

    $pdo->prepare("
        UPDATE mensalidades
        SET status = 'pago', data_pagamento = COALESCE(data_pagamento, CURDATE()), mp_payment_id = ?, atualizado_em = NOW()
        WHERE id = ? AND status != 'pago'
    ")->execute([$mpPaymentId, $mensalidadeId]);

    $stAluno = $pdo->prepare("SELECT nome FROM alunos WHERE id = ?");
    $stAluno->execute([$mens['aluno_id']]);
    $alunoNome = $stAluno->fetchColumn() ?: '';

    $competencia = date('Y-m');
    $descLanc    = 'Mensalidade ' . $mens['referencia'] . ' — ' . $alunoNome . ' (via MP)';
    try {
        $pdo->prepare("
            INSERT IGNORE INTO lancamentos_financeiros
                (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
            VALUES (?, CURDATE(), 'receita', 'mensalidade', ?, ?, 'auto', 'mensalidade', ?)
        ")->execute([$competencia, $descLanc, $mens['valor'], $mensalidadeId]);
    } catch (PDOException $e) {}

    return true;
}
