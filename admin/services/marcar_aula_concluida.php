<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] !== 'professor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo    = getDbConnection();
$profId = (int) $_SESSION['usuario']['professor_id'];

$turmaId = (int) ($_POST['turma_id'] ?? 0);
$data    = trim($_POST['data'] ?? '');

if (!$turmaId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

if ($data > date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Não é possível registrar aulas futuras.']);
    exit;
}

// Verifica se existe falta registrada pelo admin — não pode marcar como concluída
$stFalta = $pdo->prepare("SELECT id FROM professor_faltas WHERE professor_id = ? AND turma_id = ? AND data = ?");
$stFalta->execute([$profId, $turmaId, $data]);
if ($stFalta->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Esta data tem falta registrada pelo administrador.']);
    exit;
}

// Toggle
$stChk = $pdo->prepare("SELECT id FROM professor_aulas_concluidas WHERE professor_id = ? AND turma_id = ? AND data = ?");
$stChk->execute([$profId, $turmaId, $data]);
$existing = $stChk->fetchColumn();

if ($existing) {
    $pdo->prepare("DELETE FROM professor_aulas_concluidas WHERE id = ?")->execute([$existing]);
    echo json_encode(['success' => true, 'action' => 'desmarcado']);
} else {
    $pdo->prepare("INSERT INTO professor_aulas_concluidas (professor_id, turma_id, data) VALUES (?, ?, ?)")
        ->execute([$profId, $turmaId, $data]);
    echo json_encode(['success' => true, 'action' => 'concluido']);
}
