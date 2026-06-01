<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

// ── Parâmetros ────────────────────────────────────────────────────────────────
$aba = $_GET['aba'] ?? 'dashboard';
$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

$dtMes    = new DateTime($mes . '-01');
$prevMes  = (clone $dtMes)->modify('-1 month')->format('Y-m');
$nextMes  = (clone $dtMes)->modify('+1 month')->format('Y-m');
$ehFuturo = $nextMes > date('Y-m');

$mesesPT = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',
            '05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',
            '09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$mesLabel = $mesesPT[$dtMes->format('m')] . ' de ' . $dtMes->format('Y');

$catLabels = [
    'mensalidade'    => 'Mensalidades',
    'salario'        => 'Salários',
    'parcela_divida' => 'Parcelas de Dívidas',
    'aluguel'        => 'Aluguel',
    'material'       => 'Material Esportivo',
    'marketing'      => 'Marketing',
    'administrativo' => 'Administrativo',
    'patrocinio'     => 'Patrocínios',
    'outros_receita' => 'Outras Receitas',
    'outros'         => 'Outros',
];

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtR(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}
function catLabel(string $cat, array $labels): string {
    return $labels[$cat] ?? ucfirst(str_replace('_', ' ', $cat));
}

// ── Sync automático: TODAS as mensalidades pagas → lançamentos ───────────────
// Roda sem filtro de mês — garante que nenhum pagamento histórico fique faltando.
function syncMensalidades(PDO $pdo): void {
    $st = $pdo->query("
        SELECT m.id, m.valor, m.data_pagamento, m.referencia,
               a.nome AS aluno_nome, t.nome AS turma_nome
        FROM mensalidades m
        JOIN alunos a ON a.id = m.aluno_id
        JOIN turmas t ON t.id = m.turma_id
        WHERE m.status = 'pago'
          AND m.data_pagamento IS NOT NULL
          AND NOT EXISTS (
              SELECT 1 FROM lancamentos_financeiros lf
              WHERE lf.referencia_tipo = 'mensalidade' AND lf.referencia_id = m.id
          )
    ");
    $ins = $pdo->prepare("
        INSERT IGNORE INTO lancamentos_financeiros
            (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
        VALUES (?, ?, 'receita', 'mensalidade', ?, ?, 'auto', 'mensalidade', ?)
    ");
    $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
    foreach ($st->fetchAll() as $m) {
        // Competência = mês em que o pagamento foi recebido (data_pagamento)
        $competencia = substr($m['data_pagamento'], 0, 7);
        [$a, $me]    = explode('-', $m['referencia']);
        $refLabel    = ($meses[$me] ?? $me) . '/' . $a;
        $desc = 'Mensalidade ' . $refLabel . ' — ' . $m['aluno_nome'] . ' (' . $m['turma_nome'] . ')';
        try { $ins->execute([$competencia, $m['data_pagamento'], $desc, $m['valor'], $m['id']]); }
        catch (PDOException) {}
    }
}

// ── Dashboard: carrega dados ──────────────────────────────────────────────────
$dashboard = [];
if ($aba === 'dashboard') {
    syncMensalidades($pdo);

    $stLanc = $pdo->prepare("
        SELECT tipo, categoria, SUM(valor) AS total
        FROM lancamentos_financeiros
        WHERE competencia = ?
        GROUP BY tipo, categoria
        ORDER BY tipo DESC, total DESC
    ");
    $stLanc->execute([$mes]);
    $totais = $stLanc->fetchAll();

    $dashboard['receitas']  = [];
    $dashboard['despesas']  = [];
    $dashboard['totalRec']  = 0;
    $dashboard['totalDesp'] = 0;

    foreach ($totais as $t) {
        if ($t['tipo'] === 'receita') {
            $dashboard['receitas'][$t['categoria']] = (float)$t['total'];
            $dashboard['totalRec'] += (float)$t['total'];
        } else {
            $dashboard['despesas'][$t['categoria']] = (float)$t['total'];
            $dashboard['totalDesp'] += (float)$t['total'];
        }
    }
    $dashboard['resultado'] = $dashboard['totalRec'] - $dashboard['totalDesp'];

    // Verificar se salários já foram gerados
    $stSal = $pdo->prepare("
        SELECT COUNT(*) FROM lancamentos_financeiros
        WHERE competencia = ? AND categoria = 'salario' AND origem = 'auto'
    ");
    $stSal->execute([$mes]);
    $dashboard['salariosGerados'] = (int)$stSal->fetchColumn() > 0;

    // Últimos 8 lançamentos do mês
    $stRec = $pdo->prepare("
        SELECT * FROM lancamentos_financeiros WHERE competencia = ? ORDER BY data DESC, id DESC LIMIT 8
    ");
    $stRec->execute([$mes]);
    $dashboard['lancamentos'] = $stRec->fetchAll();
}

// ── Lançamentos: lista ────────────────────────────────────────────────────────
$lancamentos = [];
if ($aba === 'lancamentos') {
    syncMensalidades($pdo);
    $stL = $pdo->prepare("
        SELECT * FROM lancamentos_financeiros WHERE competencia = ? ORDER BY data DESC, id DESC
    ");
    $stL->execute([$mes]);
    $lancamentos = $stL->fetchAll();
}

// ── Dívidas: lista ────────────────────────────────────────────────────────────
$dividas = [];
if ($aba === 'dividas') {
    $dividas = $pdo->query("
        SELECT d.*,
               (SELECT COUNT(*) FROM parcelas_dividas WHERE divida_id=d.id) AS total_parcelas,
               (SELECT COUNT(*) FROM parcelas_dividas WHERE divida_id=d.id AND status!='pendente') AS pagas,
               (SELECT SUM(valor) FROM parcelas_dividas WHERE divida_id=d.id AND status!='pendente') AS total_pago
        FROM dividas d ORDER BY d.criado_em DESC
    ")->fetchAll();
}

// ── Dívida: detalhe ───────────────────────────────────────────────────────────
$dividaDetalhe = null;
$parcelas      = [];
if ($aba === 'divida') {
    $divId = (int)($_GET['id'] ?? 0);
    $stD = $pdo->prepare("SELECT * FROM dividas WHERE id = ?");
    $stD->execute([$divId]);
    $dividaDetalhe = $stD->fetch();
    if (!$dividaDetalhe) { header('Location: ' . ADMIN_BASE_URL . '/financeiro?aba=dividas'); exit; }
    $stP = $pdo->prepare("SELECT * FROM parcelas_dividas WHERE divida_id = ? ORDER BY numero");
    $stP->execute([$divId]);
    $parcelas = $stP->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Financeiro</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<style>
/* ── Tabs ── */
.finTabs { display:flex; gap:4px; margin-bottom:28px; border-bottom:2px solid #1e1e1e; padding-bottom:0; }
.finTab  { padding:10px 20px; border-radius:8px 8px 0 0; font-size:13px; font-weight:700;
           color:#666; text-decoration:none; margin-bottom:-2px; border-bottom:2px solid transparent;
           transition:.15s; }
.finTab:hover  { color:#ddd; }
.finTab.active { color:#e5c200; border-bottom-color:#e5c200; }

/* ── Mês selector ── */
.finMes { display:flex; align-items:center; gap:14px; margin-bottom:24px; }
.finMes a { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;
            background:#1a1a1a; border:1px solid #2a2a2a; border-radius:6px; color:#aaa;
            text-decoration:none; font-size:16px; transition:.15s; }
.finMes a:hover { border-color:#e5c200; color:#e5c200; }
.finMes strong  { font-size:17px; color:#eee; min-width:200px; text-align:center; }

/* ── Cards de resumo ── */
.finCards { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:28px; }
.finCard  { background:#111; border:1px solid #2a2a2a; border-radius:10px; padding:20px 22px; }
.finCard__label  { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#666; margin-bottom:8px; }
.finCard__valor  { font-size:26px; font-weight:900; }
.finCard--rec    { border-left:3px solid #79ff45; }
.finCard--rec .finCard__valor  { color:#79ff45; }
.finCard--desp   { border-left:3px solid #ff5a5a; }
.finCard--desp .finCard__valor { color:#ff5a5a; }
.finCard--res    .finCard__valor { }
.finCard--pos    { border-left:3px solid #79ff45; }
.finCard--pos .finCard__valor  { color:#79ff45; }
.finCard--neg    { border-left:3px solid #ff5a5a; }
.finCard--neg .finCard__valor  { color:#ff5a5a; }

/* ── DRE ── */
.finDRE { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; }
.finDREBlock { background:#111; border:1px solid #2a2a2a; border-radius:10px; overflow:hidden; }
.finDREBlock__head { padding:14px 20px; border-bottom:1px solid #1e1e1e;
                     font-size:12px; font-weight:900; text-transform:uppercase;
                     letter-spacing:.08em; color:#888; }
.finDREBlock__head--rec  { color:#79ff45; }
.finDREBlock__head--desp { color:#ff7070; }
.finDRERow { display:flex; justify-content:space-between; align-items:center;
             padding:11px 20px; border-bottom:1px solid #141414; font-size:13px; color:#ccc; }
.finDRERow:last-child { border-bottom:0; }
.finDRERow strong { color:#eee; }
.finDRERow--total { background:#0e0e0e; font-weight:700; font-size:14px; color:#eee; padding:14px 20px; }

/* ── Tabela genérica ── */
.finTable { width:100%; border-collapse:collapse; font-size:13px; }
.finTable th { background:#1a1a1a; color:#666; font-size:11px; text-transform:uppercase;
               letter-spacing:.05em; padding:12px 16px; text-align:left; font-weight:700; }
.finTable td { padding:12px 16px; border-bottom:1px solid #1a1a1a; color:#ccc; vertical-align:middle; }
.finTable tr:last-child td { border-bottom:0; }
.finTable tr:hover td { background:rgba(255,255,255,.02); }
.finTable__wrap { background:#111; border:1px solid #2a2a2a; border-radius:10px; overflow:hidden; }
.badge-rec  { background:rgba(46,182,16,.1); border:1px solid rgba(116,255,54,.3); color:#79ff45;
              font-size:10px; font-weight:900; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
.badge-desp { background:rgba(255,45,45,.1); border:1px solid rgba(255,45,45,.3); color:#ff7070;
              font-size:10px; font-weight:900; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
.badge-auto { background:rgba(90,90,90,.2); border:1px solid #333; color:#666;
              font-size:10px; font-weight:700; padding:2px 7px; border-radius:4px; text-transform:uppercase; }
.badge-pend { background:rgba(255,140,0,.1); border:1px solid rgba(255,140,0,.3); color:#ff9a1e;
              font-size:10px; font-weight:900; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
.badge-pago { background:rgba(46,182,16,.1); border:1px solid rgba(116,255,54,.3); color:#79ff45;
              font-size:10px; font-weight:900; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
.badge-adi  { background:rgba(100,100,255,.1); border:1px solid rgba(100,100,255,.3); color:#8080ff;
              font-size:10px; font-weight:900; padding:2px 8px; border-radius:4px; text-transform:uppercase; }

/* ── Parcelas progress ── */
.parcProgress { height:6px; background:#1e1e1e; border-radius:3px; overflow:hidden; margin-top:4px; }
.parcProgress__bar { height:100%; background:#e5c200; border-radius:3px; }

/* ── Modal ── */
.finModal { display:none; position:fixed; inset:0; z-index:9000; align-items:center;
            justify-content:center; background:rgba(0,0,0,.7); backdrop-filter:blur(4px); padding:16px; }
.finModal.open { display:flex; }
.finModal__box { background:#141414; border:1px solid #2a2a2a; border-radius:12px;
                 width:100%; max-width:520px; max-height:90vh; overflow-y:auto; }
.finModal__head { display:flex; align-items:center; justify-content:space-between;
                  padding:18px 22px; border-bottom:1px solid #1e1e1e; }
.finModal__head h3 { margin:0; font-size:17px; }
.finModal__head button { background:none; border:none; color:#666; font-size:22px;
                         cursor:pointer; line-height:1; padding:0; }
.finModal__head button:hover { color:#eee; }
.finModal__body { padding:22px; }
.finField { margin-bottom:16px; }
.finField label { display:block; font-size:12px; color:#888; margin-bottom:6px;
                  text-transform:uppercase; letter-spacing:.05em; }
.finField label span { color:#e53535; }
.finField .input { width:100%; }
.finRow2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.finRow3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
.finModalActions { display:flex; gap:10px; justify-content:flex-end; padding:16px 22px;
                   border-top:1px solid #1e1e1e; }

/* ── Barra de info dívida ── */
.dividaInfo { background:#111; border:1px solid #2a2a2a; border-radius:10px;
              padding:20px 24px; margin-bottom:22px; display:grid;
              grid-template-columns:repeat(4,1fr); gap:16px; }
.dividaInfo dt { font-size:11px; color:#666; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
.dividaInfo dd { font-size:16px; font-weight:700; color:#eee; margin:0; }
</style>
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

    <?php if ($aba === 'divida' && $dividaDetalhe): ?>
    <!-- ── DETALHE DA DÍVIDA ──────────────────────────────────────────────── -->
    <section class="alunos">
        <div class="alunos__header row" style="margin-bottom:20px;">
            <div class="col-md-12">
                <a href="<?= ADMIN_BASE_URL ?>/financeiro?aba=dividas" class="alunos__back">&#8592; Voltar para Dívidas</a>
                <h2><?= htmlspecialchars($dividaDetalhe['descricao']) ?></h2>
            </div>
        </div>

        <dl class="dividaInfo">
            <div><dt>Credor</dt><dd><?= htmlspecialchars($dividaDetalhe['credor'] ?: '—') ?></dd></div>
            <div><dt>Valor total</dt><dd><?= fmtR((float)$dividaDetalhe['valor_total']) ?></dd></div>
            <div><dt>Parcelas</dt><dd><?= count($parcelas) ?>x de <?= fmtR((float)$dividaDetalhe['valor_parcela']) ?></dd></div>
            <div><dt>Status</dt><dd><?= $dividaDetalhe['status'] === 'quitado' ? '✅ Quitada' : '⏳ Em aberto' ?></dd></div>
        </dl>

        <div class="finTable__wrap">
            <table class="finTable">
                <thead>
                    <tr>
                        <th>#</th><th>Vencimento</th><th>Valor</th>
                        <th>Pagamento</th><th>Status</th><th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($parcelas as $p):
                    $venc = new DateTime($p['data_vencimento']);
                    $isAdiantavel = $p['status'] === 'pendente' && $venc > new DateTime('today');
                    $isAtrasado   = $p['status'] === 'pendente' && $venc < new DateTime('today');
                ?>
                <tr>
                    <td><strong><?= $p['numero'] ?>/<?= count($parcelas) ?></strong></td>
                    <td><?= $venc->format('d/m/Y') ?><?= $isAtrasado ? ' <span style="color:#ff7070;font-size:11px;">atrasada</span>' : '' ?></td>
                    <td><?= fmtR((float)$p['valor']) ?></td>
                    <td><?= $p['data_pagamento'] ? date('d/m/Y', strtotime($p['data_pagamento'])) : '—' ?></td>
                    <td>
                        <?php if ($p['status'] === 'pago'):     ?><span class="badge-pago">Pago</span><?php
                        elseif ($p['status'] === 'adiantado'):  ?><span class="badge-adi">Adiantado</span><?php
                        elseif ($isAtrasado):                   ?><span class="badge-desp">Atrasada</span><?php
                        else:                                   ?><span class="badge-pend">Pendente</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'pendente'): ?>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn--sm btn--primary btnPagarParcela"
                                    data-id="<?= $p['id'] ?>" data-tipo="pago"
                                    data-label="parcela <?= $p['numero'] ?>">
                                Pagar
                            </button>
                            <?php if ($isAdiantavel): ?>
                            <button class="btn btn--sm btn--gray btnPagarParcela"
                                    data-id="<?= $p['id'] ?>" data-tipo="adiantado"
                                    data-label="parcela <?= $p['numero'] ?>">
                                Adiantar
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php else: ?>
    <!-- ── HEADER + TABS ─────────────────────────────────────────────────── -->
    <section class="alunos">
        <div class="row alunos__header" style="margin-bottom:20px;">
            <div class="col-md-12">
                <h2>Contro<span>le Financeiro</span></h2>
                <p>DRE, lançamentos e dívidas da MPG Academy.</p>
            </div>
        </div>

        <nav class="finTabs">
            <a href="<?= ADMIN_BASE_URL ?>/financeiro?aba=dashboard&mes=<?= $mes ?>"
               class="finTab <?= $aba === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= ADMIN_BASE_URL ?>/financeiro?aba=lancamentos&mes=<?= $mes ?>"
               class="finTab <?= $aba === 'lancamentos' ? 'active' : '' ?>">Lançamentos</a>
            <a href="<?= ADMIN_BASE_URL ?>/financeiro?aba=dividas"
               class="finTab <?= $aba === 'dividas' ? 'active' : '' ?>">Dívidas</a>
        </nav>

        <!-- ── Seletor de mês ── -->
        <?php if ($aba !== 'dividas'): ?>
        <div class="finMes">
            <a href="?aba=<?= $aba ?>&mes=<?= $prevMes ?>">&#8592;</a>
            <strong><?= $mesLabel ?></strong>
            <a href="?aba=<?= $aba ?>&mes=<?= $nextMes ?>" <?= $ehFuturo ? 'style="opacity:.35;pointer-events:none;"' : '' ?>>&#8594;</a>
        </div>
        <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php if ($aba === 'dashboard'): ?>
    <!-- ── DASHBOARD ──────────────────────────────────────────────────── -->

        <!-- Cards de resumo -->
        <div class="finCards">
            <div class="finCard finCard--rec">
                <div class="finCard__label">Total Receitas</div>
                <div class="finCard__valor"><?= fmtR($dashboard['totalRec']) ?></div>
            </div>
            <div class="finCard finCard--desp">
                <div class="finCard__label">Total Despesas</div>
                <div class="finCard__valor"><?= fmtR($dashboard['totalDesp']) ?></div>
            </div>
            <?php $res = $dashboard['resultado']; ?>
            <div class="finCard <?= $res >= 0 ? 'finCard--pos' : 'finCard--neg' ?>">
                <div class="finCard__label">Resultado do Mês</div>
                <div class="finCard__valor"><?= fmtR($res) ?> <?= $res >= 0 ? '▲' : '▼' ?></div>
            </div>
        </div>

        <!-- DRE -->
        <div class="finDRE">
            <div class="finDREBlock">
                <div class="finDREBlock__head finDREBlock__head--rec">↑ Receitas</div>
                <?php if (empty($dashboard['receitas'])): ?>
                <div class="finDRERow" style="color:#444;">Nenhuma receita no mês.</div>
                <?php else: ?>
                <?php foreach ($dashboard['receitas'] as $cat => $val): ?>
                <div class="finDRERow">
                    <span><?= catLabel($cat, $catLabels) ?></span>
                    <strong><?= fmtR($val) ?></strong>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <div class="finDRERow finDRERow--total">
                    <span>TOTAL RECEITAS</span>
                    <span style="color:#79ff45;"><?= fmtR($dashboard['totalRec']) ?></span>
                </div>
            </div>

            <div class="finDREBlock">
                <div class="finDREBlock__head finDREBlock__head--desp">↓ Despesas</div>
                <?php if (empty($dashboard['despesas'])): ?>
                <div class="finDRERow" style="color:#444;">Nenhuma despesa no mês.</div>
                <?php else: ?>
                <?php foreach ($dashboard['despesas'] as $cat => $val): ?>
                <div class="finDRERow">
                    <span><?= catLabel($cat, $catLabels) ?></span>
                    <strong><?= fmtR($val) ?></strong>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <div class="finDRERow finDRERow--total">
                    <span>TOTAL DESPESAS</span>
                    <span style="color:#ff7070;"><?= fmtR($dashboard['totalDesp']) ?></span>
                </div>
            </div>
        </div>

        <!-- Botão gerar salários -->
        <?php if (!$dashboard['salariosGerados']): ?>
        <div style="background:rgba(229,194,0,.08);border:1px solid rgba(229,194,0,.25);border-radius:8px;padding:14px 20px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;gap:16px;">
            <div>
                <strong style="color:#e5c200;">Salários não gerados para <?= $mesLabel ?></strong>
                <p style="color:#888;font-size:13px;margin:4px 0 0;">Clique para lançar os salários de todos os professores ativos como despesa do mês.</p>
            </div>
            <button class="btn btn--primary" id="btnGerarSalarios" data-mes="<?= $mes ?>">Gerar Folha de Pagamento</button>
        </div>
        <div style="background:rgba(229,194,0,.05);border:1px solid rgba(229,194,0,.2);border-radius:8px;padding:14px 20px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;gap:16px;">
            <div>
                <strong style="color:#e5c200;">Sincronizar Pagamentos Mercado Pago</strong>
                <p style="color:#888;font-size:13px;margin:4px 0 0;">Verifica pagamentos aprovados no MP e atualiza o status das mensalidades. Também marca como atrasadas as mensalidades com vencimento passado.</p>
            </div>
            <button class="btn btn--gray" id="btnSyncMP">🔄 Sincronizar MP</button>
        </div>
        <div id="syncMPMsg" style="display:none;margin-bottom:16px;font-size:13px;"></div>
        <div style="display:none">
        </div>
        <?php else: ?>
        <div style="background:rgba(46,182,16,.06);border:1px solid rgba(116,255,54,.2);border-radius:8px;padding:10px 18px;margin-bottom:22px;font-size:13px;color:#79ff45;">
            ✓ Folha de pagamento de <?= $mesLabel ?> já gerada.
        </div>
        <?php endif; ?>

        <!-- Últimos lançamentos -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <h3 style="margin:0;font-size:15px;color:#eee;">Últimos lançamentos</h3>
            <a href="?aba=lancamentos&mes=<?= $mes ?>" style="font-size:13px;color:#e5c200;">Ver todos →</a>
        </div>
        <div class="finTable__wrap">
            <table class="finTable">
                <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Tipo</th><th>Valor</th></tr></thead>
                <tbody>
                <?php if (empty($dashboard['lancamentos'])): ?>
                <tr><td colspan="5" style="text-align:center;color:#444;padding:24px;">Nenhum lançamento em <?= $mesLabel ?>.</td></tr>
                <?php else: ?>
                <?php foreach ($dashboard['lancamentos'] as $l): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= date('d/m/Y', strtotime($l['data'])) ?></td>
                    <td><?= htmlspecialchars($l['descricao']) ?></td>
                    <td><span style="font-size:12px;color:#888;"><?= catLabel($l['categoria'], $catLabels) ?></span></td>
                    <td><?= $l['tipo'] === 'receita' ? '<span class="badge-rec">Receita</span>' : '<span class="badge-desp">Despesa</span>' ?></td>
                    <td style="font-weight:700;color:<?= $l['tipo'] === 'receita' ? '#79ff45' : '#ff7070' ?>;"><?= fmtR((float)$l['valor']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($aba === 'lancamentos'): ?>
    <!-- ── LANÇAMENTOS ────────────────────────────────────────────────── -->

        <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
            <button class="btn btn--primary" id="btnNovoLancamento">+ Novo Lançamento</button>
        </div>

        <div class="finTable__wrap">
            <table class="finTable">
                <thead><tr>
                    <th>Data</th><th>Descrição</th><th>Categoria</th>
                    <th>Tipo</th><th>Origem</th><th>Valor</th><th></th>
                </tr></thead>
                <tbody>
                <?php if (empty($lancamentos)): ?>
                <tr><td colspan="7" style="text-align:center;color:#444;padding:28px;">Nenhum lançamento em <?= $mesLabel ?>.</td></tr>
                <?php else: ?>
                <?php foreach ($lancamentos as $l): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= date('d/m/Y', strtotime($l['data'])) ?></td>
                    <td>
                        <?= htmlspecialchars($l['descricao']) ?>
                        <?php if ($l['observacao']): ?><br><small style="color:#555;"><?= htmlspecialchars($l['observacao']) ?></small><?php endif; ?>
                    </td>
                    <td><span style="font-size:12px;color:#888;"><?= catLabel($l['categoria'], $catLabels) ?></span></td>
                    <td><?= $l['tipo'] === 'receita' ? '<span class="badge-rec">Receita</span>' : '<span class="badge-desp">Despesa</span>' ?></td>
                    <td><?= $l['origem'] === 'auto' ? '<span class="badge-auto">Auto</span>' : '<span style="font-size:11px;color:#777;">Manual</span>' ?></td>
                    <td style="font-weight:700;color:<?= $l['tipo'] === 'receita' ? '#79ff45' : '#ff7070' ?>;"><?= fmtR((float)$l['valor']) ?></td>
                    <td>
                        <?php if ($l['origem'] === 'manual'): ?>
                        <button class="btn btn--sm btn--error btnDeleteLanc" data-id="<?= $l['id'] ?>" data-desc="<?= htmlspecialchars($l['descricao']) ?>">Excluir</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php elseif ($aba === 'dividas'): ?>
    <!-- ── DÍVIDAS ────────────────────────────────────────────────────── -->

        <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
            <button class="btn btn--primary" id="btnNovaDivida">+ Nova Dívida</button>
        </div>

        <div class="finTable__wrap">
            <table class="finTable">
                <thead><tr>
                    <th>Descrição</th><th>Credor</th><th>Total</th>
                    <th>Progresso</th><th>Status</th><th>Ações</th>
                </tr></thead>
                <tbody>
                <?php if (empty($dividas)): ?>
                <tr><td colspan="6" style="text-align:center;color:#444;padding:28px;">Nenhuma dívida cadastrada.</td></tr>
                <?php else: ?>
                <?php foreach ($dividas as $d):
                    $pct = $d['total_parcelas'] > 0 ? round(($d['pagas'] / $d['total_parcelas']) * 100) : 0;
                ?>
                <tr>
                    <td><strong style="color:#eee;"><?= htmlspecialchars($d['descricao']) ?></strong></td>
                    <td><?= htmlspecialchars($d['credor'] ?: '—') ?></td>
                    <td><?= fmtR((float)$d['valor_total']) ?></td>
                    <td style="min-width:140px;">
                        <span style="font-size:12px;color:#888;"><?= $d['pagas'] ?>/<?= $d['total_parcelas'] ?> parcelas</span>
                        <div class="parcProgress"><div class="parcProgress__bar" style="width:<?= $pct ?>%"></div></div>
                    </td>
                    <td>
                        <?= $d['status'] === 'quitado'
                            ? '<span class="badge-pago">Quitada</span>'
                            : '<span class="badge-pend">Em aberto</span>' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="?aba=divida&id=<?= $d['id'] ?>" class="btn btn--sm btn--gray">Parcelas</a>
                            <?php if ($d['pagas'] == 0): ?>
                            <button class="btn btn--sm btn--error btnDeleteDivida"
                                    data-id="<?= $d['id'] ?>" data-desc="<?= htmlspecialchars($d['descricao']) ?>">Excluir</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
    </section>
    <?php endif; ?>

    </main>
</div>

<!-- ── Modal: Novo Lançamento ────────────────────────────────────────────── -->
<div class="finModal" id="modalLancamento">
    <div class="finModal__box">
        <div class="finModal__head">
            <h3>Novo Lançamento</h3>
            <button id="closeModalLanc">&times;</button>
        </div>
        <form id="formLancamento">
            <input type="hidden" name="competencia" value="<?= $mes ?>">
            <div class="finModal__body">
                <div class="finRow2">
                    <div class="finField">
                        <label>Tipo <span>*</span></label>
                        <select name="tipo" class="input" id="lancTipo" required>
                            <option value="despesa">Despesa</option>
                            <option value="receita">Receita</option>
                        </select>
                    </div>
                    <div class="finField">
                        <label>Data <span>*</span></label>
                        <input type="date" name="data" class="input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="finRow2">
                    <div class="finField">
                        <label>Categoria <span>*</span></label>
                        <select name="categoria" class="input" id="lancCategoria" required>
                            <optgroup label="— Despesas —">
                                <option value="aluguel">Aluguel</option>
                                <option value="material">Material Esportivo</option>
                                <option value="marketing">Marketing</option>
                                <option value="administrativo">Administrativo</option>
                                <option value="outros">Outros</option>
                            </optgroup>
                            <optgroup label="— Receitas —">
                                <option value="outros_receita">Outras Receitas</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="finField">
                        <label>Valor (R$) <span>*</span></label>
                        <input type="text" name="valor" id="lancValor" class="input" placeholder="0,00" required>
                    </div>
                </div>
                <div class="finField">
                    <label>Descrição <span>*</span></label>
                    <input type="text" name="descricao" class="input" placeholder="Descrição do lançamento" required>
                </div>
                <div class="finField">
                    <label>Observação</label>
                    <input type="text" name="observacao" class="input" placeholder="Opcional">
                </div>
            </div>
            <div class="finModalActions">
                <button type="button" class="btn btn--gray" id="cancelarLanc">Cancelar</button>
                <button type="submit" class="btn btn--primary">Salvar lançamento</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Nova Dívida ──────────────────────────────────────────────────── -->
<div class="finModal" id="modalDivida">
    <div class="finModal__box">
        <div class="finModal__head">
            <h3>Nova Dívida</h3>
            <button id="closeModalDivida">&times;</button>
        </div>
        <form id="formDivida">
            <div class="finModal__body">
                <div class="finRow2">
                    <div class="finField" style="grid-column:1/-1;">
                        <label>Descrição <span>*</span></label>
                        <input type="text" name="descricao" class="input" placeholder="Ex: Compra de equipamentos" required>
                    </div>
                </div>
                <div class="finRow2">
                    <div class="finField">
                        <label>Credor</label>
                        <input type="text" name="credor" class="input" placeholder="Nome do fornecedor">
                    </div>
                    <div class="finField">
                        <label>Categoria</label>
                        <select name="categoria" class="input">
                            <option value="outros">Outros</option>
                            <option value="equipamento">Equipamento</option>
                            <option value="reforma">Reforma</option>
                            <option value="aluguel">Aluguel</option>
                            <option value="administrativo">Administrativo</option>
                        </select>
                    </div>
                </div>
                <div class="finRow3">
                    <div class="finField">
                        <label>Valor Total (R$) <span>*</span></label>
                        <input type="text" name="valor_total" id="divValorTotal" class="input" placeholder="0,00" required>
                    </div>
                    <div class="finField">
                        <label>Nº de Parcelas <span>*</span></label>
                        <input type="number" name="num_parcelas" id="divNumParcelas" class="input" min="1" max="120" value="1" required>
                    </div>
                    <div class="finField">
                        <label>Parcela aprox.</label>
                        <input type="text" id="divPreviewParcela" class="input" readonly style="color:#e5c200;background:#1a1a1a;">
                    </div>
                </div>
                <div class="finField">
                    <label>1ª parcela vence em <span>*</span></label>
                    <input type="date" name="data_inicio" class="input" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="finField">
                    <label>Observação</label>
                    <input type="text" name="observacao" class="input" placeholder="Opcional">
                </div>
            </div>
            <div class="finModalActions">
                <button type="button" class="btn btn--gray" id="cancelarDivida">Cancelar</button>
                <button type="submit" class="btn btn--primary">Cadastrar dívida</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Confirm excluir ─────────────────────────────────────────────────────── -->
<div class="confirmModal" id="confirmModal">
    <div class="confirmModal__box">
        <h3 id="confirmTitulo">Confirmar exclusão</h3>
        <p id="confirmTexto"></p>
        <div class="confirmModal__actions">
            <button class="btn btn--gray" id="confirmCancelar">Cancelar</button>
            <button class="btn btn--error" id="confirmOk">Confirmar</button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var ABA_ATUAL     = "<?= $aba ?>";
var MES_ATUAL     = "<?= $mes ?>";

// ── Utilitários ───────────────────────────────────────────────────────────────
function maskValor(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '');
        if (!v) { this.value = ''; return; }
        v = (parseInt(v, 10) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        this.value = v;
    });
}
function valorFloat(str) {
    return parseFloat((str || '0').replace(/\./g, '').replace(',', '.')) || 0;
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Lançamento modal ──────────────────────────────────────────────────────────
var btnNovoLanc = document.getElementById('btnNovoLancamento');
if (btnNovoLanc) {
    maskValor(document.getElementById('lancValor'));
    btnNovoLanc.addEventListener('click', function () { openModal('modalLancamento'); });
    document.getElementById('closeModalLanc').addEventListener('click',  function () { closeModal('modalLancamento'); });
    document.getElementById('cancelarLanc').addEventListener('click',    function () { closeModal('modalLancamento'); });

    document.getElementById('formLancamento').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Salvando...';
        fetch(ADMIN_BASE_URL + '/services/save_lancamento.php', {
            method: 'POST', credentials: 'same-origin', body: new FormData(this),
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else { alert(d.message || 'Erro.'); btn.disabled = false; btn.textContent = 'Salvar lançamento'; }
        });
    });
}

// ── Dívida modal ──────────────────────────────────────────────────────────────
var btnNovaDivida = document.getElementById('btnNovaDivida');
if (btnNovaDivida) {
    var divValorTotalEl  = document.getElementById('divValorTotal');
    var divNumParcelasEl = document.getElementById('divNumParcelas');
    var divPreviewEl     = document.getElementById('divPreviewParcela');

    maskValor(divValorTotalEl);

    function atualizarPreviewParcela() {
        var total = valorFloat(divValorTotalEl.value);
        var n     = parseInt(divNumParcelasEl.value) || 1;
        if (total > 0 && n > 0) {
            var parcela = total / n;
            divPreviewEl.value = 'R$ ' + parcela.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        } else {
            divPreviewEl.value = '';
        }
    }
    divValorTotalEl.addEventListener('input', atualizarPreviewParcela);
    divNumParcelasEl.addEventListener('input', atualizarPreviewParcela);

    btnNovaDivida.addEventListener('click', function () { openModal('modalDivida'); });
    document.getElementById('closeModalDivida').addEventListener('click', function () { closeModal('modalDivida'); });
    document.getElementById('cancelarDivida').addEventListener('click',   function () { closeModal('modalDivida'); });

    document.getElementById('formDivida').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Cadastrando...';
        fetch(ADMIN_BASE_URL + '/services/save_divida.php', {
            method: 'POST', credentials: 'same-origin', body: new FormData(this),
        }).then(r => r.json()).then(d => {
            if (d.success) location.href = ADMIN_BASE_URL + '/financeiro?aba=divida&id=' + d.id;
            else { alert(d.message || 'Erro.'); btn.disabled = false; btn.textContent = 'Cadastrar dívida'; }
        });
    });
}

// ── Gerar salários ────────────────────────────────────────────────────────────
// ── Sync MP ───────────────────────────────────────────────────────────────────
var btnSyncMP = document.getElementById('btnSyncMP');
if (btnSyncMP) {
    btnSyncMP.addEventListener('click', function () {
        this.disabled = true; this.textContent = 'Sincronizando...';
        var msg = document.getElementById('syncMPMsg');
        fetch(ADMIN_BASE_URL + '/services/sincronizar_pagamentos_mp.php', {
            method: 'POST', credentials: 'same-origin',
        })
        .then(r => r.json())
        .then(d => {
            msg.textContent   = d.mensagem || 'Concluído.';
            msg.style.color   = d.atualizadas > 0 ? '#7ecf7e' : '#aaa';
            msg.style.display = '';
            if (d.atualizadas > 0) location.reload();
        })
        .catch(() => { msg.textContent = 'Erro de comunicação.'; msg.style.color = '#cf7e7e'; msg.style.display = ''; })
        .finally(() => { this.disabled = false; this.textContent = '🔄 Sincronizar MP'; });
    });
}

var btnGerarSal = document.getElementById('btnGerarSalarios');
if (btnGerarSal) {
    btnGerarSal.addEventListener('click', function () {
        if (!confirm('Gerar lançamentos de salário para ' + this.dataset.mes + '?')) return;
        this.disabled = true; this.textContent = 'Gerando...';
        var fd = new FormData();
        fd.append('competencia', this.dataset.mes);
        fetch(ADMIN_BASE_URL + '/services/gerar_salarios.php', {
            method: 'POST', credentials: 'same-origin', body: fd,
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else alert(d.message || 'Erro.');
        });
    });
}

// ── Confirm genérico ──────────────────────────────────────────────────────────
var confirmAction = null;
function abrirConfirm(titulo, texto, fn) {
    document.getElementById('confirmTitulo').textContent = titulo;
    document.getElementById('confirmTexto').textContent  = texto;
    confirmAction = fn;
    document.getElementById('confirmModal').classList.add('confirmModal--open');
}
document.getElementById('confirmCancelar')?.addEventListener('click', function () {
    document.getElementById('confirmModal').classList.remove('confirmModal--open');
});
document.getElementById('confirmOk')?.addEventListener('click', function () {
    document.getElementById('confirmModal').classList.remove('confirmModal--open');
    if (confirmAction) confirmAction();
});

// ── Excluir lançamento ────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnDeleteLanc');
    if (!btn) return;
    abrirConfirm('Excluir Lançamento', 'Excluir "' + btn.dataset.desc + '"? Esta ação não pode ser desfeita.', function () {
        var fd = new FormData(); fd.append('id', btn.dataset.id);
        fetch(ADMIN_BASE_URL + '/services/delete_lancamento.php', {
            method: 'POST', credentials: 'same-origin', body: fd,
        }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message || 'Erro.'); });
    });
});

// ── Excluir dívida ────────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnDeleteDivida');
    if (!btn) return;
    abrirConfirm('Excluir Dívida', 'Excluir "' + btn.dataset.desc + '"? Todas as parcelas serão removidas.', function () {
        var fd = new FormData(); fd.append('id', btn.dataset.id);
        fetch(ADMIN_BASE_URL + '/services/delete_divida.php', {
            method: 'POST', credentials: 'same-origin', body: fd,
        }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message || 'Erro.'); });
    });
});

// ── Pagar / Adiantar parcela ──────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnPagarParcela');
    if (!btn) return;
    var tipo = btn.dataset.tipo;
    var label = btn.dataset.label;
    var msg = tipo === 'adiantado'
        ? 'Adiantar pagamento da ' + label + '? Será registrada como paga hoje.'
        : 'Registrar pagamento da ' + label + '?';
    abrirConfirm(tipo === 'adiantado' ? 'Adiantar Parcela' : 'Pagar Parcela', msg, function () {
        var fd = new FormData();
        fd.append('parcela_id', btn.dataset.id);
        fd.append('tipo', tipo);
        fetch(ADMIN_BASE_URL + '/services/pagar_parcela.php', {
            method: 'POST', credentials: 'same-origin', body: fd,
        }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message || 'Erro.'); });
    });
});
</script>
</body>
</html>
