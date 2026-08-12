<?php

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

$dataEvento = trim($_POST['data_evento'] ?? '');
$motivo     = trim($_POST['motivo'] ?? '');

$dt = DateTime::createFromFormat('Y-m-d', $dataEvento);
if (!$dt || $dt->format('Y-m-d') !== $dataEvento || (int) $dt->format('w') !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida — precisa ser um domingo (YYYY-MM-DD).']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$st = $pdo->prepare("SELECT id FROM batebola_dias_bloqueados WHERE data_evento = ?");
$st->execute([$dataEvento]);
$existente = $st->fetchColumn();

if ($existente) {
    $pdo->prepare("DELETE FROM batebola_dias_bloqueados WHERE id = ?")->execute([$existente]);
    echo json_encode(['success' => true, 'bloqueado' => false]);
} else {
    $pdo->prepare("INSERT INTO batebola_dias_bloqueados (data_evento, motivo) VALUES (?, ?)")
        ->execute([$dataEvento, $motivo !== '' ? $motivo : null]);
    echo json_encode(['success' => true, 'bloqueado' => true]);
}
