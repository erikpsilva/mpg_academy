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

// Mensalidades em ordem decrescente
$stMens = $pdo->prepare("
    SELECT referencia, valor, vencimento, data_pagamento, status
    FROM mensalidades
    WHERE aluno_id = ?
    ORDER BY referencia DESC
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
    $proxVencDate = new DateTime($proxDate->format('Y-m') . '-05');

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
                        $isLate   = $m['status'] === 'atrasado';
                        $isPaid   = $m['status'] === 'pago';
                        $refLabel = refLabel($m['referencia'], $meses);
                    ?>

                    <div class="studentMonthlyTable__row<?= $isLate ? ' studentMonthlyTable__row--late' : '' ?>"
                         role="row"
                         data-status="<?= htmlspecialchars($m['status']) ?>">
                        <span data-label="Refer&ecirc;ncia"><?= $refLabel ?></span>
                        <span data-label="Vencimento"><?= fmtDate($m['vencimento']) ?></span>
                        <span data-label="Valor">R$ <?= number_format((float)$m['valor'], 2, ',', '.') ?></span>

                        <span data-label="Status">
                            <?php if ($isLate): ?>
                                <b class="studentMonthlyStatus studentMonthlyStatus--late">Atrasado</b>
                                <small>Vencido h&aacute; <?= $m['dias_atraso'] ?> dia<?= $m['dias_atraso'] === 1 ? '' : 's' ?></small>
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
                            <?php if ($isLate): ?>
                                <a class="studentMonthlyPay" href="#">Pagar agora</a>
                            <?php else: ?>
                                <a class="studentMonthlyReceipt" href="#">Ver recibo <i class="icon-go"></i></a>
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

<script>
(function () {
    var sel    = document.getElementById('filtroStatus');
    var table  = document.querySelector('.studentMonthlyTable');
    if (!sel || !table) return;

    sel.addEventListener('change', function () {
        var filter = this.value;
        table.querySelectorAll('[data-status]').forEach(function (el) {
            if (!filter || el.dataset.status === filter) {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    });
}());
</script>

</body>
</html>
