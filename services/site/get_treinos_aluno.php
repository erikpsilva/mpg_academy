<?php
require_once __DIR__ . '/mobile_auth.php';
$aluno = mobileAuth();

$pdo  = getDbConnection();

// Busca treinos da turma do aluno com horário da turma
$stmt = $pdo->prepare("
    SELECT
        tt.id,
        tt.data_treino  AS data,
        tt.status,
        tt.observacao,
        t.nome          AS turma_nome,
        q.nome          AS quadra_nome,
        DAYNAME(tt.data_treino) AS dia_semana_en,
        MIN(qh.hora_inicio)     AS hora_inicio,
        MIN(qh.hora_fim)        AS hora_fim
    FROM turma_treinos tt
    JOIN turmas t       ON t.id  = tt.turma_id
    JOIN quadras q      ON q.id  = t.quadra_id
    JOIN turma_alunos ta ON ta.turma_id = tt.turma_id
                        AND ta.aluno_id = ?
                        AND ta.status   = 'ativo'
    LEFT JOIN turma_horarios th  ON th.turma_id  = tt.turma_id
    LEFT JOIN quadra_horarios qh ON qh.id         = th.horario_id
    WHERE tt.data_treino >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY tt.id, tt.data_treino, tt.status, tt.observacao, t.nome, q.nome
    ORDER BY tt.data_treino ASC
    LIMIT 60
");
$stmt->execute([$aluno['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dias = [
    'Monday'    => 'Segunda',
    'Tuesday'   => 'Terça',
    'Wednesday' => 'Quarta',
    'Thursday'  => 'Quinta',
    'Friday'    => 'Sexta',
    'Saturday'  => 'Sábado',
    'Sunday'    => 'Domingo',
];

foreach ($rows as &$r) {
    $r['dia_semana'] = $dias[$r['dia_semana_en']] ?? $r['dia_semana_en'];
    $r['hora_inicio'] = $r['hora_inicio'] ?? '—';
    $r['hora_fim']    = $r['hora_fim']    ?? '—';
    unset($r['dia_semana_en']);
}

echo json_encode(['success' => true, 'data' => $rows]);
