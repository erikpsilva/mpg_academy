<?php

/**
 * Pedido de uniforme para professor ou equipe MPG.
 *
 * Separado do pedido de aluno de propósito: aqui não há cobrança, não há turma e o tipo pode
 * ser a camisa da equipe técnica, que o aluno nunca pode pedir. Toda a regra está em
 * uniformeCriarPedidoEquipe() (config/uniformes.php) — este arquivo só valida a entrada e a
 * permissão.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e) {
    error_log('[uniforme-equipe] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar o pedido: ' . $e->getMessage()]);
});

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

$pessoaTipo    = trim($_POST['pessoa_tipo']    ?? '');
$pessoaId      = (int) ($_POST['pessoa_id']    ?? 0);
$tipoUniforme  = trim($_POST['tipo_uniforme']  ?? 'completo');
$genero        = trim($_POST['genero']         ?? '');
$modelo        = trim($_POST['modelo']         ?? 'padrao');
$nomeCamisa    = uniformeNormalizarNome($_POST['nome_camisa'] ?? '');
$numero        = isset($_POST['numero']) && $_POST['numero'] !== '' ? (int) $_POST['numero'] : null;
$tamanhoCamisa = trim($_POST['tamanho_camisa'] ?? '');
$tamanhoShorts = trim($_POST['tamanho_shorts'] ?? '') ?: null;
$cargo         = trim($_POST['equipe_cargo']   ?? '') ?: null;

if ($pessoaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Escolha a pessoa do pedido.']);
    exit;
}

$resultado = uniformeCriarPedidoEquipe(
    $pdo,
    $pessoaTipo,
    $pessoaId,
    $tipoUniforme,
    $genero,
    $modelo,
    $nomeCamisa,
    $numero,
    $tamanhoCamisa,
    $tamanhoShorts,
    $cargo,
    (int) ($_SESSION['usuario']['id'] ?? 0)
);

if (!$resultado['success']) {
    http_response_code(409);
    echo json_encode($resultado);
    exit;
}

$pessoaNome = uniformePessoaNome($pdo, $pessoaTipo, $pessoaId) ?? '';

echo json_encode([
    'success'   => true,
    'pedido_id' => $resultado['pedido_id'],
    'message'   => 'Pedido registrado para ' . $pessoaNome . '.',
    'texto_camisa' => $tipoUniforme === 'equipe_tecnica'
        ? uniformeTextoEquipe($cargo, $nomeCamisa)
        : $nomeCamisa . ($numero !== null ? ' #' . $numero : ''),
]);
