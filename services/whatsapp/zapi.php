<?php

// Z-API credentials
define('ZAPI_INSTANCE',     '3F440D9CACDF51785B317A94F00847F6');
define('ZAPI_TOKEN',        '5E43617F1326762958BEE1D6');
define('ZAPI_CLIENT_TOKEN', 'F457c946dd7db4b0e81d6297315f7f5c7S');
define('ZAPI_BASE',         'https://api.z-api.io/instances/' . ZAPI_INSTANCE . '/token/' . ZAPI_TOKEN);

/**
 * Envia mensagem de texto via Z-API.
 * Em ambiente local, salva em arquivo em vez de enviar.
 *
 * @param string $phone  Número com DDI+DDD+número, só dígitos. Ex: "5511999999999"
 * @param string $message Texto da mensagem
 * @return bool
 */
function sendWhatsApp(string $phone, string $message): bool {
    // Sanitiza: só dígitos
    $phone = preg_replace('/\D/', '', $phone);

    // Garante DDI 55 (Brasil)
    if (substr($phone, 0, 2) !== '55') {
        $phone = '55' . $phone;
    }

    // Em local, salva arquivo em vez de enviar
    if (appIsLocal()) {
        $dir = dirname(__FILE__, 3) . '/storage/whatsapp_teste';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = $dir . '/' . date('Y-m-d_H-i-s') . '_' . $phone . '.txt';
        file_put_contents($filename, "[Para: $phone]\n\n$message\n");
        return true;
    }

    $payload = json_encode(['phone' => $phone, 'message' => $message]);

    $ch = curl_init(ZAPI_BASE . '/send-text');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Client-Token: ' . ZAPI_CLIENT_TOKEN],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
}

/**
 * Formata número de celular brasileiro para uso com Z-API.
 * Aceita formatos: (11) 99999-9999, 11999999999, 5511999999999
 */
function formatPhoneZapi(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);

    // Remove DDI se já tiver
    if (substr($digits, 0, 2) === '55' && strlen($digits) >= 12) {
        $digits = substr($digits, 2);
    }

    // Adiciona DDI 55
    return '55' . $digits;
}
