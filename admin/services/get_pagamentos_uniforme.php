<?php

/**
 * Pagamentos de uniforme, pra tela admin/pagamentos-uniformes.
 *
 * Uniforme é controle à parte: não passa por lancamentos_financeiros, justamente pra não
 * somar com a receita de mensalidades no Controle Financeiro. Por isso os números aqui saem
 * direto de pedidos_uniforme, que é a fonte da verdade do que foi vendido e recebido.
 */

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
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo = getDbConnection();

// Competência no formato Y-m. Vazio = todos os meses.
$mes = trim($_GET['mes'] ?? '');
if ($mes !== '' && !preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = '';
}

// O pagamento é datado por `pago_em`; o pedido manual nasce pago, então cai no mês em que
// o admin registrou. COALESCE com criado_em cobre linhas antigas sem pago_em.
$where  = ["p.status_pagamento = 'pago'"];
$params = [];
if ($mes !== '') {
    $where[]  = "DATE_FORMAT(COALESCE(p.pago_em, p.criado_em), '%Y-%m') = ?";
    $params[] = $mes;
}

$st = $pdo->prepare("
    SELECT p.id, p.aluno_id, p.genero, p.modelo, p.nome_camisa, p.numero,
           p.tamanho_camisa, p.tamanho_shorts, p.valor, p.status_pedido,
           p.mp_payment_id, p.mp_payment_method, p.mp_taxa_valor, p.mp_valor_liquido,
           p.criado_por_usuario_id, p.pago_em, p.criado_em,
           a.nome AS aluno_nome, a.email AS aluno_email,
           t.nome AS turma_nome,
           u.nome AS registrado_por
    FROM pedidos_uniforme p
    LEFT JOIN alunos   a ON a.id = p.aluno_id
    LEFT JOIN turmas   t ON t.id = p.turma_id
    LEFT JOIN usuarios u ON u.id = p.criado_por_usuario_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY COALESCE(p.pago_em, p.criado_em) DESC, p.id DESC
    LIMIT 500
");
$st->execute($params);

$pagamentos = [];
$totalBruto = 0.0;
$totalTaxa  = 0.0;

foreach ($st->fetchAll() as $r) {
    $valor = (float) $r['valor'];
    $taxa  = $r['mp_taxa_valor'] !== null ? (float) $r['mp_taxa_valor'] : null;
    $manual = !empty($r['criado_por_usuario_id']);

    $totalBruto += $valor;
    $totalTaxa  += ($taxa ?? 0.0);

    $quando = $r['pago_em'] ?: $r['criado_em'];

    $pagamentos[] = [
        'id'             => (int) $r['id'],
        'aluno_id'       => $r['aluno_id'] ? (int) $r['aluno_id'] : null,
        'aluno_nome'     => $r['aluno_nome'] ?: '(aluno removido)',
        'aluno_email'    => $r['aluno_email'],
        'turma'          => $r['turma_nome'],
        'genero'         => $r['genero'],
        'modelo'         => $r['modelo'],
        'nome_camisa'    => $r['nome_camisa'],
        'numero'         => $r['numero'] !== null ? (int) $r['numero'] : null,
        'tamanho_camisa' => $r['tamanho_camisa'],
        'tamanho_shorts' => $r['tamanho_shorts'],
        'valor'          => $valor,
        'taxa'           => $taxa,
        'liquido'        => $r['mp_valor_liquido'] !== null ? (float) $r['mp_valor_liquido'] : ($taxa !== null ? round($valor - $taxa, 2) : null),
        'status_pedido'  => $r['status_pedido'],
        'manual'         => $manual,
        'registrado_por' => $manual ? ($r['registrado_por'] ?: 'admin') : null,
        'forma'          => mpFormaPagamentoLabel($r['mp_payment_method'], false, $manual),
        'mp_payment_id'  => $r['mp_payment_id'],
        'pago_em'        => $quando ? (new DateTime($quando))->format('d/m/Y H:i') : null,
    ];
}

// Meses que têm pagamento, pro seletor da tela não oferecer mês vazio.
$meses = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(COALESCE(pago_em, criado_em), '%Y-%m') AS mes
    FROM pedidos_uniforme
    WHERE status_pagamento = 'pago'
    ORDER BY mes DESC
")->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'success'      => true,
    'pagamentos'   => $pagamentos,
    'total'        => count($pagamentos),
    'total_bruto'  => round($totalBruto, 2),
    'total_taxa'   => round($totalTaxa, 2),
    'total_liquido'=> round($totalBruto - $totalTaxa, 2),
    'meses'        => $meses,
    'mes'          => $mes,
]);
