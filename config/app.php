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

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
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
