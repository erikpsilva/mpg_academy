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

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__) . '/lib_pagamento_professor.php';
$pdo = getDbConnection();

// ── Saldo em caixa: acumulado desde sempre, direto de lancamentos_financeiros ──
// (única fonte de verdade — todo pagamento real gera um lançamento lá)
$totais = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) AS entradas,
        COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) AS saidas
    FROM lancamentos_financeiros
")->fetch(PDO::FETCH_ASSOC);

$entradas = (float) $totais['entradas'];
$saidas   = (float) $totais['saidas'];

// ── Dívida em aberto: parcelas de dívidas da empresa ainda pendentes ──────────
$dividaPendente = (float) $pdo->query("
    SELECT COALESCE(SUM(valor), 0) FROM parcelas_dividas WHERE status = 'pendente'
")->fetchColumn();

// ── Professores a pagar: saldo devido acumulado de cada professor ativo ──────
$profIds = $pdo->query("SELECT id FROM professores WHERE status = 'ativo'")->fetchAll(PDO::FETCH_COLUMN);
$professoresAPagar = 0.0;
foreach ($profIds as $profId) {
    $professoresAPagar += max(0, calcularValorDevido($pdo, (int) $profId)['valor_devido']);
}

echo json_encode([
    'success'             => true,
    'entradas'            => $entradas,
    'saidas'              => $saidas,
    'saldo'               => $entradas - $saidas,
    'divida_pendente'     => $dividaPendente,
    'professores_a_pagar' => $professoresAPagar,
]);
