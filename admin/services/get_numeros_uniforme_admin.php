<?php

/**
 * Mesma disponibilidade de números do modal do aluno (services/site/get_numeros_uniforme.php),
 * só que pro admin consultar em nome de qualquer aluno no formulário de pedido manual.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo     = getDbConnection();
$alunoId = (int) ($_GET['aluno_id'] ?? 0);
$turmaId = (int) ($_GET['turma_id'] ?? 0);
$genero  = trim($_GET['genero'] ?? '');

if ($alunoId <= 0 || $turmaId <= 0 || !in_array($genero, UNIFORME_GENEROS, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

// Só aceita turma em que o aluno de fato está matriculado — mesma regra do fluxo do aluno.
$turmaIds = array_map(fn($t) => (int) $t['id'], uniformeTurmasDoAluno($pdo, $alunoId));
if (!in_array($turmaId, $turmaIds, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Esse aluno não está matriculado nessa turma.']);
    exit;
}

$balde = uniformeNumerosDoBalde($pdo, $turmaId, $genero, $alunoId);

echo json_encode([
    'success'  => true,
    'turma_id' => $turmaId,
    'genero'   => $genero,
    'min'      => UNIFORME_NUMERO_MIN,
    'max'      => UNIFORME_NUMERO_MAX,
    'ocupados' => $balde['ocupados'],
    'meus'     => $balde['meus'],
]);
