<?php

/**
 * Turmas ativas de um aluno específico — usado no formulário de pedido manual de
 * uniforme (admin/pedirfuniforme) depois que o admin escolhe o aluno.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$alunoId = (int) ($_GET['aluno_id'] ?? 0);
if ($alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aluno inválido.']);
    exit;
}

$pdo = getDbConnection();

$stAluno = $pdo->prepare("SELECT id, nome, email, sexo FROM alunos WHERE id = ? AND status = 'ativo'");
$stAluno->execute([$alunoId]);
$aluno = $stAluno->fetch();

if (!$aluno) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}

$turmas = uniformeTurmasDoAluno($pdo, $alunoId);

echo json_encode(['success' => true, 'aluno' => $aluno, 'turmas' => $turmas]);
