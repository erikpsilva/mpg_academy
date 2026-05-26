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
$busca     = trim($_GET['busca'] ?? '');
$pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 20;
$offset    = ($pagina - 1) * $porPagina;

$where  = '';
$params = [];

if ($busca !== '') {
    $where  = 'WHERE q.nome LIKE ? OR q.cidade LIKE ?';
    $like   = '%' . $busca . '%';
    $params = [$like, $like];
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM quadras q $where");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

$totalGeral = (int) $pdo->query('SELECT COUNT(*) FROM quadras')->fetchColumn();

$stmt = $pdo->prepare("
    SELECT q.id, q.nome, q.telefone, q.cidade, q.estado, q.valor_mensal, q.status,
           COUNT(DISTINCT qh.id) AS total_horarios,
           COUNT(DISTINCT t.id)  AS total_turmas
    FROM quadras q
    LEFT JOIN quadra_horarios qh ON qh.quadra_id = q.id
    LEFT JOIN turmas t           ON t.quadra_id  = q.id
    $where
    GROUP BY q.id
    ORDER BY q.id DESC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'      => true,
    'total'        => $total,
    'totalGeral'   => $totalGeral,
    'pagina'       => $pagina,
    'totalPaginas' => (int) ceil($total / $porPagina),
    'registros'    => $registros,
]);
