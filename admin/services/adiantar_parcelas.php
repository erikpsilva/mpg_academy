<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$rawIds = $_POST['ids'] ?? [];
if (!is_array($rawIds) || empty($rawIds)) {
    echo json_encode(['success'=>false,'message'=>'Nenhuma parcela selecionada.']);
    exit;
}

$ids = array_map('intval', $rawIds);
$ids = array_filter($ids, fn($id) => $id > 0);
if (empty($ids)) {
    echo json_encode(['success'=>false,'message'=>'IDs inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$ph     = implode(',', array_fill(0, count($ids), '?'));
$hoje   = date('Y-m-d');
$compet = date('Y-m');

$stP = $pdo->prepare("
    SELECT p.*, d.descricao AS div_desc, d.id AS div_id, d.num_parcelas
    FROM parcelas_dividas p
    JOIN dividas d ON d.id = p.divida_id
    WHERE p.id IN ($ph) AND p.status = 'pendente' AND p.data_vencimento > ?
");
$stP->execute([...$ids, $hoje]);
$parcelas = $stP->fetchAll(PDO::FETCH_ASSOC);

if (empty($parcelas)) {
    echo json_encode(['success'=>false,'message'=>'Nenhuma parcela válida encontrada.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stLanc = $pdo->prepare("
        INSERT INTO lancamentos_financeiros
            (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
        VALUES (?, ?, 'despesa', 'parcela_divida', ?, ?, 'auto', 'parcela_divida', ?)
    ");
    $stUpd = $pdo->prepare("
        UPDATE parcelas_dividas SET status = 'adiantado', data_pagamento = ?, lancamento_id = ? WHERE id = ?
    ");

    $dividaIds = [];
    foreach ($parcelas as $p) {
        $desc = $p['div_desc'] . ' — Parcela ' . $p['numero'] . '/' . $p['num_parcelas'] . ' (adiantada)';
        $stLanc->execute([$compet, $hoje, $desc, $p['valor'], $p['id']]);
        $lancId = (int) $pdo->lastInsertId();
        $stUpd->execute([$hoje, $lancId, $p['id']]);
        $dividaIds[$p['div_id']] = true;
    }

    // Verifica se alguma dívida ficou quitada
    $stCheck = $pdo->prepare("
        SELECT COUNT(*) FROM parcelas_dividas WHERE divida_id = ? AND status = 'pendente'
    ");
    foreach (array_keys($dividaIds) as $divId) {
        $stCheck->execute([$divId]);
        if ((int)$stCheck->fetchColumn() === 0) {
            $pdo->prepare("UPDATE dividas SET status = 'quitado' WHERE id = ?")->execute([$divId]);
        }
    }

    $pdo->commit();
    echo json_encode(['success'=>true, 'total'=>count($parcelas)]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erro ao registrar adiantamento.']);
}
