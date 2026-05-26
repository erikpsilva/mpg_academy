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

require_once dirname(__FILE__, 3) . '/config/database.php';

$turmaId = (int) ($_POST['turma_id'] ?? 0);
$alunoId = (int) ($_POST['aluno_id'] ?? 0);

if ($turmaId <= 0 || $alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDs inválidos.']);
    exit;
}

$pdo = getDbConnection();
$pdo->prepare("DELETE FROM turma_alunos WHERE turma_id = ? AND aluno_id = ?")->execute([$turmaId, $alunoId]);

echo json_encode(['success' => true]);
