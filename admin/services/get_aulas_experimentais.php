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

$stmt = $pdo->prepare("
    SELECT
        ae.id, ae.status, ae.data_agendada, ae.criado_em,
        at.id AS aluno_teste_id, at.nome, at.email, at.celular,
        t.id AS turma_id, t.nome AS turma_nome, t.nivel, t.max_alunos,
        q.nome AS quadra_nome,
        (SELECT COUNT(*) FROM turma_alunos ta
            WHERE ta.turma_id = t.id AND ta.status = 'ativo') AS alunos_ativos,
        (SELECT COUNT(*) FROM aulas_experimentais ae2
            WHERE ae2.turma_id = t.id AND ae2.status = 'agendada') AS agendadas_count
    FROM aulas_experimentais ae
    JOIN alunos_teste at ON at.id = ae.aluno_teste_id
    JOIN turmas t        ON t.id  = ae.turma_id
    JOIN quadras q       ON q.id  = t.quadra_id
    WHERE ae.status IN ('agendada', 'fila')
    ORDER BY t.id ASC,
             FIELD(ae.status, 'agendada', 'fila') ASC,
             ae.data_agendada ASC,
             ae.criado_em ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por turma
$turmasMap = [];
foreach ($rows as $r) {
    $tid = (int) $r['turma_id'];

    if (!isset($turmasMap[$tid])) {
        $maxAlunos    = $r['max_alunos'] !== null ? (int) $r['max_alunos'] : null;
        $alunosAtivos = (int) $r['alunos_ativos'];
        $agendadas    = (int) $r['agendadas_count'];

        $vagasTeste = $maxAlunos !== null
            ? max(0, $maxAlunos - $alunosAtivos - $agendadas)
            : null;

        $turmasMap[$tid] = [
            'turma_id'      => $tid,
            'turma_nome'    => $r['turma_nome'],
            'quadra_nome'   => $r['quadra_nome'],
            'nivel'         => $r['nivel'],
            'max_alunos'    => $maxAlunos,
            'alunos_ativos' => $alunosAtivos,
            'vagas_teste'   => $vagasTeste,
            'agendados'     => [],
            'fila'          => [],
        ];
    }

    $entrada = [
        'id'             => (int) $r['id'],
        'aluno_teste_id' => (int) $r['aluno_teste_id'],
        'nome'          => $r['nome'],
        'email'         => $r['email'],
        'celular'       => $r['celular'],
        'data_agendada' => $r['data_agendada'],
        'criado_em'     => $r['criado_em'],
        'status'        => $r['status'],
    ];

    if ($r['status'] === 'agendada') {
        $turmasMap[$tid]['agendados'][] = $entrada;
    } else {
        $turmasMap[$tid]['fila'][] = $entrada;
    }
}

// Busca realizados (lista plana, ordenada mais recente primeiro)
// ja_aluno = 1 se o email do aluno_teste já existe em alunos (status ativo)
$stmtReal = $pdo->prepare("
    SELECT
        ae.id, ae.criado_em,
        at.id AS aluno_teste_id, at.nome, at.email, at.celular,
        t.nome AS turma_nome,
        q.nome AS quadra_nome,
        CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END AS ja_aluno
    FROM aulas_experimentais ae
    JOIN alunos_teste at  ON at.id = ae.aluno_teste_id
    JOIN turmas t         ON t.id  = ae.turma_id
    JOIN quadras q        ON q.id  = t.quadra_id
    LEFT JOIN alunos a    ON a.email = at.email AND a.status = 'ativo'
    WHERE ae.status = 'realizada'
    ORDER BY ja_aluno ASC, ae.criado_em DESC
");
$stmtReal->execute();
$realizados = $stmtReal->fetchAll(PDO::FETCH_ASSOC);

foreach ($realizados as &$r) {
    $r['id']             = (int) $r['id'];
    $r['aluno_teste_id'] = (int) $r['aluno_teste_id'];
    $r['ja_aluno']       = (int) $r['ja_aluno'];
}

echo json_encode([
    'success'    => true,
    'turmas'     => array_values($turmasMap),
    'realizados' => $realizados,
]);
