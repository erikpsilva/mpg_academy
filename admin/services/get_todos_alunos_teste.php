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

// ── Para fazer: agendada + fila (lista plana) ─────────────────────────────────
$stmtParaFazer = $pdo->query("
    SELECT
        ae.id, ae.status, ae.data_agendada, ae.criado_em,
        at.id   AS aluno_teste_id,
        at.nome, at.email, at.celular,
        t.nome  AS turma_nome, t.nivel,
        q.nome  AS quadra_nome
    FROM aulas_experimentais ae
    JOIN alunos_teste at ON at.id = ae.aluno_teste_id
    JOIN turmas t        ON t.id  = ae.turma_id
    JOIN quadras q       ON q.id  = t.quadra_id
    WHERE ae.status IN ('agendada', 'fila')
    ORDER BY ae.data_agendada ASC, ae.criado_em ASC
");
$paraFazer = $stmtParaFazer->fetchAll(PDO::FETCH_ASSOC);

foreach ($paraFazer as &$r) {
    $r['id']             = (int) $r['id'];
    $r['aluno_teste_id'] = (int) $r['aluno_teste_id'];
}
unset($r);

// ── Já fizeram: realizada (lista plana com flags de ação) ─────────────────────
// ja_aluno = 1 se o email já existe em alunos ativos
// na_fila  = 1 se aluno_teste_id já está em fila_espera aguardando
$stmtJaFizeram = $pdo->query("
    SELECT
        ae.id, ae.criado_em,
        at.id   AS aluno_teste_id,
        at.nome, at.email, at.celular,
        t.id    AS turma_id, t.nome AS turma_nome, t.max_alunos,
        q.nome  AS quadra_nome,
        (SELECT COUNT(*) FROM turma_alunos ta
            WHERE ta.turma_id = t.id AND ta.status = 'ativo') AS alunos_ativos,
        CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END           AS ja_aluno,
        CASE WHEN fe.id IS NOT NULL THEN 1 ELSE 0 END          AS na_fila,
        fe_t.nome                                               AS fila_turma_nome
    FROM aulas_experimentais ae
    JOIN alunos_teste at  ON at.id  = ae.aluno_teste_id
    JOIN turmas t         ON t.id   = ae.turma_id
    JOIN quadras q        ON q.id   = t.quadra_id
    LEFT JOIN alunos a    ON a.email = at.email AND a.status = 'ativo'
    LEFT JOIN fila_espera fe   ON fe.aluno_teste_id = at.id AND fe.status = 'aguardando'
    LEFT JOIN turmas fe_t      ON fe_t.id = fe.turma_id
    WHERE ae.status = 'realizada'
    ORDER BY ja_aluno ASC, ae.criado_em DESC
");
$jaFizeram = $stmtJaFizeram->fetchAll(PDO::FETCH_ASSOC);

foreach ($jaFizeram as &$r) {
    $r['id']             = (int) $r['id'];
    $r['aluno_teste_id'] = (int) $r['aluno_teste_id'];
    $r['turma_id']       = (int) $r['turma_id'];
    $r['alunos_ativos']  = (int) $r['alunos_ativos'];
    $r['max_alunos']     = $r['max_alunos'] !== null ? (int) $r['max_alunos'] : null;
    $r['vagas']          = $r['max_alunos'] !== null
        ? max(0, $r['max_alunos'] - $r['alunos_ativos'])
        : null;
    $r['ja_aluno'] = (int) $r['ja_aluno'];
    $r['na_fila']  = (int) $r['na_fila'];
}
unset($r);

echo json_encode([
    'success'    => true,
    'para_fazer' => $paraFazer,
    'ja_fizeram' => $jaFizeram,
]);
