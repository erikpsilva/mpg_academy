<?php

// ─── Credenciais de Produção ──────────────────────────────────────────────────
define('MP_PUBLIC_KEY_PROD',   'APP_USR-5b1fa14f-4426-4495-8b6b-6b6eb690fa7e');
define('MP_ACCESS_TOKEN_PROD', 'APP_USR-6171189951122609-053113-bbae49b17db317cd8892d8db3c4248f8-131746200');

// ─── Credenciais de Teste ─────────────────────────────────────────────────────
define('MP_PUBLIC_KEY_TEST',   'APP_USR-74177cfa-58ad-461d-a342-dcd0b122887a');
define('MP_ACCESS_TOKEN_TEST', 'APP_USR-3700588200978728-053113-3b92864eb4a9feb78a47e32a145414e0-3260785637');

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
