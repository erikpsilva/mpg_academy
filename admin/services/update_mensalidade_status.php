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

$id      = (int) ($_POST['id'] ?? 0);
$status  = trim($_POST['status'] ?? '');
$dataPag = trim($_POST['data_pagamento'] ?? '');

if ($id <= 0 || !in_array($status, ['pendente', 'pago', 'atrasado'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

if ($dataPag && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPag)) {
    $dataPag = '';
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$mens = $pdo->prepare("
    SELECT m.id, m.referencia, m.valor, m.status AS status_atual,
           a.nome AS aluno_nome, a.id AS aluno_id
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
    WHERE m.id = ?
");
$mens->execute([$id]);
$mens = $mens->fetch();

if (!$mens) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Mensalidade não encontrada.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($status === 'pago') {
        $dataSalvar = $dataPag ?: date('Y-m-d');

        $pdo->prepare("
            UPDATE mensalidades
            SET status = 'pago', data_pagamento = ?, atualizado_em = NOW()
            WHERE id = ?
        ")->execute([$dataSalvar, $id]);

        // Registra lançamento financeiro se ainda não existe para esta mensalidade
        $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
                  '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
        [$refAno, $refMes] = explode('-', $mens['referencia']);
        $refLabel = ($meses[$refMes] ?? $refMes) . '/' . $refAno;
        $descricao = 'Mensalidade ' . $refLabel . ' — ' . $mens['aluno_nome'] . ' (baixa manual)';

        try {
            $pdo->prepare("
                INSERT IGNORE INTO lancamentos_financeiros
                    (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
                VALUES (?, ?, 'receita', 'mensalidade', ?, ?, 'manual', 'mensalidade', ?)
            ")->execute([$mens['referencia'], $dataSalvar, $descricao, $mens['valor'], $id]);
        } catch (PDOException $e) {}

    } else {
        $pdo->prepare("
            UPDATE mensalidades
            SET status = ?, data_pagamento = NULL, atualizado_em = NOW()
            WHERE id = ?
        ")->execute([$status, $id]);

        // Remove lançamento manual se havia sido gerado por baixa manual
        try {
            $pdo->prepare("
                DELETE FROM lancamentos_financeiros
                WHERE referencia_tipo = 'mensalidade' AND referencia_id = ? AND origem = 'manual'
            ")->execute([$id]);
        } catch (PDOException $e) {}
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'status' => $status]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()]);
}
