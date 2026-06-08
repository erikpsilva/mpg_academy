<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); exit; }

$profId  = (int)   (trim($_POST['professor_id'] ?? ''));
$turmaId = (int)   (trim($_POST['turma_id']     ?? ''));
$data    =          trim($_POST['data']          ?? '');
$tipo    = in_array($_POST['tipo'] ?? '', ['planejada', 'sem_aviso']) ? $_POST['tipo'] : 'sem_aviso';
$obs     =          trim($_POST['observacao']    ?? '') ?: null;

if ($profId <= 0 || $turmaId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['success' => false, 'message' => 'Preencha professor, turma e data corretamente.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Verifica que o professor leciona nessa turma
$stCheck = $pdo->prepare("SELECT 1 FROM professor_turmas WHERE professor_id = ? AND turma_id = ?");
$stCheck->execute([$profId, $turmaId]);
if (!$stCheck->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Professor não leciona nesta turma.']);
    exit;
}

try {
    $pdo->prepare("
        INSERT INTO professor_faltas (professor_id, turma_id, data, tipo, observacao)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), observacao = VALUES(observacao)
    ")->execute([$profId, $turmaId, $data, $tipo, $obs]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar falta.']);
}
