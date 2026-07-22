<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__FILE__, 3) . '/config/app.php';

if (empty($_SESSION['_impersonator_aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$logId   = $_SESSION['_impersonator_aluno']['log_id'] ?? null;
$alunoId = $_SESSION['aluno']['id'] ?? null;

if ($logId) {
    $pdo->prepare("UPDATE impersonacoes_aluno_log SET finalizado_em = NOW() WHERE id = ?")->execute([$logId]);
}

unset($_SESSION['aluno'], $_SESSION['_impersonator_aluno']);

header('Location: ' . ADMIN_BASE_URL . '/alunos' . ($alunoId ? '?id=' . (int) $alunoId : ''));
exit;
