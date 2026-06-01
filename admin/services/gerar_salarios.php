<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$competencia = trim($_POST['competencia'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Busca professores ativos com salário definido
$professores = $pdo->query("
    SELECT id, nome, sobrenome, salario, dia_pagamento
    FROM professores
    WHERE status = 'ativo' AND salario IS NOT NULL AND salario > 0
")->fetchAll();

$gerados = 0;
$pulados = 0;

$stCheck = $pdo->prepare("
    SELECT id FROM lancamentos_financeiros
    WHERE referencia_tipo = 'salario' AND referencia_id = ? AND competencia = ?
");
$stIns = $pdo->prepare("
    INSERT INTO lancamentos_financeiros
        (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
    VALUES (?, ?, 'despesa', 'salario', ?, ?, 'auto', 'salario', ?)
");

foreach ($professores as $p) {
    $stCheck->execute([$p['id'], $competencia]);
    if ($stCheck->fetchColumn()) { $pulados++; continue; }

    // Data de pagamento = dia_pagamento do mês de competência (ou último dia se o mês for curto)
    $diaPgto = $p['dia_pagamento'] ?: 5;
    [$ano, $mes] = explode('-', $competencia);
    $diasNoMes   = (int)(new DateTime($competencia . '-01'))->format('t');
    $dia         = min($diaPgto, $diasNoMes);
    $data        = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

    $desc = 'Salário — ' . $p['nome'] . ' ' . $p['sobrenome'];
    try {
        $stIns->execute([$competencia, $data, $desc, (float)$p['salario'], $p['id']]);
        $gerados++;
    } catch (PDOException) { /* já existe */ }
}

echo json_encode([
    'success' => true,
    'gerados' => $gerados,
    'pulados' => $pulados,
    'mensagem' => $gerados . ' salário(s) gerado(s)' . ($pulados ? ', ' . $pulados . ' já existia(m).' : '.'),
]);
