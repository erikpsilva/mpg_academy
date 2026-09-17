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

$chave = trim($_POST['chave'] ?? '');
$valor = $_POST['valor'] ?? '';

require_once dirname(__FILE__, 3) . '/config/avisos.php';

// Somente chaves permitidas podem ser alteradas. As dos avisos vêm do catálogo em
// config/avisos.php, para que aviso novo não precise ser lembrado aqui também.
$chavesAvisos     = avisosChaves();
$chavesPermitidas = array_merge(
    ['pagamento_modo_teste', 'valor_matricula', 'matricula_ativa', 'valor_batebola', 'valor_uniforme', 'valor_uniforme_equipe'],
    $chavesAvisos
);
if (!in_array($chave, $chavesPermitidas, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Chave inválida.']);
    exit;
}

// Aviso só aceita ligado/desligado. Qualquer outro valor seria lido como "ligado" por
// avisoAtivo() e o toggle mostraria um estado que não é o gravado.
if (in_array($chave, $chavesAvisos, true) && !in_array($valor, ['0', '1'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor inválido para aviso: use 0 ou 1.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("
    INSERT INTO configuracoes (chave, valor)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = CURRENT_TIMESTAMP
");
$stmt->execute([$chave, $valor]);

echo json_encode(['success' => true]);
