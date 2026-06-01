<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$parcelaId = (int)($_POST['parcela_id'] ?? 0);
$tipo      = in_array($_POST['tipo'] ?? '', ['pago','adiantado']) ? $_POST['tipo'] : 'pago';

if ($parcelaId <= 0) { echo json_encode(['success'=>false,'message'=>'ID inválido.']); exit; }

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stP = $pdo->prepare("
    SELECT p.*, d.descricao AS div_desc, d.num_parcelas
    FROM parcelas_dividas p
    JOIN dividas d ON d.id = p.divida_id
    WHERE p.id = ? AND p.status = 'pendente'
");
$stP->execute([$parcelaId]);
$parcela = $stP->fetch();

if (!$parcela) {
    echo json_encode(['success'=>false,'message'=>'Parcela não encontrada ou já paga.']);
    exit;
}

$hoje        = date('Y-m-d');
$competencia = date('Y-m');
$descricao   = $parcela['div_desc'] . ' — Parcela ' . $parcela['numero'] . '/' . $parcela['num_parcelas'];

try {
    $pdo->beginTransaction();

    // Cria lançamento financeiro
    $stLanc = $pdo->prepare("
        INSERT INTO lancamentos_financeiros
            (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
        VALUES (?, ?, 'despesa', 'parcela_divida', ?, ?, 'auto', 'parcela_divida', ?)
    ");
    $stLanc->execute([$competencia, $hoje, $descricao, $parcela['valor'], $parcelaId]);
    $lancamentoId = (int) $pdo->lastInsertId();

    // Atualiza a parcela
    $stUpd = $pdo->prepare("
        UPDATE parcelas_dividas
        SET status = ?, data_pagamento = ?, lancamento_id = ?
        WHERE id = ?
    ");
    $stUpd->execute([$tipo, $hoje, $lancamentoId, $parcelaId]);

    // Verifica se todas as parcelas da dívida foram pagas → quita a dívida
    $stCheck = $pdo->prepare("
        SELECT COUNT(*) FROM parcelas_dividas WHERE divida_id = ? AND status = 'pendente'
    ");
    $stCheck->execute([$parcela['divida_id']]);
    if ((int)$stCheck->fetchColumn() === 0) {
        $pdo->prepare("UPDATE dividas SET status = 'quitado' WHERE id = ?")
            ->execute([$parcela['divida_id']]);
    }

    $pdo->commit();
    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erro ao registrar pagamento.']);
}
