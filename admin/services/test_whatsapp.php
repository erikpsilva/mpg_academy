<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$phone   = trim($_POST['phone']   ?? '');
$message = trim($_POST['message'] ?? 'Teste MPG Academy - ' . date('H:i:s'));

if (!$phone) {
    echo json_encode(['success' => false, 'message' => 'Informe o celular.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/services/whatsapp/zapi.php';

$digits = formatPhoneZapi($phone);

$payload = json_encode(['phone' => $digits, 'message' => $message]);

$ch = curl_init(ZAPI_BASE . '/send-text');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Client-Token: ' . ZAPI_CLIENT_TOKEN],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$resp    = curl_exec($ch);
$code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo json_encode([
    'phone_enviado' => $digits,
    'token_usado'   => ZAPI_TOKEN,
    'http_code'     => $code,
    'curl_error'    => $curlErr ?: null,
    'zapi_response' => json_decode($resp, true) ?? $resp,
]);
