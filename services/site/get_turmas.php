<?php

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT t.id, t.nome, t.nivel, t.genero, t.valor_mensalidade, t.promo_valor, t.promo_meses,
           q.nome AS quadra_nome,
           CONCAT(q.rua, ', ', q.numero, ' – ', q.bairro, ', ', q.cidade, '/', q.estado) AS quadra_endereco,
           GROUP_CONCAT(
               CONCAT(qh.dia_semana, '~', qh.hora_inicio, '~', qh.hora_fim)
               ORDER BY qh.dia_semana, qh.hora_inicio
               SEPARATOR '|'
           ) AS horarios_raw
    FROM turmas t
    JOIN quadras q ON q.id = t.quadra_id
    LEFT JOIN turma_horarios th ON th.turma_id = t.id
    LEFT JOIN quadra_horarios qh ON qh.id = th.horario_id
    WHERE t.status = 'ativa'
    GROUP BY t.id
    ORDER BY
        COUNT(DISTINCT qh.dia_semana) DESC,
        MIN(qh.hora_inicio) ASC,
        FIELD(t.nivel, 'iniciante', 'intermediario', 'avancado'),
        t.nome
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$t) {
    if ($t['horarios_raw']) {
        $t['horarios'] = array_map(function ($h) {
            [$dia, $inicio, $fim] = explode('~', $h, 3);
            return ['dia_semana' => (int) $dia, 'hora_inicio' => $inicio, 'hora_fim' => $fim];
        }, explode('|', $t['horarios_raw']));
    } else {
        $t['horarios'] = [];
    }
    unset($t['horarios_raw']);

    $t['valor_mensalidade'] = $t['valor_mensalidade'] !== null ? (float) $t['valor_mensalidade'] : null;
    $t['promo_valor']       = $t['promo_valor']       !== null ? (float) $t['promo_valor']       : null;
    $t['promo_meses']       = $t['promo_meses']       !== null ? (int)   $t['promo_meses']       : null;
}

echo json_encode(['success' => true, 'turmas' => $rows]);
