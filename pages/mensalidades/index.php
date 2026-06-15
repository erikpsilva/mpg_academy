<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once ROOT . '/config/database.php';

$aluno    = $_SESSION['aluno'];
$alunoId  = (int) $aluno['id'];

$meses = [
    '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr',
    '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
    '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez',
];

$pdo = getDbConnection();

// Turma ativa do aluno
$stTurma = $pdo->prepare("
    SELECT t.id AS turma_id, t.nome AS turma_nome, t.valor_mensalidade,
           q.nome AS quadra_nome
    FROM turma_alunos ta
    JOIN turmas t ON t.id = ta.turma_id
    JOIN quadras q ON q.id = t.quadra_id
    WHERE ta.aluno_id = ? AND ta.status = 'ativo'
    LIMIT 1
");
$stTurma->execute([$alunoId]);
$turma = $stTurma->fetch();

// Mensalidades em ordem decrescente com nome da turma
$stMens = $pdo->prepare("
    SELECT m.id, m.referencia, m.tipo, m.descricao, m.valor, m.matricula_valor,
           m.vencimento, m.data_pagamento, m.status,
           COALESCE(t.nome, '') AS turma_nome
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.aluno_id = ?
    ORDER BY m.vencimento DESC, m.referencia DESC
");
$stMens->execute([$alunoId]);
$mensalidades = $stMens->fetchAll();

$hoje     = new DateTime('today');
$temAtraso = false;

foreach ($mensalidades as &$m) {
    $venc          = new DateTime($m['vencimento']);
    $m['dias_atraso']    = 0;
    $m['total_devido']   = null;
    $m['multa']          = null;
    $m['juros']          = null;
    $m['base_com_multa'] = null;

    if ($m['status'] === 'atrasado') {
        $temAtraso       = true;
        $dias            = (int) $venc->diff($hoje)->days;
        $m['dias_atraso'] = $dias;
        $valor           = (float) $m['valor'];
        $multa           = $valor * 0.05;
        $base            = $valor + $multa;
        $juros           = $base * 0.005 * $dias;
        $m['multa']          = $multa;
        $m['juros']          = $juros;
        $m['base_com_multa'] = $base;
        $m['total_devido']   = $base + $juros;
    }
}
unset($m);

// Próxima fatura (mês seguinte ao último registro)
$proxVencDate = null;
$proxRef      = '';
$proxStatus   = 'paid';
$proxDias     = '';

if (!empty($mensalidades)) {
    [$ano, $mes] = explode('-', $mensalidades[0]['referencia']);
    $proxDate     = new DateTime("$ano-$mes-01");
    $proxDate->modify('+1 month');
    $proxRef      = $proxDate->format('Y-m');
    // Vencimento = dia 5 do mês seguinte ao de referência (ciclo fecha dia 30, paga dia 5)
    $proxVencMes  = clone $proxDate;
    $proxVencMes->modify('+1 month');
    $proxVencDate = new DateTime($proxVencMes->format('Y-m') . '-05');

    $diff = (int) $hoje->diff($proxVencDate)->days;
    if ($proxVencDate > $hoje) {
        $proxStatus = 'paid';
        $proxDias   = 'em ' . $diff . ' dia' . ($diff === 1 ? '' : 's');
    } else {
        $proxStatus = 'late';
        $proxDias   = 'vencida há ' . $diff . ' dia' . ($diff === 1 ? '' : 's');
    }
}

function refLabel(string $ref, array $meses): string {
    [$a, $m] = explode('-', $ref);
    return ($meses[$m] ?? $m) . '/' . $a;
}

function fmtDate(string $date): string {
    return (new DateTime($date))->format('d/m/Y');
}

function fmtMoney(float $val): string {
    return 'R$&nbsp;' . number_format($val, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Mensalidades</title>
<?php include ROOT . '/includes/assets.php'; ?>
<style>
.studentMonthlyStatus--pending {
    border: 1px solid rgba(96,165,250,0.6);
    color: #60a5fa;
    background: rgba(96,165,250,0.1);
}
</style>
</head>

<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="studentArea studentMonthly">
    <div class="studentArea__layout">
        <aside class="studentAreaSidebar">
            <nav class="studentAreaSidebar__nav" aria-label="Menu do aluno">
                <a href="<?= BASE_URL ?>/areadoaluno"><i class="icon-home"></i> Dashboard</a>

                <strong>Geral</strong>
                <a href="<?= BASE_URL ?>/meuperfil"><i class="icon-user"></i> Meu Perfil</a>
                <a href="<?= BASE_URL ?>/mensalidades" class="is-active"><i class="icon-creditcard"></i> Mensalidades</a>
                <a href="<?= BASE_URL ?>/treinos"><i class="icon-calendar"></i> Agenda</a>
                <a href="<?= BASE_URL ?>/comunicados"><i class="icon-megaphone"></i> Comunicados</a>

                <strong>Extras</strong>
                <a href="#indique"><i class="icon-comunidade"></i> Indique um amigo</a>
            </nav>

            <div class="studentAreaSidebar__help">
                <h3>Precisa de ajuda?</h3>
                <p>Fale com nossa equipe pelo WhatsApp.</p>
                <a href="https://wa.me/5511972330097" target="_blank" rel="noopener">
                    <i class="icon-whatsapp"></i>
                    Falar no WhatsApp
                </a>
            </div>
        </aside>

        <section class="studentAreaContent">
            <nav class="studentMonthlyBreadcrumb" aria-label="breadcrumb">
                <a href="<?= BASE_URL ?>/areadoaluno">Dashboard</a>
                <i class="icon-go" aria-hidden="true"></i>
                <span>Mensalidades</span>
            </nav>

            <section class="studentMonthlyHero">
                <span><i class="icon-creditcard" aria-hidden="true"></i></span>
                <div>
                    <h1>Mensalidades</h1>
                    <p>Acompanhe suas mensalidades, pagamentos e faturas<?= $turma ? ' — ' . htmlspecialchars($turma['turma_nome']) : '' ?>.</p>
                </div>
            </section>

            <?php if ($temAtraso): ?>
            <section class="studentMonthlyAlert">
                <span>!</span>
                <div>
                    <h2>Aten&ccedil;&atilde;o para faturas em atraso</h2>
                    <p>Faturas em atraso geram 5% de multa + 0,5% ao dia sobre o valor da mensalidade. Evite multas mantendo suas mensalidades em dia.</p>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($proxVencDate): ?>
            <section class="studentMonthlyNext">
                <h2>Pr&oacute;xima fatura</h2>
                <div class="studentMonthlyNext__box">
                    <article>
                        <span>Vencimento</span>
                        <strong><?= $proxVencDate->format('d/m/Y') ?></strong>
                        <small><?= htmlspecialchars($proxDias) ?></small>
                    </article>
                    <article>
                        <span>Valor da mensalidade</span>
                        <strong><?= $turma ? 'R$ ' . number_format((float)$turma['valor_mensalidade'], 2, ',', '.') : '—' ?></strong>
                    </article>
                    <article>
                        <span>Status</span>
                        <b class="studentMonthlyStatus studentMonthlyStatus--<?= $proxStatus === 'paid' ? 'paid' : 'late' ?>">
                            <?= $proxStatus === 'paid' ? 'Em dia' : 'Atrasado' ?>
                        </b>
                        <small>Ser&aacute; cobrada no dia <?= $proxVencDate->format('d/m/Y') ?></small>
                    </article>
                    <a href="#">Ver detalhes <i class="icon-ver" aria-hidden="true"></i></a>
                </div>
            </section>
            <?php endif; ?>

            <section class="studentMonthlyHistory">
                <div class="studentMonthlyHistory__head">
                    <h2>Hist&oacute;rico de faturas</h2>
                    <label>
                        <select aria-label="Filtrar por status" id="filtroStatus">
                            <option value="">Todos os status</option>
                            <option value="pago">Pago</option>
                            <option value="atrasado">Atrasado</option>
                        </select>
                    </label>
                </div>

                <div class="studentMonthlyTable" role="table" aria-label="Hist&oacute;rico de faturas">
                    <div class="studentMonthlyTable__row studentMonthlyTable__row--head" role="row">
                        <span>Refer&ecirc;ncia</span>
                        <span>Vencimento</span>
                        <span>Valor</span>
                        <span>Status</span>
                        <span>Pagamento</span>
                        <span>A&ccedil;&otilde;es</span>
                    </div>

                    <?php foreach ($mensalidades as $m): ?>
                    <?php
                        $isLate    = $m['status'] === 'atrasado';
                        $isPaid    = $m['status'] === 'pago';
                        $isPending = $m['status'] === 'pendente';
                        $isAvulso  = ($m['tipo'] ?? 'mensalidade') === 'avulso';
                        $refLabel  = $isAvulso
                            ? htmlspecialchars($m['descricao'] ?? 'Cobrança extra')
                            : refLabel($m['referencia'], $meses);
                        $matriculaValor = (float)($m['matricula_valor'] ?? 0);
                    ?>

                    <div class="studentMonthlyTable__row<?= $isLate ? ' studentMonthlyTable__row--late' : '' ?>"
                         role="row"
                         data-status="<?= htmlspecialchars($m['status']) ?>">
                        <span data-label="Refer&ecirc;ncia">
                            <?php if ($isAvulso): ?>
                            <span class="studentMonthlyRef studentMonthlyRef--extra">
                                <b>EXTRA</b>
                                <strong><?= $refLabel ?></strong>
                            </span>
                            <?php else: ?>
                            <?= $refLabel ?>
                            <?php endif; ?>
                        </span>
                        <span data-label="Vencimento"><?= fmtDate($m['vencimento']) ?></span>
                        <span data-label="Valor">
                            <?php if ($matriculaValor > 0): ?>
                                R$ <?= number_format((float)$m['valor'] - $matriculaValor, 2, ',', '.') ?>
                                <small style="display:block;color:#888;font-size:11px;">+ R$ <?= number_format($matriculaValor, 2, ',', '.') ?> matrícula</small>
                            <?php else: ?>
                                R$ <?= number_format((float)$m['valor'], 2, ',', '.') ?>
                            <?php endif; ?>
                        </span>

                        <span data-label="Status">
                            <?php if ($isLate): ?>
                                <b class="studentMonthlyStatus studentMonthlyStatus--late">Atrasado</b>
                                <small>Vencido h&aacute; <?= $m['dias_atraso'] ?> dia<?= $m['dias_atraso'] === 1 ? '' : 's' ?></small>
                            <?php elseif ($isPending): ?>
                                <b class="studentMonthlyStatus studentMonthlyStatus--pending">A Vencer</b>
                                <small>Vence em <?= fmtDate($m['vencimento']) ?></small>
                            <?php else: ?>
                                <b class="studentMonthlyStatus studentMonthlyStatus--paid">Pago</b>
                            <?php endif; ?>
                        </span>

                        <span data-label="Pagamento">
                            <?php if ($isLate): ?>
                                <strong><?= fmtMoney($m['total_devido']) ?></strong>
                                <small>(com multa)</small>
                            <?php elseif ($isPaid && $m['data_pagamento']): ?>
                                Pago em <?= fmtDate($m['data_pagamento']) ?>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </span>

                        <span data-label="A&ccedil;&otilde;es">
                            <?php if ($isLate || $m['status'] === 'pendente'): ?>
                                <a class="studentMonthlyPay" href="<?= BASE_URL ?>/pagamento?mensalidade_id=<?= $m['id'] ?>">Pagar agora</a>
                            <?php else: ?>
                                <a class="studentMonthlyReceipt btnVerRecibo" href="#"
                                   data-ref="<?= $refLabel ?>"
                                   data-turma="<?= htmlspecialchars($m['turma_nome'] ?? '—') ?>"
                                   data-aluno="<?= htmlspecialchars($aluno['nome']) ?>"
                                   data-valor="<?= $m['valor'] ?>"
                                   data-vencimento="<?= $m['vencimento'] ?>"
                                   data-pagamento="<?= $m['data_pagamento'] ?? '' ?>">
                                    Ver recibo <i class="icon-go"></i>
                                </a>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if ($isLate): ?>
                    <div class="studentMonthlyTable__details" data-status="atrasado">
                        <div>
                            <span>Valor original</span>
                            <strong><?= fmtMoney((float)$m['valor']) ?></strong>
                            <span>Multa (5%)</span>
                            <strong><?= fmtMoney($m['multa']) ?></strong>
                        </div>
                        <div>
                            <span>Juros (0,5% ao dia)</span>
                            <strong><?= fmtMoney($m['juros']) ?></strong>
                            <span><?= $m['dias_atraso'] ?> dia<?= $m['dias_atraso'] === 1 ? '' : 's' ?> de atraso</span>
                        </div>
                        <div>
                            <span>Total com multa</span>
                            <strong><?= fmtMoney($m['total_devido']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endforeach; ?>

                    <?php if (empty($mensalidades)): ?>
                    <div class="studentMonthlyTable__row" role="row">
                        <span style="grid-column:1/-1;color:#888;text-align:center;padding:24px 0;">Nenhuma mensalidade encontrada.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="studentMonthlyInfo">
                <i class="icon-information" aria-hidden="true"></i>
                <div>
                    <h2>Como funcionam as mensalidades?</h2>
                    <p><i class="icon-check"></i> O vencimento das mensalidades &eacute; todo dia 5 de cada m&ecirc;s.</p>
                    <p><i class="icon-check"></i> Ap&oacute;s o vencimento, ser&aacute; cobrada multa de 5% + 0,5% ao dia de atraso.</p>
                    <p><i class="icon-check"></i> Mantenha suas mensalidades em dia e evite cobran&ccedil;as adicionais.</p>
                </div>
            </section>
        </section>
    </div>
</main>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>

<!-- ── Modal Recibo ────────────────────────────────────────────────────────── -->
<div id="reciboModal" style="display:none;position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);">
    <div id="reciboCard" style="position:relative;width:100%;max-width:460px;max-height:90vh;overflow-y:auto;background:#111;border:1px solid #2a2a2a;border-radius:14px;">

        <button id="reciboClose" style="position:absolute;top:14px;right:16px;background:none;border:none;color:#888;font-size:24px;cursor:pointer;line-height:1;z-index:2;" aria-label="Fechar">&times;</button>

        <div id="reciboContent" style="padding:32px 28px 28px;"><!-- preenchido pelo JS --></div>
    </div>
</div>

<style>
@media (max-width: 640px) {
    #reciboCard {
        max-width: 100% !important;
        max-height: 100% !important;
        border-radius: 0 !important;
        height: 100%;
    }
    #reciboModal {
        padding: 0 !important;
        align-items: stretch !important;
    }
}
.recibo__divider { border: none; border-top: 1px solid #2a2a2a; margin: 18px 0; }
.recibo__row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 5px 0; font-size: 14px; color: #ccc; }
.recibo__row strong { color: #fff; white-space: nowrap; }
.recibo__row--total { font-size: 17px; font-weight: 900; color: #fff; padding-top: 12px; }
.recibo__row--total span:last-child { color: #e5c200; font-size: 20px; }
.recibo__tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 900; text-transform: uppercase; margin-bottom: 18px; }
.recibo__tag--ok   { background: rgba(46,182,16,.12); border: 1px solid rgba(116,255,54,.4); color: #79ff45; }
.recibo__tag--late { background: rgba(255,45,45,.12); border: 1px solid rgba(255,45,45,.4); color: #ff6060; }
</style>

<script>
var BASE_URL  = "<?= BASE_URL ?>";
var LOGO_URL  = BASE_URL + "/images/logo.png";
var ALUNO_NOME = "<?= htmlspecialchars($aluno['nome'], ENT_QUOTES) ?>";

// ── Filtro de status ────────────────────────────────────────────────────────
(function () {
    var sel   = document.getElementById('filtroStatus');
    var table = document.querySelector('.studentMonthlyTable');
    if (!sel || !table) return;
    sel.addEventListener('change', function () {
        var filter = this.value;
        table.querySelectorAll('[data-status]').forEach(function (el) {
            el.style.display = (!filter || el.dataset.status === filter) ? '' : 'none';
        });
    });
}());

// ── Recibo ──────────────────────────────────────────────────────────────────
(function () {
    var modal   = document.getElementById('reciboModal');
    var content = document.getElementById('reciboContent');
    var closeBtn = document.getElementById('reciboClose');

    var fmt = function (n) {
        return 'R$ ' + parseFloat(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var fmtDate = function (str) {
        if (!str) return '—';
        var p = str.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    };

    function buildRecibo(d) {
        var valor = parseFloat(d.valor);
        var venc  = new Date(d.vencimento + 'T00:00:00');
        var pago  = d.pagamento ? new Date(d.pagamento + 'T00:00:00') : null;

        var isLate = pago && pago > venc;
        var diasAtraso = isLate ? Math.round((pago - venc) / 86400000) : 0;
        var multa  = isLate ? valor * 0.05 : 0;
        var base   = valor + multa;
        var juros  = isLate ? base * 0.005 * diasAtraso : 0;
        var total  = isLate ? base + juros : valor;

        var tagHtml = isLate
            ? '<span class="recibo__tag recibo__tag--late">Pago com atraso (' + diasAtraso + ' dia' + (diasAtraso === 1 ? '' : 's') + ')</span>'
            : '<span class="recibo__tag recibo__tag--ok">Pago em dia</span>';

        var itensHtml = '';
        if (isLate) {
            itensHtml +=
                '<div class="recibo__row"><span>Mensalidade ' + d.ref + '</span><strong>' + fmt(valor) + '</strong></div>' +
                '<div class="recibo__row"><span>Multa por atraso (5%)</span><strong>' + fmt(multa) + '</strong></div>' +
                '<div class="recibo__row"><span>Juros de atraso (0,5%/dia &times; ' + diasAtraso + ' dia' + (diasAtraso === 1 ? '' : 's') + ')</span><strong>' + fmt(juros) + '</strong></div>';
        } else {
            itensHtml +=
                '<div class="recibo__row"><span>Mensalidade ' + d.ref + '</span><strong>' + fmt(valor) + '</strong></div>';
        }

        return '<div style="text-align:center;margin-bottom:24px;">'
            + '<img src="' + LOGO_URL + '" alt="MPG Academy" style="height:48px;object-fit:contain;margin-bottom:12px;">'
            + '<p style="margin:0;font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.08em;">Recibo de Mensalidade</p>'
            + '</div>'

            + '<hr class="recibo__divider">'

            + '<div style="margin-bottom:16px;">'
            + '<div class="recibo__row"><span>Aluno</span><strong>' + d.aluno + '</strong></div>'
            + '<div class="recibo__row"><span>Turma</span><strong>' + d.turma + '</strong></div>'
            + '<div class="recibo__row"><span>Refer&ecirc;ncia</span><strong>' + d.ref + '</strong></div>'
            + '<div class="recibo__row"><span>Vencimento</span><strong>' + fmtDate(d.vencimento) + '</strong></div>'
            + (d.pagamento ? '<div class="recibo__row"><span>Data do pagamento</span><strong>' + fmtDate(d.pagamento) + '</strong></div>' : '')
            + '</div>'

            + '<hr class="recibo__divider">'

            + tagHtml

            + itensHtml

            + '<hr class="recibo__divider">'

            + '<div class="recibo__row recibo__row--total"><span>Total pago</span><span>' + fmt(total) + '</span></div>'

            + '<hr class="recibo__divider">'

            + '<p style="text-align:center;color:#555;font-size:11px;margin:0;">'
            + 'MPG Academy — Escola de V&ocirc;lei<br>'
            + 'Recibo gerado em ' + new Date().toLocaleDateString('pt-BR')
            + '</p>';
    }

    function openModal(d) {
        content.innerHTML = buildRecibo(d);
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btnVerRecibo');
        if (!btn) return;
        e.preventDefault();
        openModal({
            ref:        btn.dataset.ref,
            turma:      btn.dataset.turma,
            aluno:      btn.dataset.aluno || ALUNO_NOME,
            valor:      btn.dataset.valor,
            vencimento: btn.dataset.vencimento,
            pagamento:  btn.dataset.pagamento || '',
        });
    });
}());
</script>

</body>
</html>
