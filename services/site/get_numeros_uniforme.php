<?php

/**
 * Situação dos números 1–99 pro modal de escolha do formulário de uniforme.
 * A disponibilidade é por TURMA + GÊNERO do uniforme (ver config/uniformes.php).
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['aluno'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo     = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];

$turmaId = (int) ($_GET['turma_id'] ?? 0);
$genero  = trim($_GET['genero'] ?? '');

if (!in_array($genero, UNIFORME_GENEROS, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Gênero inválido.']);
    exit;
}

// Só aceita turma em que o aluno está de fato matriculado.
$turmas   = uniformeTurmasDoAluno($pdo, $alunoId);
$turmaIds = array_map(fn($t) => (int) $t['id'], $turmas);

if ($turmaId <= 0 && count($turmaIds) === 1) {
    $turmaId = $turmaIds[0];
}

if (!in_array($turmaId, $turmaIds, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Turma inválida para este aluno.']);
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
