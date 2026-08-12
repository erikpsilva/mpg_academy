<?php

/**
 * Cria um pedido de uniforme manualmente pelo admin, pro aluno que teve dificuldade de
 * usar o formulário sozinho. O pagamento já aconteceu por fora (link externo do Mercado
 * Pago, PIX manual etc.) — por isso o pedido nasce direto como 'pago', sem passar pela
 * reserva de 30 minutos nem pelo checkout.
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

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (empty($_SESSION['usuario']) || !in_array($nivel, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo = getDbConnection();

$alunoId    = (int) ($_POST['aluno_id'] ?? 0);
$turmaId    = (int) ($_POST['turma_id'] ?? 0);
$genero     = trim($_POST['genero']  ?? '');
$modelo     = trim($_POST['modelo']  ?? '');
$nomeCamisa = uniformeNormalizarNome($_POST['nome_camisa'] ?? '');
$numero     = (int) ($_POST['numero'] ?? 0);
$tamanhoCamisa = strtoupper(trim($_POST['tamanho_camisa'] ?? ''));
$tamanhoShorts = strtoupper(trim($_POST['tamanho_shorts'] ?? ''));

if ($alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione o aluno.']);
    exit;
}

if ($nomeCamisa === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe o nome que vai na camiseta.']);
    exit;
}

$stAluno = $pdo->prepare("SELECT id FROM alunos WHERE id = ? AND status = 'ativo'");
$stAluno->execute([$alunoId]);
if (!$stAluno->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado ou inativo.']);
    exit;
}

$turmaIds = array_map(fn($t) => (int) $t['id'], uniformeTurmasDoAluno($pdo, $alunoId));
if (!in_array($turmaId, $turmaIds, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Esse aluno não está matriculado nessa turma.']);
    exit;
}

$valor = uniformeValor($pdo);

$resultado = uniformeCriarPedidoManual(
    $pdo,
    $alunoId,
    $turmaId,
    $genero,
    $modelo,
    $nomeCamisa,
    $numero,
    $tamanhoCamisa,
    $tamanhoShorts,
    $valor,
    (int) $_SESSION['usuario']['id']
);

if (!$resultado['success']) {
    http_response_code(409);
    echo json_encode($resultado);
    exit;
}

echo json_encode($resultado);
