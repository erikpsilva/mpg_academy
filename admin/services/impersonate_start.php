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

$professorId = (int) ($_POST['professor_id'] ?? 0);
if ($professorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$st = $pdo->prepare("
    SELECT id, nome, sobrenome, email, cpf, status
    FROM professores WHERE id = ?
");
$st->execute([$professorId]);
$prof = $st->fetch(PDO::FETCH_ASSOC);

if (!$prof || $prof['status'] !== 'ativo') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Professor não encontrado ou inativo.']);
    exit;
}

$adminId = (int) $_SESSION['usuario']['id'];

$pdo->prepare("
    INSERT INTO impersonacoes_log (admin_id, professor_id) VALUES (?, ?)
")->execute([$adminId, $professorId]);
$logId = (int) $pdo->lastInsertId();

$_SESSION['_impersonator']        = $_SESSION['usuario'];
$_SESSION['_impersonator_log_id'] = $logId;

// Mesmo formato exato usado pelo login real de professor (admin/services/login.php)
$_SESSION['usuario'] = [
    'id'            => $prof['id'],
    'nome_completo' => $prof['nome'] . ' ' . $prof['sobrenome'],
    'email'         => $prof['email'],
    'cpf'           => $prof['cpf'],
    'nivel_acesso'  => 'professor',
    'professor_id'  => $prof['id'],
];

echo json_encode(['success' => true]);
