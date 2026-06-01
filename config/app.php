<?php

function appHost(): string {
    return $_SERVER['HTTP_HOST'] ?? 'localhost';
}

function appStartsWith(string $value, string $prefix): bool {
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function appHostName(): string {
    $host = appHost();

    if (appStartsWith($host, '[')) {
        return trim(explode(']', $host)[0], '[]');
    }

    return explode(':', $host)[0];
}

function appIsLocal(): bool {
    $host = appHostName();

    // Localhost padrão
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) return true;

    // IPs de rede local (acesso via celular/dispositivo na mesma rede)
    if (preg_match('/^192\.168\./', $host)) return true;
    if (preg_match('/^10\./',       $host)) return true;
    if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $host)) return true;

    return false;
}

function appScheme(): string {
    if (appIsLocal()) {
        return 'http';
    }

    $https = $_SERVER['HTTPS'] ?? '';
    return (!empty($https) && $https !== 'off') ? 'https' : 'https';
}

function appBaseUrl(): string {
    if (appIsLocal()) {
        return appScheme() . '://' . appHost() . '/mpg_academy';
    }

    return 'https://www.mpgacademy.com.br';
}

if (!defined('APP_ENV')) {
    define('APP_ENV', appIsLocal() ? 'local' : 'production');
}

if (!defined('APP_IS_LOCAL')) {
    define('APP_IS_LOCAL', appIsLocal());
}

if (!defined('BASE_URL')) {
    define('BASE_URL', appBaseUrl());
}

if (!defined('ADMIN_BASE_URL')) {
    define('ADMIN_BASE_URL', BASE_URL . '/admin');
}
