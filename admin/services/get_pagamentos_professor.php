<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) { http_response_code(403); exit; }

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';

if ($nivel === 'professor') {
    $profId = (int) $_SESSION['usuario']['professor_id'];
} else {
    $profId = (int) ($_GET['professor_id'] ?? 0);
}

if ($profId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__) . '/lib_pagamento_professor.php';
$pdo = getDbConnection();

$st = $pdo->prepare("
    SELECT id, competencia, valor, data_pagamento, referencia, observacao, comprovante, criado_em
    FROM professor_pagamentos
    WHERE professor_id = ?
    ORDER BY data_pagamento DESC, id DESC
");
$st->execute([$profId]);
$pagamentos = $st->fetchAll(PDO::FETCH_ASSOC);

if ($pagamentos) {
    $ids = array_column($pagamentos, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stAnexos = $pdo->prepare("
        SELECT id, pagamento_id, nome_original, caminho, tipo_mime
        FROM professor_pagamento_anexos WHERE pagamento_id IN ($in)
        ORDER BY criado_em ASC
    ");
    $stAnexos->execute($ids);
    $anexosPorPagamento = [];
    foreach ($stAnexos->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $anexosPorPagamento[$a['pagamento_id']][] = $a;
    }
    foreach ($pagamentos as &$p) {
        $p['anexos'] = $anexosPorPagamento[$p['id']] ?? [];
    }
    unset($p);
}

echo json_encode([
    'success'    => true,
    'pagamentos' => $pagamentos,
    'extrato'    => calcularExtratoMensal($pdo, $profId),
]);
