<?php

/**
 * Quem pode receber pedido de uniforme de equipe (professores e usuários do painel), com os
 * números já ocupados no uniforme completo da equipe.
 *
 * Alimenta o seletor de pessoa em admin/pedirfuniforme. Aluno NÃO entra aqui — o fluxo dele
 * continua sendo a busca por nome, com turma e cobrança.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e) {
    error_log('[uniforme-equipe-lista] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar: ' . $e->getMessage()]);
});

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (empty($_SESSION['usuario']) || !in_array($nivel, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo = getDbConnection();

// Números ocupados só fazem sentido com gênero e pessoa definidos (o balde é por gênero, e
// o número da própria pessoa não bloqueia ela mesma).
$genero     = trim($_GET['genero']      ?? '');
$pessoaTipo = trim($_GET['pessoa_tipo'] ?? '');
$pessoaId   = (int) ($_GET['pessoa_id'] ?? 0);

$balde = null;
if (in_array($genero, UNIFORME_GENEROS, true) && uniformeEhEquipe($pessoaTipo) && $pessoaId > 0) {
    $balde = uniformeNumerosDoBaldeEquipe($pdo, $genero, $pessoaTipo, $pessoaId);
}

echo json_encode([
    'success'  => true,
    'pessoas'  => uniformeEquipeDisponivel($pdo),
    'tipos'    => UNIFORME_TIPO_LABEL,
    'cargos'   => UNIFORME_CARGO_LABEL,
    'tamanhos' => [
        'masculino' => [
            'camisa' => uniformeTamanhos('masculino', 'camisa'),
            'shorts' => uniformeTamanhos('masculino', 'shorts'),
        ],
        'feminino' => [
            'camisa' => uniformeTamanhos('feminino', 'camisa'),
            'shorts' => uniformeTamanhos('feminino', 'shorts'),
        ],
    ],
    'numero_min' => UNIFORME_NUMERO_MIN,
    'numero_max' => UNIFORME_NUMERO_MAX,
    'nome_max'   => UNIFORME_NOME_MAX,
    'ocupados'   => $balde['ocupados'] ?? null,
    'meus'       => $balde['meus'] ?? null,
]);
