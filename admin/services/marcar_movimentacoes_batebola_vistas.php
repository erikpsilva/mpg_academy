<?php

/**
 * Zera o badge das movimentações do Bate Bola no sininho.
 *
 * Chamado quando o admin abre a lista de presença — igual ao que os pedidos de uniforme já
 * fazem. Marcar como visto não apaga nada: batebola_movimentacoes segue sendo o histórico
 * do que foi recebido ou devolvido por fora.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

try {
    getDbConnection()->exec("UPDATE batebola_movimentacoes SET visto_admin = 1 WHERE visto_admin = 0");
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[batebola-mov-vistas] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao marcar como visto.']);
}
