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
if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] !== 'professor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$profId      = (int) $_SESSION['usuario']['professor_id'];
$senhaAtual  = $_POST['senha_atual']      ?? '';
$senhaNova   = $_POST['senha_nova']       ?? '';
$senhaConf   = $_POST['senha_confirmar']  ?? '';

if ($senhaAtual === '' || $senhaNova === '' || $senhaConf === '') {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
    exit;
}
if (strlen($senhaNova) < 6) {
    echo json_encode(['success' => false, 'message' => 'A nova senha deve ter pelo menos 6 caracteres.']);
    exit;
}
if ($senhaNova !== $senhaConf) {
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$st = $pdo->prepare("SELECT senha FROM professores WHERE id = ?");
$st->execute([$profId]);
$prof = $st->fetch(PDO::FETCH_ASSOC);

if (!$prof || !password_verify($senhaAtual, $prof['senha'])) {
    echo json_encode(['success' => false, 'message' => 'Senha atual incorreta.']);
    exit;
}

$pdo->prepare("UPDATE professores SET senha = ? WHERE id = ?")
    ->execute([password_hash($senhaNova, PASSWORD_DEFAULT), $profId]);

echo json_encode(['success' => true, 'message' => 'Senha atualizada com sucesso.']);
