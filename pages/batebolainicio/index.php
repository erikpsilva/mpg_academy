<?php
if (empty($_SESSION['jogador'])) {
    header('Location: ' . BASE_URL . '/batebola');
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/batebola.php';
$pdo = getDbConnection();

// Confirma PIX do Bate Bola pagos que o webhook não avisou (no máximo 1x por minuto).
require_once ROOT . '/config/conciliacao_mp.php';
mpConciliarPendentes($pdo, ['batebola']);

$stmt = $pdo->prepare("SELECT nome, email, celular, foto, nivel FROM jogadores_batebola WHERE id = ?");
$stmt->execute([$_SESSION['jogador']['id']]);
$perfil = $stmt->fetch();

if (!$perfil) {
    unset($_SESSION['jogador']);
    header('Location: ' . BASE_URL . '/batebola');
    exit;
}

$_SESSION['jogador']['nome']  = $perfil['nome'];
$_SESSION['jogador']['nivel'] = $perfil['nivel'];
// trim antes do explode: nome gravado com espaço na frente fazia explode devolver ''
// e a saudação virava "Bem-vindo, !".
$primeiroNome = explode(' ', trim($perfil['nome']))[0];

$dataEvento = batebolaProximoDomingo($pdo);
$valor      = batebolaValorEvento($pdo, $dataEvento);
$especial   = batebolaEhEspecial($dataEvento);

$stInsc = $pdo->prepare("SELECT status FROM batebola_inscricoes WHERE jogador_id = ? AND data_evento = ?");
$stInsc->execute([$_SESSION['jogador']['id'], $dataEvento]);
$statusInscricao  = $stInsc->fetchColumn() ?: null;
$vagasConfirmadas   = batebolaVagasConfirmadas($pdo, $dataEvento);
$vagasEsgotadas     = $vagasConfirmadas >= BATEBOLA_MAX_VAGAS && $statusInscricao !== 'pago';
$inscricoesFechadas = !batebolaInscricoesAbertas() && $statusInscricao !== 'pago' && !$vagasEsgotadas;

$stConfirmados = $pdo->prepare("
    SELECT j.nome
    FROM batebola_inscricoes bi
    JOIN jogadores_batebola j ON j.id = bi.jogador_id
    WHERE bi.data_evento = ? AND bi.status = 'pago'
    ORDER BY j.nome ASC
");
$stConfirmados->execute([$dataEvento]);
$confirmados = $stConfirmados->fetchAll(PDO::FETCH_COLUMN);

// Mesma montagem que o admin usa (config/batebola.php), já com as trocas manuais aplicadas.
// Antes esta tela repetia a lógica do sorteio, e qualquer ajuste feito no admin não
// aparecia aqui — jogador e admin viam times diferentes.
$timesSorteados = batebolaTimesDoEvento($pdo, $dataEvento);

$classesTimes = [
    'Azul' => 'bateBolaTimesHome__time--azul',
    'Vermelho' => 'bateBolaTimesHome__time--vermelho',
    'Amarelo' => 'bateBolaTimesHome__time--amarelo',
    'Verde' => 'bateBolaTimesHome__time--verde',
];

$meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$dtEvento = new DateTime($dataEvento);
$dataFmtExtenso = $dtEvento->format('d') . ' de ' . $meses[(int) $dtEvento->format('n') - 1];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>Início Bate Bola | MPG Academy</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="bateBolaInicio">
    <div class="container">
        <div class="bateBolaInicio__top">
            <div class="bateBolaInicio__welcome">
                <div class="bateBolaInicio__avatar">
                    <?php if (!empty($perfil['foto'])): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($perfil['foto']) ?>" alt="Foto de <?= htmlspecialchars($perfil['nome']) ?>" data-lightbox>
                    <?php else: ?>
                        <i class="icon-user" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <span>Área do participante</span>
                    <h1>Bem-vindo, <?= htmlspecialchars($primeiroNome) ?>!</h1>
                    <p class="bateBolaInicio__lead">Bom te ver por aqui. Confira suas informações do Bate Bola.</p>
                </div>
            </div>
            <nav class="bateBolaInicio__nav">
                <a href="<?= BASE_URL ?>/batebolameusdados"><i class="icon-user" aria-hidden="true"></i> Meus Dados</a>
                <a href="<?= BASE_URL ?>/services/site/jogador_logout.php"><i class="icon-go" aria-hidden="true"></i> Sair</a>
            </nav>
        </div>

        <?php include ROOT . '/includes/batebola_home_eventos.php'; ?>

        <div class="bateBolaInicio__grid">
            <aside class="bateBolaSchedule">
                <h2>Informações do domingo tradicional</h2>
                <dl>
                    <div><dt><i class="icon-calendar" aria-hidden="true"></i> Quando</dt><dd>Domingos</dd></div>
                    <div><dt><i class="icon-timescompetitivos" aria-hidden="true"></i> Horário</dt><dd>Das <?= batebolaHorarioTexto($dataEvento) ?><?= $especial ? ' (1h a mais nessa edição)' : '' ?></dd></div>
                    <div>
                        <dt><i class="icon-zonanorte" aria-hidden="true"></i> Local</dt>
                        <dd><?= BATEBOLA_LOCAL_NOME ?><small><?= BATEBOLA_LOCAL_ENDERECO ?></small></dd>
                    </div>
                    <div><dt><i class="icon-creditcard" aria-hidden="true"></i> Valor</dt><dd>R$ <?= number_format($valor, 2, ',', '.') ?> (PIX)</dd></div>
                </dl>
            </aside>

            <aside class="bateBolaSchedule bateBolaInicio__confirmadosCard">
                <h2>Domingo: <?= count($confirmados) ?>/<?= BATEBOLA_MAX_VAGAS ?> confirmados</h2>
                <?php if (empty($confirmados)): ?>
                    <p>Ninguém confirmou ainda — seja o primeiro a garantir sua vaga!</p>
                <?php else: ?>
                    <ol class="bateBolaInicio__confirmadosList">
                        <?php foreach ($confirmados as $i => $nomeConfirmado): ?>
                            <li><span><?= $i + 1 ?></span> <?= htmlspecialchars($nomeConfirmado) ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </aside>
        </div>

        <?php if (!empty($timesSorteados)): ?>
            <section class="bateBolaTimesHome">
                <div class="bateBolaTimesHome__head">
                    <span>Times definidos</span>
                    <h2>Confira seu time para domingo</h2>
                    <p>Procure seu nome abaixo e venha com tudo para o Bate Bola.</p>
                </div>
                <div class="bateBolaTimesHome__grid">
                    <?php foreach ($timesSorteados as $time): ?>
                        <article class="bateBolaTimesHome__time <?= $classesTimes[$time['cor']] ?? '' ?>">
                            <h3><i aria-hidden="true"></i> Time <?= htmlspecialchars($time['cor']) ?></h3>
                            <ul>
                                <?php foreach ($time['jogadores'] as $membro): ?>
                                    <li class="<?= (int) $membro['id'] === (int) $_SESSION['jogador']['id'] ? 'is-current' : '' ?>">
                                        <span class="bateBolaTimesHome__player">
                                            <span class="bateBolaTimesHome__avatar">
                                                <?php if (!empty($membro['foto'])): ?>
                                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($membro['foto']) ?>" alt="Foto de <?= htmlspecialchars($membro['nome']) ?>" data-lightbox>
                                                <?php else: ?>
                                                    <i class="icon-user" aria-hidden="true"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="bateBolaTimesHome__name"><?= htmlspecialchars($membro['nome']) ?></span>
                                        </span>
                                        <?php if ((int) $membro['id'] === (int) $_SESSION['jogador']['id']): ?><strong>Você</strong><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include ROOT . '/includes/lightbox.php'; ?>
<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
</body>
</html>
