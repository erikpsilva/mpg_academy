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

if ($_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para alterar a senha de alunos.']);
    exit;
}

$id    = (int) ($_POST['id'] ?? 0);
$senha = (string) ($_POST['senha'] ?? '');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

if (strlen($senha) < 6) {
    echo json_encode(['success' => false, 'message' => 'A senha precisa ter no mínimo 6 caracteres.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$senhaHash = password_hash($senha, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE alunos SET senha = ?, atualizado_em = NOW() WHERE id = ?");
$stmt->execute([$senhaHash, $id]);

if ($stmt->rowCount() === 0) {
    // Pode já ter a mesma senha (raro) ou aluno não existir — checa se existe.
    $chk = $pdo->prepare("SELECT id FROM alunos WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
        exit;
    }
}

echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso.']);
