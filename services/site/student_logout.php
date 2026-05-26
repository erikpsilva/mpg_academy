<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['aluno']);

require_once dirname(__FILE__, 3) . '/config/app.php';

header('Location: ' . BASE_URL);
exit;
