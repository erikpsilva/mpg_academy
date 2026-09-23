<?php

// Credenciais da Z-API — guardadas fora da pasta pública (ver config/segredos.php).
// Com a instância e o token qualquer um manda WhatsApp pelo número da academia, então eles
// não podem ficar num arquivo servido pela web nem num repositório aberto.
require_once dirname(__FILE__, 3) . '/config/segredos.php';

define('ZAPI_INSTANCE',     mpgSegredoObrigatorio('ZAPI_INSTANCE'));
define('ZAPI_TOKEN',        mpgSegredoObrigatorio('ZAPI_TOKEN'));
define('ZAPI_CLIENT_TOKEN', mpgSegredoObrigatorio('ZAPI_CLIENT_TOKEN'));
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

/**
 * Gera um link do Google Maps que busca exatamente o endereço informado.
 * Usado nas mensagens de WhatsApp em vez de deixar o WhatsApp tentar
 * detectar/geocodificar o endereço em texto puro sozinho (o que pode
 * levar pro lugar errado quando o parser dele erra a leitura).
 */
/**
 * Link do mapa que vai nas mensagens de aula e treino.
 *
 * A busca por endereço (o fallback abaixo) já mandou aluno pro lugar errado: o Google
 * interpreta o texto do jeito dele e às vezes cai numa outra rua de mesmo nome. Por isso
 * cada quadra pode ter o link certo salvo em `quadras.maps_link` — quando existe, é ele que
 * vale, sem adivinhação. A busca por endereço fica só pra quadra que ainda não tem link.
 *
 * @param string      $endereco Endereço montado, usado só quando não há link salvo.
 * @param string|null $linkFixo Conteúdo de quadras.maps_link, quando houver.
 */
function googleMapsLink(string $endereco, ?string $linkFixo = null): string {
    $linkFixo = trim((string) $linkFixo);
    if ($linkFixo !== '') return $linkFixo;

    return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($endereco);
}
