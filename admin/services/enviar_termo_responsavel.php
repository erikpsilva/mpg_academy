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

$aulaId = (int)($_POST['aula_id'] ?? 0);
if (!$aulaId) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/mail.php';
require_once dirname(__FILE__, 3) . '/services/site/notificar_termo_responsavel.php';

$pdo = getDbConnection();

// Busca dados da aula + aluno + responsável
$stmt = $pdo->prepare("
    SELECT ae.id, at.nome, at.responsavel_nome, at.responsavel_email, at.responsavel_celular,
           t.nome AS turma_nome
    FROM aulas_experimentais ae
    JOIN alunos_teste at ON at.id = ae.aluno_teste_id
    JOIN turmas t ON t.id = ae.turma_id
    WHERE ae.id = ? LIMIT 1
");
$stmt->execute([$aulaId]);
$aula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aula) {
    echo json_encode(['success' => false, 'message' => 'Aula não encontrada.']);
    exit;
}
if (empty($aula['responsavel_email'])) {
    echo json_encode(['success' => false, 'message' => 'Responsável sem e-mail cadastrado.']);
    exit;
}

// Garante que existe um registro de termo com token
$stmtT = $pdo->prepare("SELECT id, token FROM termo_assinaturas WHERE aula_experimental_id = ? LIMIT 1");
$stmtT->execute([$aulaId]);
$termo = $stmtT->fetch(PDO::FETCH_ASSOC);

if (!$termo) {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO termo_assinaturas (aula_experimental_id, token) VALUES (?, ?)")
        ->execute([$aulaId, $token]);
} else {
    $token = $termo['token'];
}

$termoUrl = appBaseUrl() . '/termo?token=' . $token;

// Envia e-mail + WhatsApp via função compartilhada
notificarTermoResponsavel([
    'responsavel_email'   => $aula['responsavel_email'],
    'responsavel_nome'    => $aula['responsavel_nome'] ?? 'Responsável',
    'responsavel_celular' => $aula['responsavel_celular'] ?? '',
    'aluno_nome'          => $aula['nome'],
    'turma_nome'          => $aula['turma_nome'],
], $termoUrl);

echo json_encode(['success' => true, 'token' => $token]);
