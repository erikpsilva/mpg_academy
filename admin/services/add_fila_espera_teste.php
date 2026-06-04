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

$alunoTesteId = (int) ($_POST['aluno_teste_id'] ?? 0);
$turmaId      = (int) ($_POST['turma_id']       ?? 0);

if ($alunoTesteId <= 0 || $turmaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Verifica se já está na fila desta turma
$check = $pdo->prepare("
    SELECT id FROM fila_espera
    WHERE aluno_teste_id = ? AND turma_id = ? AND status = 'aguardando'
");
$check->execute([$alunoTesteId, $turmaId]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este aluno já está na fila de espera desta turma.']);
    exit;
}

// Verifica se já é aluno ativo nesta turma
$checkAtivo = $pdo->prepare("
    SELECT 1
    FROM alunos_teste at
    JOIN alunos a       ON a.email = at.email AND a.status = 'ativo'
    JOIN turma_alunos ta ON ta.aluno_id = a.id AND ta.turma_id = ? AND ta.status = 'ativo'
    WHERE at.id = ?
    LIMIT 1
");
$checkAtivo->execute([$turmaId, $alunoTesteId]);
if ($checkAtivo->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este aluno já está ativo nesta turma.']);
    exit;
}

try {
    $pdo->prepare("
        INSERT INTO fila_espera (turma_id, aluno_teste_id, status)
        VALUES (?, ?, 'aguardando')
    ")->execute([$turmaId, $alunoTesteId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar à fila de espera.']);
}
