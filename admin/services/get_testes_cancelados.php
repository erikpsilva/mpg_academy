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

$stmt = $pdo->query("
    SELECT
        ae.id, ae.status, ae.data_agendada, ae.criado_em,
        at.id   AS aluno_teste_id,
        at.nome, at.email, at.celular,
        at.is_menor, at.responsavel_nome, at.responsavel_celular,
        t.id    AS turma_id, t.nome AS turma_nome,
        q.nome  AS quadra_nome
    FROM aulas_experimentais ae
    JOIN alunos_teste at ON at.id = ae.aluno_teste_id
    JOIN turmas t        ON t.id  = ae.turma_id
    JOIN quadras q       ON q.id  = t.quadra_id
    WHERE ae.status = 'cancelada'
    ORDER BY ae.criado_em DESC, ae.id DESC
");
$cancelados = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($cancelados as &$r) {
    $r['id']             = (int) $r['id'];
    $r['aluno_teste_id'] = (int) $r['aluno_teste_id'];
    $r['turma_id']       = (int) $r['turma_id'];
    $r['is_menor']       = (int) $r['is_menor'];
}
unset($r);

echo json_encode([
    'success'    => true,
    'cancelados' => $cancelados,
]);
