<?php

/**
 * Marca um erro de pagamento como resolvido (ou volta pra pendente), e também serve pra
 * marcar todos como vistos — zerando o contador do sino.
 */

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

$pdo   = getDbConnection();
$acao  = trim($_POST['acao'] ?? '');

// Abrir a tela já dá os erros por vistos — é isso que zera o badge do sino.
if ($acao === 'marcar_vistos') {
    $pdo->query("UPDATE pagamento_erros SET visto_admin = 1 WHERE visto_admin = 0");
    echo json_encode(['success' => true]);
    exit;
}

$erroId    = (int) ($_POST['erro_id'] ?? 0);
$resolvido = ($_POST['resolvido'] ?? '1') === '1' ? 1 : 0;

if ($erroId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Erro inválido.']);
    exit;
}

$st = $pdo->prepare("UPDATE pagamento_erros SET resolvido = ?, visto_admin = 1 WHERE id = ?");
$st->execute([$resolvido, $erroId]);

echo json_encode(['success' => true, 'resolvido' => (bool) $resolvido]);
