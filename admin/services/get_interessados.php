<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $where    = 'WHERE nome_completo LIKE ? OR email LIKE ? OR celular LIKE ?';
    $like     = '%' . $busca . '%';
    $params   = [$like, $like, $like];
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM site_interessados $where");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

$stmtTotal2 = $pdo->query('SELECT COUNT(*) FROM site_interessados');
$totalGeral = (int) $stmtTotal2->fetchColumn();

$stmt = $pdo->prepare("
    SELECT id, nome_completo, email, celular, created_at
    FROM site_interessados
    $where
    ORDER BY id DESC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'     => true,
    'total'       => $total,
    'totalGeral'  => $totalGeral,
    'pagina'      => $pagina,
    'porPagina'   => $porPagina,
    'totalPaginas' => (int) ceil($total / $porPagina),
    'registros'   => $registros,
]);
