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

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Turmas que têm fila aguardando
$stmt = $pdo->prepare("
    SELECT
        t.id AS turma_id, t.nome AS turma_nome, t.nivel, t.genero, t.max_alunos,
        q.nome AS quadra_nome,
        (SELECT COUNT(*) FROM turma_alunos ta WHERE ta.turma_id = t.id AND ta.status = 'ativo') AS alunos_ativos
    FROM turmas t
    JOIN quadras q ON q.id = t.quadra_id
    WHERE EXISTS (
        SELECT 1 FROM fila_espera fe WHERE fe.turma_id = t.id AND fe.status = 'aguardando'
    )
    ORDER BY q.nome ASC, t.nome ASC
");
$stmt->execute();
$turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($turmas as &$turma) {
    $turma['alunos_ativos'] = (int) $turma['alunos_ativos'];
    $turma['max_alunos']    = $turma['max_alunos'] !== null ? (int) $turma['max_alunos'] : null;
    $turma['vagas']         = $turma['max_alunos'] !== null
        ? max(0, $turma['max_alunos'] - $turma['alunos_ativos'])
        : null;

    $filaStmt = $pdo->prepare("
        SELECT fe.id, fe.aluno_id, fe.criado_em, a.nome, a.email, a.celular
        FROM fila_espera fe
        JOIN alunos a ON a.id = fe.aluno_id
        WHERE fe.turma_id = ? AND fe.status = 'aguardando'
        ORDER BY fe.criado_em ASC
    ");
    $filaStmt->execute([$turma['turma_id']]);
    $turma['fila'] = $filaStmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode(['success' => true, 'turmas' => $turmas]);
