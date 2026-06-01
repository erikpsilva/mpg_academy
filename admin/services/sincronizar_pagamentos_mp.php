<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';

$pdo         = getDbConnection();
$accessToken = mpAccessToken($pdo);

// Busca mensalidades com mp_payment_id salvo que não estão pagas
$pendentes = $pdo->query("
    SELECT id, mp_payment_id, referencia, aluno_id
    FROM mensalidades
    WHERE mp_payment_id IS NOT NULL
      AND status != 'pago'
")->fetchAll(PDO::FETCH_ASSOC);

// Busca também mensalidades sem mp_payment_id buscando pelo external_reference no MP
$semId = $pdo->query("
    SELECT id, referencia, aluno_id
    FROM mensalidades
    WHERE mp_payment_id IS NULL
      AND status IN ('atrasado','pendente')
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$atualizadas = 0;

// Helper: consulta MP e atualiza se aprovado
function verificarPagamentoMP(PDO $pdo, string $accessToken, int $mensalidadeId, ?string $mpPaymentId): bool {
    if ($mpPaymentId) {
        // Consulta direta pelo ID
        $ch = curl_init("https://api.mercadopago.com/v1/payments/{$mpPaymentId}");
    } else {
        // Busca pelo external_reference
        $ch = curl_init("https://api.mercadopago.com/v1/payments/search?external_reference={$mensalidadeId}&sort=date_created&criteria=desc&limit=1");
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
        CURLOPT_SSL_VERIFYPEER => !APP_IS_LOCAL,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($resp, true) ?? [];

    // Normaliza: busca direta retorna objeto, search retorna {results:[...]}
    $payment = isset($data['results']) ? ($data['results'][0] ?? null) : $data;

    if (!$payment || ($payment['status'] ?? '') !== 'approved') return false;

    $pdo->prepare("
        UPDATE mensalidades
        SET status = 'pago',
            data_pagamento = COALESCE(data_pagamento, CURDATE()),
            mp_payment_id  = ?,
            atualizado_em  = NOW()
        WHERE id = ? AND status != 'pago'
    ")->execute([$payment['id'] ?? $mpPaymentId, $mensalidadeId]);

    return true;
}

foreach ($pendentes as $m) {
    if (verificarPagamentoMP($pdo, $accessToken, (int)$m['id'], $m['mp_payment_id'])) {
        $atualizadas++;
    }
}

foreach ($semId as $m) {
    if (verificarPagamentoMP($pdo, $accessToken, (int)$m['id'], null)) {
        $atualizadas++;
    }
}

// Atualiza status para 'atrasado' onde vencimento < hoje e ainda está 'pendente'
$pdo->exec("
    UPDATE mensalidades
    SET status = 'atrasado', atualizado_em = NOW()
    WHERE status = 'pendente'
      AND vencimento < CURDATE()
");

echo json_encode([
    'success'     => true,
    'atualizadas' => $atualizadas,
    'mensagem'    => $atualizadas > 0
        ? "{$atualizadas} mensalidade(s) marcada(s) como pagas."
        : 'Nenhum pagamento novo encontrado no Mercado Pago.',
]);
