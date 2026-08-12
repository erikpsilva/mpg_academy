<?php

require_once __DIR__ . '/app.php';

$ALLOWED_ORIGINS = [
    appBaseUrl(),
    'http://localhost:3000',
    'http://127.0.0.1',
    'http://127.0.0.1:3000',
    'http://127.0.0.1/mpg_academy',
    'http://localhost/mpg_academy',
    'https://www.mpgacademy.com.br',
    'https://mpgacademy.com.br',
];

function normalizeHost(string $host): string {
    if (appStartsWith($host, '[')) {
        return trim(explode(']', $host)[0], '[]');
    }

    return explode(':', $host)[0];
}

/**
 * Reduz uma URL a "esquema://host[:porta]" — que é exatamente o formato de um header
 * `Origin`. Um Origin NUNCA carrega caminho, então comparar ele direto com entradas do
 * tipo "http://localhost/mpg_academy" jamais casa: era isso que fazia todo POST do
 * navegador tomar 403 "Acesso não autorizado" no ambiente local (em produção passava por
 * acaso, porque lá a URL base não tem caminho). Normalizando os dois lados, o mesmo
 * conjunto de origens permitidas continua valendo — só que agora comparado corretamente.
 */
function originBase(string $url): string {
    $url = trim($url);
    if ($url === '') return '';

    $partes = parse_url($url);
    if (empty($partes['scheme']) || empty($partes['host'])) return '';

    $base = strtolower($partes['scheme']) . '://' . strtolower($partes['host']);
    if (!empty($partes['port'])) {
        $base .= ':' . $partes['port'];
    }

    return $base;
}

function validateApiAccess(array $allowedOrigins): void {
    $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    $originAllowed = false;

    // Verifica pelo header Origin. O navegador manda esse header em TODO POST (mesmo
    // same-origin), então a comparação precisa ser por esquema+host+porta — ver originBase().
    if (!empty($origin)) {
        $origemPedido = originBase($origin);

        foreach ($allowedOrigins as $allowed) {
            if ($origemPedido !== '' && $origemPedido === originBase($allowed)) {
                $originAllowed = true;
                header('Access-Control-Allow-Origin: ' . $origin);
                break;
            }
        }
    } else {
        // Sem header Origin = mesma origem (same-origin request)
        // Valida pelo Referer como fallback
        foreach ($allowedOrigins as $allowed) {
            if (appStartsWith($referer, $allowed)) {
                $originAllowed = true;
                break;
            }
        }

        // Se não tiver nem Origin nem Referer, é requisição direta ao servidor
        // Permite somente se vier do próprio host (localhost ou produção)
        if (!$originAllowed && empty($referer)) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            foreach ($allowedOrigins as $allowed) {
                $allowedHost = parse_url($allowed, PHP_URL_HOST);
                if (normalizeHost($host) === $allowedHost) {
                    $originAllowed = true;
                    break;
                }
            }
        }
    }

    if (!$originAllowed) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
        exit;
    }

    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Responde preflight OPTIONS do CORS e encerra
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
