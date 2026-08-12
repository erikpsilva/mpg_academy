<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/mercadopago.php';
$pdo  = getDbConnection();
$hoje = new DateTime(date('Y-m-d'));

$MESES = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

$mesFiltro = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesFiltro)) $mesFiltro = date('Y-m');
$dtMes    = new DateTime($mesFiltro . '-01');
$prevMes  = (clone $dtMes)->modify('-1 month')->format('Y-m');
$nextMes  = (clone $dtMes)->modify('+1 month')->format('Y-m');
$mesLabel = $MESES[(int) $dtMes->format('n')] . ' de ' . $dtMes->format('Y');

$stmt = $pdo->prepare("
    SELECT m.id, m.aluno_id, m.valor, m.vencimento, m.data_pagamento, m.status,
           m.mp_taxa_valor, m.mp_valor_liquido, m.mp_payment_method,
           a.nome AS aluno_nome, a.celular,
           COALESCE(t.nome, '—') AS turma_nome
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.referencia = ?
    ORDER BY (m.status = 'pago'), a.nome
");
$stmt->execute([$mesFiltro]);
$faturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// A coluna guarda o payment_type_id do MP (PIX chega como 'bank_transfer'), então a
// tradução vem do helper compartilhado — o mapa antigo aqui tinha a chave 'pix', que
// nunca casava, e a tela acabava mostrando "bank_transfer" cru.

$totPago = 0; $totPendente = 0; $totAtrasado = 0; $totLiquido = 0; $totTaxaMp = 0;
$qtdPago = 0; $qtdPendente = 0; $qtdAtrasado = 0;
foreach ($faturas as $f) {
    $v = (float) $f['valor'];
    if ($f['status'] === 'pago') {
        $totPago += $v;
        $qtdPago++;
        // Sem dado de taxa MP (baixa manual, ou fatura paga antes dessa atualização) = líquido igual ao bruto.
        $totLiquido += $f['mp_valor_liquido'] !== null ? (float) $f['mp_valor_liquido'] : $v;
        $totTaxaMp  += (float) ($f['mp_taxa_valor'] ?? 0);
    }
    elseif ($f['status'] === 'atrasado') { $totAtrasado += $v;  $qtdAtrasado++; }
    else                                  { $totPendente += $v;  $qtdPendente++; }
}
$temPendentes = ($qtdPendente + $qtdAtrasado) > 0;

function fmtBrlMens(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy — Mensalidades dos Alunos</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
<?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
<main class="adminLayout__content">

<div class="areaProfessor__welcome">
    <div>
        <h1 class="areaProfessor__title">Mensalidades dos <span>Alunos</span></h1>
        <p class="areaProfessor__sub">Faturas de todos os alunos por mês de referência, com cobrança via WhatsApp</p>
    </div>
</div>

<div class="mensAdmin__toolbar">
    <div class="historicoAulas__mesFiltro">
        <a href="?mes=<?= $prevMes ?>" class="historicoAulas__mesNav">&#8592;</a>
        <label class="historicoAulas__mesLabel">
            <?= $mesLabel ?>
            <input type="month" id="filtroMes" value="<?= $mesFiltro ?>">
        </label>
        <a href="?mes=<?= $nextMes ?>" class="historicoAulas__mesNav">&#8594;</a>
    </div>
    <button type="button" class="btn btn--primary mensAdmin__btnAvisar" id="btnAvisarTodos"
            data-mes="<?= $mesFiltro ?>" <?= $temPendentes ? '' : 'disabled' ?>>
        📲 Avisar Todos
    </button>
</div>

<div class="mensAdmin__stats">
    <div class="mensAdmin__stat mensAdmin__stat--pago">
        <span class="mensAdmin__statNum"><?= $qtdPago ?></span>
        <span class="mensAdmin__statLabel">Pagas &middot; <?= fmtBrlMens($totPago) ?></span>
    </div>
    <div class="mensAdmin__stat mensAdmin__stat--liquido">
        <span class="mensAdmin__statNum"><?= fmtBrlMens($totLiquido) ?></span>
        <span class="mensAdmin__statLabel">Recebido líquido <?= $totTaxaMp > 0 ? '&middot; taxa MP ' . fmtBrlMens($totTaxaMp) : '' ?></span>
    </div>
    <div class="mensAdmin__stat mensAdmin__stat--pendente">
        <span class="mensAdmin__statNum"><?= $qtdPendente ?></span>
        <span class="mensAdmin__statLabel">Pendentes &middot; <?= fmtBrlMens($totPendente) ?></span>
    </div>
    <div class="mensAdmin__stat mensAdmin__stat--atrasado">
        <span class="mensAdmin__statNum"><?= $qtdAtrasado ?></span>
        <span class="mensAdmin__statLabel">Atrasadas &middot; <?= fmtBrlMens($totAtrasado) ?></span>
    </div>
</div>

<?php if (empty($faturas)): ?>
<div class="minhasAulas__vazio"><span>🧾</span><p>Nenhuma fatura para <?= $mesLabel ?>.</p></div>
<?php else: ?>
<div class="mensAdmin__panel">
    <table class="mensAdminTable">
        <thead>
            <tr>
                <th>Aluno</th>
                <th>Turma</th>
                <th>Valor</th>
                <th>Líquido</th>
                <th>Vencimento</th>
                <th>Status</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($faturas as $f):
            $vencDt  = new DateTime(substr($f['vencimento'], 0, 10));
            $diff    = (int) $hoje->diff($vencDt)->format('%r%a');
            $vencFmt = $vencDt->format('d/m/Y');

            $multaJuros = null;

            if ($f['status'] === 'pago') {
                $badgeClass = 'mensAdmin__badge--pago';
                $badgeLabel = 'Pago';
                $sub = $f['data_pagamento'] ? 'em ' . (new DateTime(substr($f['data_pagamento'], 0, 10)))->format('d/m/Y') : '';
            } elseif ($f['status'] === 'atrasado' || $diff < 0) {
                $badgeClass = 'mensAdmin__badge--atrasado';
                $badgeLabel = 'Atrasada';
                $sub = abs($diff) . ' dia' . (abs($diff) > 1 ? 's' : '') . ' em atraso';
                if ($f['status'] === 'atrasado') {
                    $multaJuros = mpCalcularMultaJuros((float) $f['valor'], $f['vencimento']);
                }
            } elseif ($diff === 0) {
                $badgeClass = 'mensAdmin__badge--hoje';
                $badgeLabel = 'Pendente';
                $sub = 'vence hoje';
            } else {
                $badgeClass = 'mensAdmin__badge--pendente';
                $badgeLabel = 'Pendente';
                $sub = 'vence em ' . $diff . ' dia' . ($diff > 1 ? 's' : '');
            }
        ?>
            <tr>
                <td class="mensAdminTable__aluno"><?= htmlspecialchars($f['aluno_nome']) ?></td>
                <td><?= htmlspecialchars($f['turma_nome']) ?></td>
                <td>
                    <?= fmtBrlMens((float) $f['valor']) ?>
                    <?php if ($multaJuros): ?>
                    <small class="mensAdmin__badgeSub">
                        + <?= fmtBrlMens($multaJuros['multa'] + $multaJuros['juros']) ?> multa/juros &rarr;
                        <strong><?= fmtBrlMens($multaJuros['total']) ?></strong>
                    </small>
                    <?php endif; ?>
                </td>
                <td>
                <?php if ($f['status'] === 'pago' && $f['mp_valor_liquido'] !== null): ?>
                    <?= fmtBrlMens((float) $f['mp_valor_liquido']) ?>
                    <small class="mensAdmin__badgeSub">
                        taxa <?= fmtBrlMens((float) $f['mp_taxa_valor']) ?><?= $f['mp_payment_method'] ? ' &middot; ' . htmlspecialchars(mpFormaPagamentoLabel($f['mp_payment_method'])) : '' ?>
                    </small>
                <?php elseif ($f['status'] === 'pago'): ?>
                    &mdash;
                <?php else: ?>
                    <span class="mensAdmin__badgeSub">a definir</span>
                <?php endif; ?>
                </td>
                <td><?= $vencFmt ?></td>
                <td>
                    <span class="mensAdmin__badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                    <?php if ($sub): ?><small class="mensAdmin__badgeSub"><?= $sub ?></small><?php endif; ?>
                </td>
                <td>
                <?php if ($f['status'] !== 'pago'): ?>
                    <button type="button" class="btn btn--sm btn--gray btnCobrarFatura"
                            data-id="<?= $f['id'] ?>" data-nome="<?= htmlspecialchars($f['aluno_nome']) ?>"
                            <?= empty($f['celular']) ? 'disabled title="Sem celular cadastrado"' : '' ?>>
                        📲 Cobrar
                    </button>
                <?php else: ?>
                    &mdash;
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</main>
</div>
<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

document.getElementById('filtroMes').addEventListener('change', function () {
    if (this.value) window.location.href = '?mes=' + this.value;
});

function dispararCobranca(fd, btn, textoOriginal) {
    btn.disabled = true;
    fetch(ADMIN_BASE_URL + '/services/disparar_cobranca_mensalidade.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Concluído.' : 'Erro ao enviar.'));
        btn.disabled = false;
        if (textoOriginal) btn.textContent = textoOriginal;
    })
    .catch(function () {
        alert('Erro de comunicação.');
        btn.disabled = false;
        if (textoOriginal) btn.textContent = textoOriginal;
    });
}

document.getElementById('btnAvisarTodos')?.addEventListener('click', function () {
    if (!confirm('Enviar aviso de cobrança via WhatsApp para todos os alunos com fatura pendente ou atrasada neste mês?')) return;
    var btn = this;
    var original = btn.textContent;
    btn.textContent = 'Enviando...';
    var fd = new FormData();
    fd.append('mes', btn.dataset.mes);
    dispararCobranca(fd, btn, original);
});

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnCobrarFatura');
    if (!btn) return;
    if (!confirm('Enviar cobrança via WhatsApp para ' + btn.dataset.nome + '?')) return;
    var original = btn.textContent;
    btn.textContent = 'Enviando...';
    var fd = new FormData();
    fd.append('mensalidade_id', btn.dataset.id);
    dispararCobranca(fd, btn, original);
});
</script>
</body>
</html>
