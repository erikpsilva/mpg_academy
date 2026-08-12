<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['jogador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/batebola.php';

$pdo        = getDbConnection();
$jogadorId  = (int) $_SESSION['jogador']['id'];
$dataEvento = batebolaProximoDomingo($pdo);

$st = $pdo->prepare("SELECT status FROM batebola_inscricoes WHERE jogador_id = ? AND data_evento = ?");
$st->execute([$jogadorId, $dataEvento]);
$status = $st->fetchColumn();

echo json_encode(['success' => true, 'status' => $status ?: 'nenhuma', 'data_evento' => $dataEvento]);
