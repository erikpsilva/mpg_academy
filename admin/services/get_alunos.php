<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$pdo       = getDbConnection();
$busca     = trim($_GET['busca']    ?? '');
$turmaId   = (int) ($_GET['turma_id'] ?? 0);
$pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 20;
$offset    = ($pagina - 1) * $porPagina;

$conditions = [];
$params     = [];

if ($busca !== '') {
    $conditions[] = '(a.nome LIKE ? OR a.email LIKE ?)';
    $like         = '%' . $busca . '%';
    $params[]     = $like;
    $params[]     = $like;
}

if ($turmaId > 0) {
    $conditions[] = 'EXISTS (SELECT 1 FROM turma_alunos ta2 WHERE ta2.aluno_id = a.id AND ta2.turma_id = ? AND ta2.status = \'ativo\')';
    $params[]     = $turmaId;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmtTotal = $pdo->prepare("SELECT COUNT(DISTINCT a.id) FROM alunos a $where");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

$totalGeral = (int) $pdo->query('SELECT COUNT(*) FROM alunos')->fetchColumn();

$efetivo = "
    IF(t.valor_mensalidade IS NULL, 0,
        IF(ta.desconto IS NOT NULL AND ta.desconto > 0 AND (
               ta.desconto_vitalicio = 1
               OR (ta.desconto_inicio IS NULL AND ta.desconto_fim IS NULL)
               OR (ta.desconto_inicio <= CURDATE() AND ta.desconto_fim >= CURDATE())
           ),
           IF(ta.desconto_tipo = 'percentual',
              t.valor_mensalidade * (1 - ta.desconto / 100),
              GREATEST(0, t.valor_mensalidade - ta.desconto)
           ),
           IF(t.promo_valor IS NOT NULL AND t.promo_meses IS NOT NULL
              AND t.promo_valor < t.valor_mensalidade
              AND DATE_ADD(ta.data_entrada, INTERVAL t.promo_meses MONTH) >= CURDATE(),
              t.promo_valor,
              t.valor_mensalidade
           )
        )
    )";

$stmt = $pdo->prepare("
    SELECT a.id, a.nome, a.email, a.status, a.criado_em,
           GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ', ') AS turmas_nomes,
           SUM(COALESCE(t.valor_mensalidade, 0)) AS mensalidade_base,
           SUM($efetivo) AS mensalidade_total,
           GROUP_CONCAT(
               CONCAT(
                   REPLACE(REPLACE(t.nome, '~', '-'), '|', '-'), '~',
                   ROUND($efetivo, 2), '~',
                   ROUND(COALESCE(t.valor_mensalidade, 0), 2)
               )
               ORDER BY t.nome SEPARATOR '|'
           ) AS mensalidade_detalhes
    FROM alunos a
    LEFT JOIN turma_alunos ta ON ta.aluno_id = a.id AND ta.status = 'ativo'
    LEFT JOIN turmas t ON t.id = ta.turma_id
    $where
    GROUP BY a.id
    ORDER BY a.nome ASC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'      => true,
    'total'        => $total,
    'totalGeral'   => $totalGeral,
    'pagina'       => $pagina,
    'porPagina'    => $porPagina,
    'totalPaginas' => max(1, (int) ceil($total / $porPagina)),
    'registros'    => $registros,
]);
