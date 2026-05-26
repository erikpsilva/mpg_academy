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

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$busca  = trim($_GET['busca'] ?? '');
$limite = 20;
$offset = ($pagina - 1) * $limite;

$where  = $busca ? "WHERE (t.nome LIKE :b OR q.nome LIKE :b2)" : "";
$params = $busca ? [':b' => "%$busca%", ':b2' => "%$busca%"] : [];

$totalStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT t.id)
    FROM turmas t
    LEFT JOIN quadras q ON q.id = t.quadra_id
    $where
");
$totalStmt->execute($params);
$totalReg = (int) $totalStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT t.id, t.nome, t.valor_mensalidade, t.data_inicio, t.status,
           q.id AS quadra_id, q.nome AS quadra_nome, q.cidade, q.estado,
           COUNT(DISTINCT ta.aluno_id) AS total_alunos,
           GROUP_CONCAT(DISTINCT qh.dia_semana ORDER BY qh.dia_semana SEPARATOR ',') AS dias_semana
    FROM turmas t
    LEFT JOIN quadras q ON q.id = t.quadra_id
    LEFT JOIN turma_horarios th ON th.turma_id = t.id
    LEFT JOIN quadra_horarios qh ON qh.id = th.horario_id
    LEFT JOIN turma_alunos ta ON ta.turma_id = t.id AND ta.status = 'ativo'
    $where
    GROUP BY t.id
    ORDER BY q.nome, t.nome
    LIMIT $limite OFFSET $offset
");
$stmt->execute($params);
$turmas = $stmt->fetchAll();

$dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
foreach ($turmas as &$t) {
    $labels = [];
    if ($t['dias_semana']) {
        foreach (explode(',', $t['dias_semana']) as $d) {
            $labels[] = $dias[(int) $d];
        }
    }
    $t['dias_label']        = implode(' / ', $labels);
    $t['valor_mensalidade'] = $t['valor_mensalidade'] !== null ? (float) $t['valor_mensalidade'] : null;
    $t['total_alunos']      = (int) $t['total_alunos'];
    unset($t['dias_semana']);
}

echo json_encode([
    'success'      => true,
    'total'        => $totalReg,
    'totalGeral'   => $totalReg,
    'pagina'       => $pagina,
    'totalPaginas' => max(1, (int) ceil($totalReg / $limite)),
    'registros'    => $turmas,
]);
