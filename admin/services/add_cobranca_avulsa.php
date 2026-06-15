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

$alunoId   = (int)   ($_POST['aluno_id']   ?? 0);
$descricao = trim(    $_POST['descricao']   ?? '');
$valor     = (float)  ($_POST['valor']      ?? 0);
$vencimento = trim(   $_POST['vencimento']  ?? '');

if ($alunoId <= 0 || empty($descricao) || $valor <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencimento)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos corretamente.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$alunoSt = $pdo->prepare("SELECT id FROM alunos WHERE id = ? AND status = 'ativo'");
$alunoSt->execute([$alunoId]);
if (!$alunoSt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}

$referencia = date('Y-m', strtotime($vencimento));

try {
    $pdo->prepare("
        INSERT INTO mensalidades (aluno_id, turma_id, referencia, tipo, descricao, valor, vencimento, status)
        VALUES (?, NULL, ?, 'avulso', ?, ?, ?, 'pendente')
    ")->execute([$alunoId, $referencia, $descricao, round($valor, 2), $vencimento]);

    echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar cobrança: ' . $e->getMessage()]);
}
