<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['aluno']['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$cpf  = preg_replace('/\D/', '', $_POST['cpf'] ?? '');

if (!$nome || strlen($cpf) !== 11) {
    echo json_encode(['success' => false, 'message' => 'Preencha nome e CPF corretamente.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT is_menor, termo_status FROM alunos WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['aluno']['id']]);
$aluno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aluno || !$aluno['is_menor']) {
    echo json_encode(['success' => false, 'message' => 'Este cadastro não exige termo de responsabilidade.']);
    exit;
}
if ($aluno['termo_status'] === 'assinado') {
    echo json_encode(['success' => false, 'message' => 'Este termo já foi assinado.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$pdo->prepare("
    UPDATE alunos
    SET termo_status = 'assinado', termo_assinado_em = NOW(),
        termo_assinado_nome = ?, termo_assinado_cpf = ?, termo_assinado_ip = ?
    WHERE id = ?
")->execute([$nome, $cpf, $ip, $_SESSION['aluno']['id']]);

echo json_encode(['success' => true]);
