<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$mes = trim($_GET['mes'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__) . '/lib_pagamento_professor.php';
$pdo = getDbConnection();

$professores = $pdo->query("
    SELECT id, nome, sobrenome FROM professores WHERE status = 'ativo' ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$linhas = [];
foreach ($professores as $p) {
    $extrato = calcularExtratoMensal($pdo, (int) $p['id']);
    $doMes   = null;
    foreach ($extrato as $e) {
        if ($e['mes'] === $mes) { $doMes = $e; break; }
    }
    $ganho  = $doMes['ganho']  ?? 0;
    $pago   = $doMes['pago']   ?? 0;
    $status = $doMes['status'] ?? ($ganho == 0 ? 'sem_aulas' : 'pendente');

    $linhas[] = [
        'professor_id' => (int) $p['id'],
        'nome'         => trim($p['nome'] . ' ' . $p['sobrenome']),
        'ganho'        => $ganho,
        'pago'         => $pago,
        'saldo'        => $doMes['saldo'] ?? 0,
        'status'       => $status,
    ];
}

echo json_encode(['success' => true, 'mes' => $mes, 'professores' => $linhas]);
