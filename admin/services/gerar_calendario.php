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

require_once dirname(__FILE__, 3) . '/config/database.php';

$turmaId = (int) ($_POST['turma_id'] ?? 0);
$dataFim = trim($_POST['data_fim'] ?? '');

if ($turmaId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$pdo = getDbConnection();

$turma = $pdo->prepare("SELECT data_inicio FROM turmas WHERE id = ?");
$turma->execute([$turmaId]);
$turma = $turma->fetch();

if (!$turma || !$turma['data_inicio']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Configure a data de início antes de gerar o calendário.']);
    exit;
}

// Dias da semana deste turma (0=Dom...6=Sáb)
$diasStmt = $pdo->prepare("
    SELECT DISTINCT qh.dia_semana
    FROM turma_horarios th
    JOIN quadra_horarios qh ON qh.id = th.horario_id
    WHERE th.turma_id = ?
");
$diasStmt->execute([$turmaId]);
$diasTreino = array_column($diasStmt->fetchAll(), 'dia_semana');
$diasTreino = array_map('intval', $diasTreino);

if (empty($diasTreino)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Esta turma não possui horários cadastrados.']);
    exit;
}

function feriados(int $ano): array {
    $fixed = [
        "$ano-01-01", "$ano-04-21", "$ano-05-01",
        "$ano-09-07", "$ano-10-12", "$ano-11-02",
        "$ano-11-15", "$ano-11-20", "$ano-12-25",
    ];
    $easter = easter_date($ano);
    $var = [
        date('Y-m-d', $easter - 48 * 86400), // Carnaval seg
        date('Y-m-d', $easter - 47 * 86400), // Carnaval ter
        date('Y-m-d', $easter - 2  * 86400), // Sexta Santa
        date('Y-m-d', $easter + 60 * 86400), // Corpus Christi
    ];
    return array_flip(array_merge($fixed, $var));
}

$inicio = new DateTime($turma['data_inicio']);
$fim    = new DateTime($dataFim);

if ($fim < $inicio) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data fim deve ser posterior à data de início.']);
    exit;
}

// Pré-carrega feriados dos anos envolvidos
$feriados = [];
for ($y = (int) $inicio->format('Y'); $y <= (int) $fim->format('Y'); $y++) {
    $feriados += feriados($y);
}

// Remove apenas treinos "agendados" futuros (preserva realizados/cancelados)
$pdo->prepare("DELETE FROM turma_treinos WHERE turma_id = ? AND status = 'agendado' AND data_treino >= ?")->execute([$turmaId, $turma['data_inicio']]);

$insert  = $pdo->prepare("INSERT IGNORE INTO turma_treinos (turma_id, data_treino) VALUES (?, ?)");
$current = clone $inicio;
$count   = 0;

while ($current <= $fim) {
    $dow     = (int) $current->format('w'); // PHP: 0=Dom, 6=Sáb
    $dateStr = $current->format('Y-m-d');

    if (in_array($dow, $diasTreino) && !isset($feriados[$dateStr])) {
        $insert->execute([$turmaId, $dateStr]);
        $count++;
    }
    $current->modify('+1 day');
}

echo json_encode(['success' => true, 'message' => "$count treinos gerados.", 'total' => $count]);
