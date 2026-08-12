<?php

/**
 * Zera o contador de "pedidos novos" do sino da header — chamado quando o admin abre a
 * tela de uniformes ou o dropdown de notificações.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$pdo = getDbConnection();

$pdo->query("
    UPDATE pedidos_uniforme
    SET visto_admin = 1
    WHERE status_pagamento = 'pago' AND visto_admin = 0
");

echo json_encode(['success' => true]);
