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

$alunoId = (int) ($_POST['aluno_id'] ?? 0);
$isento  = ($_POST['isento'] ?? '0') === '1' ? 1 : 0;

if ($alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$check = $pdo->prepare("SELECT matricula_cobrada FROM alunos WHERE id = ?");
$check->execute([$alunoId]);
$aluno = $check->fetch();

if (!$aluno) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}

if ((int) $aluno['matricula_cobrada'] === 1) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'A taxa de matrícula já foi cobrada deste aluno — não é possível isentar retroativamente.']);
    exit;
}

$pdo->prepare("UPDATE alunos SET isento_matricula = ? WHERE id = ?")->execute([$isento, $alunoId]);

echo json_encode(['success' => true]);
