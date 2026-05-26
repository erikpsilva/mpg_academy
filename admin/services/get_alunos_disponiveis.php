<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$turmaId = (int) ($_GET['turma_id'] ?? 0);
if ($turmaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'turma_id inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$modo = $_GET['modo'] ?? 'turma'; // 'turma' ou 'fila'

$excludeFila = $modo === 'fila'
    ? "AND a.id NOT IN (SELECT fe.aluno_id FROM fila_espera fe WHERE fe.turma_id = ? AND fe.status = 'aguardando')"
    : '';

$sql = "
    SELECT a.id, a.nome, a.email, a.celular,
        (EXISTS (
            SELECT 1 FROM turma_alunos ta2
            WHERE ta2.aluno_id = a.id AND ta2.status = 'ativo'
        )) AS em_turma
    FROM alunos a
    WHERE a.status = 'ativo'
      AND a.id NOT IN (
          SELECT ta.aluno_id FROM turma_alunos ta WHERE ta.turma_id = ?
      )
      $excludeFila
    ORDER BY em_turma ASC, a.nome ASC
";

$stmt = $pdo->prepare($sql);
if ($modo === 'fila') {
    $stmt->execute([$turmaId, $turmaId]);
} else {
    $stmt->execute([$turmaId]);
}
$alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($alunos as &$a) {
    $a['em_turma'] = (bool) $a['em_turma'];
}

echo json_encode(['success' => true, 'alunos' => $alunos]);
