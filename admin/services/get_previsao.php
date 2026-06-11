<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

function sanitizeMes($val, $default) {
    $v = preg_replace('/[^0-9\-]/', '', $val ?? $default);
    return preg_match('/^\d{4}-\d{2}$/', $v) ? $v : $default;
}

$hoje    = date('Y-m');
$mesDe   = sanitizeMes($_GET['mes_de'] ?? null, $hoje);
$mesAte  = sanitizeMes($_GET['mes_ate'] ?? null, $mesDe);

// garante que mesDe <= mesAte
if ($mesDe > $mesAte) { $tmp = $mesDe; $mesDe = $mesAte; $mesAte = $tmp; }

// gera lista de meses no intervalo
$meses = [];
$cur = strtotime($mesDe . '-01');
$fim = strtotime($mesAte . '-01');
while ($cur <= $fim) {
    $meses[] = date('Y-m', $cur);
    $cur = strtotime('+1 month', $cur);
}
$numMeses    = count($meses);
$primeiroDia = $mesDe . '-01';
$ultimoDia   = date('Y-m-t', strtotime($mesAte . '-01'));

// ── 1. Mensalidades (todos os meses do intervalo) ──────────────────────────
$inPlaceholders = implode(',', array_fill(0, $numMeses, '?'));
$stMens = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'pago'     THEN valor ELSE 0 END) AS pagas,
        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) AS pendentes,
        SUM(CASE WHEN status = 'atrasado' THEN valor ELSE 0 END) AS atrasadas,
        COUNT(*) AS qtd
    FROM mensalidades
    WHERE referencia IN ($inPlaceholders)
");
$stMens->execute($meses);
$mens = $stMens->fetch(PDO::FETCH_ASSOC);

// ── 2. Patrocínios (recorrente × nº meses) ────────────────────────────────
$patrocinioMensal = (float)$pdo->query("
    SELECT COALESCE(SUM(valor_patrocinio), 0) FROM patrocinadores WHERE status = 'ativo'
")->fetchColumn();
$patrocinios = $patrocinioMensal * $numMeses;

// ── 3. Lançamentos receita no intervalo ───────────────────────────────────
$stRecLanc = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) FROM lancamentos_financeiros
    WHERE competencia IN ($inPlaceholders) AND tipo = 'receita'
");
$stRecLanc->execute($meses);
$receitasLanc = (float)$stRecLanc->fetchColumn();

// ── 4. Aluguel das quadras ativas (× nº meses) ────────────────────────────
$aluguelMensal = (float)$pdo->query("
    SELECT COALESCE(SUM(valor_mensal), 0) FROM quadras WHERE status = 'ativa'
")->fetchColumn();
$aluguelQuadras = $aluguelMensal * $numMeses;

// ── 5. Salários + adicionais de professores ativos (× nº meses) ──────────
$salarioMensal = (float)$pdo->query("
    SELECT COALESCE(SUM(COALESCE(salario,0) + COALESCE(bonus_valor,0)), 0)
    FROM professores WHERE status = 'ativo'
")->fetchColumn();
$salarios = $salarioMensal * $numMeses;

// ── 6. Parcelas de dívidas no intervalo ───────────────────────────────────
$stParc = $pdo->prepare("
    SELECT
        pd.id, pd.numero, pd.valor, pd.data_vencimento, pd.status,
        d.descricao AS divida_nome
    FROM parcelas_dividas pd
    JOIN dividas d ON d.id = pd.divida_id
    WHERE pd.data_vencimento BETWEEN ? AND ?
    ORDER BY pd.data_vencimento ASC
");
$stParc->execute([$primeiroDia, $ultimoDia]);
$parcelasRows = $stParc->fetchAll(PDO::FETCH_ASSOC);

$parcelasPendentes = 0;
$parcelasPagas     = 0;
foreach ($parcelasRows as $p) {
    if ($p['status'] === 'pago' || $p['status'] === 'adiantado') $parcelasPagas     += $p['valor'];
    else                                                           $parcelasPendentes += $p['valor'];
}

// ── 7. Lançamentos despesa no intervalo ───────────────────────────────────
$stDespLanc = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) FROM lancamentos_financeiros
    WHERE competencia IN ($inPlaceholders) AND tipo = 'despesa'
");
$stDespLanc->execute($meses);
$despesasLanc = (float)$stDespLanc->fetchColumn();

// ── Totais ─────────────────────────────────────────────────────────────────
$mensPagas     = (float)$mens['pagas'];
$mensPendentes = (float)$mens['pendentes'];
$mensAtrasadas = (float)$mens['atrasadas'];

$totalEntradas        = $mensPagas + $mensPendentes + $mensAtrasadas + $patrocinios + $receitasLanc;
$totalEntradasConfirm = $mensPagas + $patrocinios + $receitasLanc;
$totalSaidas          = $aluguelQuadras + $salarios + $parcelasPendentes + $parcelasPagas + $despesasLanc;
$saldo                = $totalEntradas - $totalSaidas;
$saldoConfirmado      = $totalEntradasConfirm - $totalSaidas;

echo json_encode([
    'success'   => true,
    'mes_de'    => $mesDe,
    'mes_ate'   => $mesAte,
    'num_meses' => $numMeses,
    'entradas'  => [
        'mensalidades_pagas'     => $mensPagas,
        'mensalidades_pendentes' => $mensPendentes,
        'mensalidades_atrasadas' => $mensAtrasadas,
        'mensalidades_qtd'       => (int)$mens['qtd'],
        'patrocinios'            => $patrocinios,
        'lancamentos'            => $receitasLanc,
        'total_confirmado'       => $totalEntradasConfirm,
        'total'                  => $totalEntradas,
    ],
    'saidas' => [
        'aluguel_quadras'    => $aluguelQuadras,
        'salarios'           => $salarios,
        'parcelas_pendentes' => $parcelasPendentes,
        'parcelas_pagas'     => $parcelasPagas,
        'parcelas_detalhe'   => array_map(function($p) {
            return [
                'nome'       => $p['divida_nome'] . ' (parcela ' . $p['numero'] . ')',
                'valor'      => (float)$p['valor'],
                'vencimento' => $p['data_vencimento'],
                'status'     => $p['status'],
            ];
        }, $parcelasRows),
        'lancamentos'        => $despesasLanc,
        'total'              => $totalSaidas,
    ],
    'saldo'            => $saldo,
    'saldo_confirmado' => $saldoConfirmado,
]);
