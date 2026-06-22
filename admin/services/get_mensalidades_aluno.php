<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$alunoId = (int) ($_GET['aluno_id'] ?? 0);
if ($alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT m.id, m.referencia, m.tipo, m.descricao, m.valor, m.matricula_valor, m.proporcional_valor,
           m.vencimento, m.data_pagamento, m.status, m.mp_payment_id,
           COALESCE(t.nome, '—') AS turma_nome
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.aluno_id = ?
    ORDER BY m.vencimento DESC, m.referencia DESC
");
$stmt->execute([$alunoId]);
$rows = $stmt->fetchAll();

echo json_encode(['success' => true, 'mensalidades' => $rows]);
