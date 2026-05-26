<?php

define('ROOT', __DIR__);

require_once ROOT . '/config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Captura a rota da URL (parâmetro 'url') e divide por "/"
$route = explode("/", $_GET['url'] ?? 'inicio');

// Filtra caracteres perigosos da rota principal
$mainRoute = preg_replace('/[^a-zA-Z0-9_-]/', '', $route[0]);

// Verifica se é uma rota do admin
if ($mainRoute === 'admin') {
    $subRoute = isset($route[1]) && $route[1] !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '', $route[1]) : 'login';
    $basePathAdmin = ROOT . '/admin/pages/';

    if (file_exists("{$basePathAdmin}{$subRoute}/index.php")) {
        include "{$basePathAdmin}{$subRoute}/index.php";
    } else {
        include "{$basePathAdmin}login/index.php";
    }
} else {
    $basePathPages = ROOT . '/pages/';

    if (file_exists("{$basePathPages}{$mainRoute}/index.php")) {
        include "{$basePathPages}{$mainRoute}/index.php";
    } else {
        include "{$basePathPages}inicio/index.php";
    }
}
?>
