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

$hoje = date('Y-m');
$mes  = preg_replace('/[^0-9\-]/', '', $_GET['mes'] ?? $hoje);
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = $hoje;

// não permite consultar meses futuros
if ($mes > $hoje) $mes = $hoje;

$primeiroDia = $mes . '-01';
$ultimoDia   = date('Y-m-t', strtotime($primeiroDia));
$aberto      = ($mes === $hoje);

// ── Entradas reais ─────────────────────────────────────────────────────────

// Mensalidades pagas no mês de referência
$stMens = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) AS total, COUNT(*) AS qtd
    FROM mensalidades
    WHERE referencia = ? AND status = 'pago'
");
$stMens->execute([$mes]);
$mensRow = $stMens->fetch(PDO::FETCH_ASSOC);

// Lançamentos de receita com competência no mês
$stRecLanc = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) FROM lancamentos_financeiros
    WHERE competencia = ? AND tipo = 'receita'
");
$stRecLanc->execute([$mes]);
$receitasLanc = (float)$stRecLanc->fetchColumn();

// ── Saídas reais ───────────────────────────────────────────────────────────

// Parcelas de dívidas pagas com vencimento no mês
$stParc = $pdo->prepare("
    SELECT pd.id, pd.numero, pd.valor, pd.data_vencimento, pd.status,
           d.descricao AS divida_nome
    FROM parcelas_dividas pd
    JOIN dividas d ON d.id = pd.divida_id
    WHERE pd.data_vencimento BETWEEN ? AND ?
      AND pd.status IN ('pago', 'adiantado')
    ORDER BY pd.data_vencimento ASC
");
$stParc->execute([$primeiroDia, $ultimoDia]);
$parcelasRows = $stParc->fetchAll(PDO::FETCH_ASSOC);
$totalParcelas = array_sum(array_column($parcelasRows, 'valor'));

// Lançamentos de despesa com competência no mês
$stDespLanc = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) FROM lancamentos_financeiros
    WHERE competencia = ? AND tipo = 'despesa'
");
$stDespLanc->execute([$mes]);
$despesasLanc = (float)$stDespLanc->fetchColumn();

// ── Totais ─────────────────────────────────────────────────────────────────
$totalEntradas = (float)$mensRow['total'] + $receitasLanc;
$totalSaidas   = $totalParcelas + $despesasLanc;
$saldo         = $totalEntradas - $totalSaidas;

echo json_encode([
    'success'  => true,
    'mes'      => $mes,
    'aberto'   => $aberto,
    'entradas' => [
        'mensalidades'       => (float)$mensRow['total'],
        'mensalidades_qtd'   => (int)$mensRow['qtd'],
        'lancamentos'        => $receitasLanc,
        'total'              => $totalEntradas,
    ],
    'saidas' => [
        'parcelas'        => $totalParcelas,
        'parcelas_detalhe'=> array_map(function($p) {
            return [
                'nome'       => $p['divida_nome'] . ' (parcela ' . $p['numero'] . ')',
                'valor'      => (float)$p['valor'],
                'vencimento' => $p['data_vencimento'],
            ];
        }, $parcelasRows),
        'lancamentos'     => $despesasLanc,
        'total'           => $totalSaidas,
    ],
    'saldo' => $saldo,
]);
