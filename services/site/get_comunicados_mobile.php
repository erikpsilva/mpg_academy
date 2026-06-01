<?php
require_once __DIR__ . '/mobile_auth.php';
$aluno = mobileAuth();

$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$pdo   = getDbConnection();

$stmt = $pdo->prepare("
    SELECT id, titulo, conteudo, imagem, tag, destaque, criado_em
    FROM comunicados
    WHERE publicado = 1
    ORDER BY destaque DESC, criado_em DESC
    LIMIT {$limit}
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['id']       = (int)  $r['id'];
    $r['destaque'] = (bool) $r['destaque'];
}

echo json_encode(['success' => true, 'data' => $rows]);
