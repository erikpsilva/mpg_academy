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

$turmaId = (int) ($_POST['turma_id'] ?? 0);
$alunoId = (int) ($_POST['aluno_id'] ?? 0);

if ($turmaId <= 0 || $alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDs inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Garante que o aluno não está já na turma
$check = $pdo->prepare("SELECT id FROM turma_alunos WHERE turma_id = ? AND aluno_id = ? AND status = 'ativo'");
$check->execute([$turmaId, $alunoId]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'O aluno já está nessa turma.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO fila_espera (turma_id, aluno_id) VALUES (?, ?)");
    $stmt->execute([$turmaId, $alunoId]);

    $aluno = $pdo->prepare("SELECT id, nome, email FROM alunos WHERE id = ?");
    $aluno->execute([$alunoId]);
    $aluno = $aluno->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'aluno' => $aluno]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'O aluno já está na fila de espera dessa turma.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar à fila de espera.']);
    }
}
