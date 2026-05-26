<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once ROOT . '/config/database.php';

$aluno   = $_SESSION['aluno'];
$alunoId = (int) $aluno['id'];
$pdo     = getDbConnection();

// ── Turma e horários do aluno ─────────────────────────────────────────────────

$stTurma = $pdo->prepare("
    SELECT t.id AS turma_id, t.nome AS turma_nome, t.data_inicio AS turma_inicio,
           q.nome AS quadra_nome, q.data_inicio_contrato AS quadra_inicio,
           ta.data_entrada
    FROM turma_alunos ta
    JOIN turmas t ON t.id = ta.turma_id
    JOIN quadras q ON q.id = t.quadra_id
    WHERE ta.aluno_id = ? AND ta.status = 'ativo'
    LIMIT 1
");
$stTurma->execute([$alunoId]);
$turma = $stTurma->fetch();

$horarios = [];
if ($turma) {
    $stHor = $pdo->prepare("
        SELECT qh.dia_semana, qh.hora_inicio, qh.hora_fim
        FROM turma_horarios th
        JOIN quadra_horarios qh ON qh.id = th.horario_id
        WHERE th.turma_id = ?
        ORDER BY qh.dia_semana, qh.hora_inicio
    ");
    $stHor->execute([$turma['turma_id']]);
    $horarios = $stHor->fetchAll();
}

// ── Limites de navegação ──────────────────────────────────────────────────────
// Regra: jan–dez do ano corrente. Em 1° de dezembro libera o próximo ano.

$anoAtual = (int) date('Y');
$mesAtual = (int) date('m');
$diaAtual = (int) date('d');

$maxAno = ($mesAtual === 12 && $diaAtual >= 1) ? $anoAtual + 1 : $anoAtual;

$minDate = new DateTime("$anoAtual-01-01");
$maxDate = new DateTime("$maxAno-12-01");   // 1° do mês limite (primeiro mês válido como primeiro da dupla)

// ── Mês corrente exibido (parâmetro ?m=YYYY-MM) ───────────────────────────────

$mParam = trim($_GET['m'] ?? '');
if (preg_match('/^(\d{4})-(\d{2})$/', $mParam)) {
    $m1 = DateTime::createFromFormat('Y-m-d', $mParam . '-01');
} else {
    $m1 = new DateTime(date('Y-m') . '-01');
}
$m1->modify('first day of this month');

// Clamp
if ($m1 < $minDate) $m1 = clone $minDate;
if ($m1 > $maxDate) $m1 = clone $maxDate;

$m1Str = $m1->format('Y-m');

// ── Segundo mês ───────────────────────────────────────────────────────────────

$m2 = clone $m1;
$m2->modify('+1 month');
$showSecond = $m2 <= new DateTime("$maxAno-12-31");

// ── Limites de navegação (prev / next) ────────────────────────────────────────

$prev = clone $m1;
$prev->modify('-1 month');
$hasPrev = $prev >= $minDate;
$prevStr = $prev->format('Y-m');

$next = clone $m1;
$next->modify('+1 month');
$hasNext = $next <= $maxDate;
$nextStr = $next->format('Y-m');

// ── Feriados nacionais com nome ───────────────────────────────────────────────

function feriadosAno(int $ano): array {
    $f = [
        "$ano-01-01" => 'Confraterniza&ccedil;&atilde;o Universal',
        "$ano-04-21" => 'Tiradentes',
        "$ano-05-01" => 'Dia do Trabalho',
        "$ano-09-07" => 'Independ&ecirc;ncia do Brasil',
        "$ano-10-12" => 'Nossa Senhora Aparecida',
        "$ano-11-02" => 'Finados',
        "$ano-11-15" => 'Proclama&ccedil;&atilde;o da Rep&uacute;blica',
        "$ano-11-20" => 'Consci&ecirc;ncia Negra',
        "$ano-12-25" => 'Natal',
    ];
    $easter = easter_date($ano);
    $f[date('Y-m-d', $easter - 48 * 86400)] = 'Carnaval';
    $f[date('Y-m-d', $easter - 47 * 86400)] = 'Carnaval';
    $f[date('Y-m-d', $easter - 2  * 86400)] = 'Sexta-feira Santa';
    $f[date('Y-m-d', $easter + 60 * 86400)] = 'Corpus Christi';
    return $f;
}

// Carrega feriados dos anos envolvidos
$anosNecessarios = array_unique([(int) $m1->format('Y'), (int) $m2->format('Y')]);
$feriados = [];
foreach ($anosNecessarios as $a) {
    $feriados += feriadosAno($a);
}

// ── Dias de treino indexados por dia_semana (0=Dom…6=Sáb) ────────────────────

$diasTreino = [];
foreach ($horarios as $h) {
    $dow = (int) $h['dia_semana'];
    $diasTreino[$dow] = [
        'inicio' => substr($h['hora_inicio'], 0, 5),
        'fim'    => substr($h['hora_fim'], 0, 5),
    ];
}

// ── Overrides do admin (turma_treinos) ───────────────────────────────────────

$overrides = [];
if ($turma) {
    foreach ($anosNecessarios as $a) {
        $stTr = $pdo->prepare("
            SELECT data_treino, status
            FROM turma_treinos
            WHERE turma_id = ? AND YEAR(data_treino) = ?
        ");
        $stTr->execute([$turma['turma_id'], $a]);
        foreach ($stTr->fetchAll() as $row) {
            $overrides[$row['data_treino']] = $row['status'];
        }
    }
}

$hojeStr = date('Y-m-d');

// Data efetiva de início: a mais recente entre data_entrada do aluno,
// data_inicio da turma e data_inicio_contrato da quadra (ignora nulos).
$dataInicio = '1970-01-01';
if ($turma) {
    $candidatas = array_filter([
        $turma['data_entrada'],
        $turma['turma_inicio'],
        $turma['quadra_inicio'],
    ]);
    if ($candidatas) $dataInicio = max($candidatas);
}

// ── Helper: monta células de um mês ──────────────────────────────────────────

function buildMonthCells(
    int $year, int $month,
    array $diasTreino, array $feriados, array $overrides,
    string $dataEntrada, string $hojeStr
): array {
    $mesStr   = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDay = new DateTime("$year-$mesStr-01");
    $daysInMonth = (int) $firstDay->format('t');
    $startPad    = (int) $firstDay->format('w'); // 0=Dom

    $cells = array_fill(0, $startPad, ['muted' => true]);

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dayStr  = str_pad($d, 2, '0', STR_PAD_LEFT);
        $dateStr = "$year-$mesStr-$dayStr";
        $dow     = (int) (new DateTime($dateStr))->format('w');

        $isHoliday = isset($feriados[$dateStr]);
        $isToday   = ($dateStr === $hojeStr);
        $isTrDay   = isset($diasTreino[$dow]) && $dateStr >= $dataEntrada;

        $cls      = '';
        $spanText = '';
        $title    = '';

        if ($isHoliday && $isTrDay) {
            $cls      = 'is-holiday';
            $spanText = 'Feriado';
            $title    = $feriados[$dateStr];
        } elseif ($isTrDay) {
            if (isset($overrides[$dateStr])) {
                $st  = $overrides[$dateStr];
                $cls = $st === 'realizado' ? 'is-done'
                     : ($st === 'cancelado' ? 'is-canceled' : 'is-confirmed');
            } else {
                $cls = ($dateStr < $hojeStr) ? 'is-done' : 'is-confirmed';
            }
            $spanText = $diasTreino[$dow]['inicio'];
        }

        if ($isToday) $cls = trim($cls . ' is-today');

        $cells[] = [
            'muted' => false,
            'day'   => $d,
            'class' => $cls,
            'span'  => $spanText,
            'title' => $title,
        ];
    }

    // Completar última linha para múltiplo de 7
    $rem = count($cells) % 7;
    if ($rem !== 0) {
        for ($i = 0; $i < 7 - $rem; $i++) {
            $cells[] = ['muted' => true];
        }
    }

    return $cells;
}

// ── Dados dos dois meses ──────────────────────────────────────────────────────

$nomesMeses = [
    1=>'Janeiro', 2=>'Fevereiro', 3=>'Mar&ccedil;o', 4=>'Abril',
    5=>'Maio', 6=>'Junho', 7=>'Julho', 8=>'Agosto',
    9=>'Setembro', 10=>'Outubro', 11=>'Novembro', 12=>'Dezembro',
];

$mes1 = [
    'ano'    => (int) $m1->format('Y'),
    'mes'    => (int) $m1->format('m'),
    'nome'   => $nomesMeses[(int) $m1->format('m')] . ' ' . $m1->format('Y'),
    'atual'  => ($m1->format('Y-m') === date('Y-m')),
    'cells'  => buildMonthCells(
        (int) $m1->format('Y'), (int) $m1->format('m'),
        $diasTreino, $feriados, $overrides, $dataInicio, $hojeStr
    ),
];

$mes2 = $showSecond ? [
    'ano'    => (int) $m2->format('Y'),
    'mes'    => (int) $m2->format('m'),
    'nome'   => $nomesMeses[(int) $m2->format('m')] . ' ' . $m2->format('Y'),
    'atual'  => ($m2->format('Y-m') === date('Y-m')),
    'cells'  => buildMonthCells(
        (int) $m2->format('Y'), (int) $m2->format('m'),
        $diasTreino, $feriados, $overrides, $dataInicio, $hojeStr
    ),
] : null;

// ── Label do horário de treino ────────────────────────────────────────────────

$nomesDias = [
    0=>'Domingo', 1=>'Segunda-feira', 2=>'Ter&ccedil;a-feira',
    3=>'Quarta-feira', 4=>'Quinta-feira', 5=>'Sexta-feira', 6=>'S&aacute;bado',
];

$horarioLabel = '';
if (!empty($horarios)) {
    $parts = [];
    foreach ($horarios as $h) {
        $parts[] = ($nomesDias[(int)$h['dia_semana']] ?? '')
                 . ' &nbsp;&middot;&nbsp; '
                 . substr($h['hora_inicio'], 0, 5)
                 . '&ndash;'
                 . substr($h['hora_fim'], 0, 5);
    }
    $horarioLabel = implode('<br>', $parts);
}

// ── Render HTML ───────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Agenda</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>

<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="studentArea studentTrainings">
    <div class="studentArea__layout">
        <aside class="studentAreaSidebar">
            <nav class="studentAreaSidebar__nav" aria-label="Menu do aluno">
                <a href="<?= BASE_URL ?>/areadoaluno"><i class="icon-home"></i> Dashboard</a>

                <strong>Geral</strong>
                <a href="<?= BASE_URL ?>/meuperfil"><i class="icon-user"></i> Meu Perfil</a>
                <a href="<?= BASE_URL ?>/mensalidades"><i class="icon-creditcard"></i> Mensalidades</a>
                <a href="<?= BASE_URL ?>/treinos" class="is-active"><i class="icon-calendar"></i> Agenda</a>
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
                <span>Agenda</span>
            </nav>

            <section class="studentTrainingsHero">
                <div class="studentTrainingsHero__title">
                    <span><i class="icon-calendar" aria-hidden="true"></i></span>
                    <div>
                        <h1>Agenda</h1>
                        <p>Calend&aacute;rio de treinos &mdash; feriados nacionais j&aacute; exclu&iacute;dos.</p>
                    </div>
                </div>

                <?php if ($turma): ?>
                <div class="studentTrainingsDetails">
                    <div class="studentTrainingsDetails__item">
                        <span>Turma</span>
                        <strong><?= htmlspecialchars($turma['turma_nome']) ?></strong>
                    </div>
                    <div class="studentTrainingsDetails__item">
                        <span>Quadra</span>
                        <strong><?= htmlspecialchars($turma['quadra_nome']) ?></strong>
                    </div>
                    <?php if ($horarioLabel): ?>
                    <div class="studentTrainingsDetails__item">
                        <span>Hor&aacute;rio</span>
                        <strong><?= $horarioLabel ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p style="color:#aaa;font-size:14px;margin-top:16px;">Voc&ecirc; n&atilde;o est&aacute; matriculado em nenhuma turma ativa.</p>
                <?php endif; ?>
            </section>

            <section class="studentTrainingCalendar">

                <div class="studentTrainingCalendar__head">
                    <div>
                        <?php if ($hasPrev): ?>
                        <a href="?m=<?= $prevStr ?>" class="calNavBtn" aria-label="M&ecirc;s anterior">
                            <i class="icon-prev"></i>
                        </a>
                        <?php else: ?>
                        <span class="calNavBtn calNavBtn--disabled" aria-disabled="true">
                            <i class="icon-prev"></i>
                        </span>
                        <?php endif; ?>

                        <h2>
                            <?= $mes1['nome'] ?>
                            <?= $mes2 ? '<span style="opacity:.4;margin:0 4px">&middot;</span>' . $mes2['nome'] : '' ?>
                        </h2>

                        <?php if ($hasNext): ?>
                        <a href="?m=<?= $nextStr ?>" class="calNavBtn" aria-label="Pr&oacute;ximo m&ecirc;s">
                            <i class="icon-next"></i>
                        </a>
                        <?php else: ?>
                        <span class="calNavBtn calNavBtn--disabled" aria-disabled="true">
                            <i class="icon-next"></i>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                $meses = $mes2 ? [$mes1, $mes2] : [$mes1];

                function renderMonth(array $m): void { ?>
                <div class="studentTrainingMonth<?= $m['atual'] ? ' is-current' : '' ?>">
                    <div class="studentTrainingMonth__title<?= $m['atual'] ? ' is-current' : '' ?>">
                        <?= $m['nome'] ?>
                    </div>
                    <div class="studentTrainingCalendar__weekdays">
                        <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>S&aacute;b</span>
                    </div>
                    <div class="studentTrainingCalendar__grid">
                        <?php foreach ($m['cells'] as $c): ?>
                        <?php if ($c['muted']): ?>
                        <article class="is-muted"><strong></strong></article>
                        <?php else: ?>
                        <article class="<?= $c['class'] ?>"<?= $c['title'] ? ' title="' . htmlspecialchars($c['title']) . '"' : '' ?>>
                            <strong><?= $c['day'] ?></strong>
                            <?php if ($c['span']): ?>
                            <span><?= htmlspecialchars($c['span']) ?></span>
                            <?php endif; ?>
                        </article>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php } ?>

                <div class="studentTraining2Months">
                    <?php foreach ($meses as $m) { renderMonth($m); } ?>
                </div>

                <ul class="studentTrainingCalendar__legend">
                    <li><i class="is-confirmed"></i> Treino confirmado</li>
                    <li><i class="is-done"></i> Treino realizado</li>
                    <li><i class="is-canceled"></i> Cancelado</li>
                    <li><i class="is-holiday"></i> Feriado</li>
                    <li><i class="is-today"></i> Hoje</li>
                </ul>

            </section>

            <section class="studentTrainingInfo">
                <h2>Informa&ccedil;&otilde;es importantes</h2>
                <div class="studentTrainingInfo__cards">
                    <article>
                        <i class="icon-calendar"></i>
                        <h3>Feriados</h3>
                        <p>Feriados nacionais n&atilde;o ter&atilde;o treino e j&aacute; est&atilde;o exclu&iacute;dos do calend&aacute;rio.</p>
                    </article>
                    <article class="is-danger">
                        <i class="icon-check"></i>
                        <h3>Cancelamentos</h3>
                        <p>Treinos podem ser cancelados pela escola. Fique atento aos comunicados.</p>
                    </article>
                    <article>
                        <i class="icon-notification"></i>
                        <h3>Altera&ccedil;&otilde;es</h3>
                        <p>Qualquer altera&ccedil;&atilde;o ser&aacute; avisada com anteced&ecirc;ncia nos Comunicados.</p>
                    </article>
                    <article>
                        <i class="icon-check"></i>
                        <h3>Presen&ccedil;a</h3>
                        <p>Em caso de falta, avise com anteced&ecirc;ncia sempre que poss&iacute;vel.</p>
                    </article>
                </div>

                <aside class="studentTrainingInfo__help">
                    <i class="icon-whatsapp"></i>
                    <div>
                        <h3>D&uacute;vidas sobre os treinos?</h3>
                        <p>Fale diretamente com nossa equipe pelo WhatsApp.</p>
                    </div>
                    <a href="https://wa.me/5511972330097" target="_blank" rel="noopener">Falar no WhatsApp</a>
                </aside>
            </section>
        </section>
    </div>

    <footer class="studentAreaFooter">
        <div>
            <img src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">
            <p>
                <a href="https://www.instagram.com/mpgacademy/" target="_blank" rel="noopener"><i class="icon-instagram"></i></a>
                <a href="https://wa.me/5511972330097" target="_blank" rel="noopener"><i class="icon-whatsapp"></i></a>
            </p>
        </div>
        <nav>
            <strong>Navega&ccedil;&atilde;o</strong>
            <a href="<?= BASE_URL ?>/areadoaluno">In&iacute;cio</a>
            <a href="<?= BASE_URL ?>">Site</a>
        </nav>
        <nav>
            <strong>Aluno</strong>
            <a href="<?= BASE_URL ?>/areadoaluno">Dashboard</a>
            <a href="<?= BASE_URL ?>/meuperfil">Meu Perfil</a>
            <a href="<?= BASE_URL ?>/mensalidades">Mensalidades</a>
            <a href="<?= BASE_URL ?>/comunicados">Comunicados</a>
        </nav>
        <nav>
            <strong>Legal</strong>
            <a href="#">Pol&iacute;tica de Privacidade</a>
            <a href="#">Termos de Uso</a>
        </nav>
        <address id="contato">
            <strong>Fale conosco</strong>
            <a href="tel:+5511972330097"><i class="icon-phonecall"></i> (11) 97233-0097</a>
            <a href="mailto:contato@mpgacademy.com.br"><i class="icon-mail"></i> contato@mpgacademy.com.br</a>
            <span><i class="icon-zonanorte"></i> Zona Norte &mdash; S&atilde;o Paulo / SP</span>
        </address>
        <small>&copy; <?= date('Y') ?> MPG Academy. Todos os direitos reservados.</small>
    </footer>
</main>

<?php include ROOT . '/includes/scripts.php'; ?>

</body>
</html>
