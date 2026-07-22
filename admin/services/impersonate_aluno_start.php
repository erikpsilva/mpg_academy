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

if (empty($_SESSION['usuario']) || ($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$alunoId = (int) ($_POST['aluno_id'] ?? 0);
if ($alunoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Aluno inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$st = $pdo->prepare("SELECT id, nome, email, foto, status FROM alunos WHERE id = ?");
$st->execute([$alunoId]);
$aluno = $st->fetch(PDO::FETCH_ASSOC);

if (!$aluno) {
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}
if ($aluno['status'] !== 'ativo') {
    echo json_encode(['success' => false, 'message' => 'Só é possível visualizar como aluno ativo.']);
    exit;
}

$pdo->prepare("INSERT INTO impersonacoes_aluno_log (admin_id, aluno_id) VALUES (?, ?)")
    ->execute([$_SESSION['usuario']['id'], $alunoId]);
$logId = (int) $pdo->lastInsertId();

// Guarda quem está impersonando (pra mostrar o banner e voltar depois) — não mexe em
// $_SESSION['usuario'], o admin continua logado no painel normalmente nessa mesma sessão.
$_SESSION['_impersonator_aluno'] = [
    'admin_id'   => $_SESSION['usuario']['id'],
    'admin_nome' => $_SESSION['usuario']['nome_completo'] ?? '',
    'log_id'     => $logId,
];
$_SESSION['aluno'] = $aluno;

echo json_encode(['success' => true]);
