<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

require_once dirname(__FILE__, 3) . '/config/app.php';

if (empty($_SESSION['_impersonator'])) {
    header('Location: ' . BASE_URL . '/admin/login');
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

if (!empty($_SESSION['_impersonator_log_id'])) {
    $pdo->prepare("
        UPDATE impersonacoes_log SET finalizado_em = NOW() WHERE id = ?
    ")->execute([$_SESSION['_impersonator_log_id']]);
}

$_SESSION['usuario'] = $_SESSION['_impersonator'];
unset($_SESSION['_impersonator'], $_SESSION['_impersonator_log_id']);

header('Location: ' . BASE_URL . '/admin/professores');
exit;
