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

$id             = (int) ($_POST['id'] ?? 0);
$novoVencimento = trim($_POST['novo_vencimento'] ?? '');

if ($id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $novoVencimento)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$mens = $pdo->prepare("SELECT id, status FROM mensalidades WHERE id = ?");
$mens->execute([$id]);
$mens = $mens->fetch();

if (!$mens) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Fatura não encontrada.']);
    exit;
}
if ($mens['status'] === 'pago') {
    echo json_encode(['success' => false, 'message' => 'Fatura já paga, não é possível alterar o prazo.']);
    exit;
}

// Se a nova data ainda não venceu, tira do atraso — multa/juros são calculados na hora a
// partir de status+vencimento (nunca ficam gravados), então isso já basta pra "sumir" com
// o valor extra, sem precisar mexer em nenhuma outra coluna.
$novoStatus = $novoVencimento >= date('Y-m-d') ? 'pendente' : 'atrasado';

$pdo->prepare("UPDATE mensalidades SET vencimento = ?, status = ?, atualizado_em = NOW() WHERE id = ?")
    ->execute([$novoVencimento, $novoStatus, $id]);

echo json_encode(['success' => true, 'status' => $novoStatus]);
