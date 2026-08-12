<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/batebola.php';
$pdo = getDbConnection();

$proximoDomingo = batebolaProximoDomingo($pdo);

$baseDomingo = new DateTime('today');
if ((int) $baseDomingo->format('w') !== 0) {
    $baseDomingo->modify('last sunday');
}
$cursor = (clone $baseDomingo)->modify('-4 weeks');

$opcoes = [];
for ($i = 0; $i < 17; $i++) {
    $opcoes[] = $cursor->format('Y-m-d');
    $cursor->modify('+7 days');
}

$dataSelecionada = $_GET['data'] ?? $proximoDomingo;
$dt = DateTime::createFromFormat('Y-m-d', $dataSelecionada);
if (!$dt || $dt->format('Y-m-d') !== $dataSelecionada || (int) $dt->format('w') !== 0) {
    $dataSelecionada = $proximoDomingo;
}
if (!in_array($dataSelecionada, $opcoes, true)) {
    $opcoes[] = $dataSelecionada;
    sort($opcoes);
}

$chaveSorteio = 'batebola_times_' . $dataSelecionada;

if (isset($_GET['cancelar'])) {
    $stCancelar = $pdo->prepare("DELETE FROM configuracoes WHERE chave = ?");
    $stCancelar->execute([$chaveSorteio]);
    header('Location: ' . BASE_URL . '/admin/batebolatimes?data=' . urlencode($dataSelecionada));
    exit;
}

if (isset($_GET['seed'])) {
    $seedNovo = max(1, (int) $_GET['seed']);
    $stSalvar = $pdo->prepare("
        INSERT INTO configuracoes (chave, valor)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = CURRENT_TIMESTAMP
    ");
    $stSalvar->execute([$chaveSorteio, (string) $seedNovo]);
    header('Location: ' . BASE_URL . '/admin/batebolatimes?data=' . urlencode($dataSelecionada));
    exit;
}

$stSeed = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
$stSeed->execute([$chaveSorteio]);
$seedSalvo = $stSeed->fetchColumn();
$jaSorteou = $seedSalvo !== false;
$seed      = $jaSorteou ? (int) $seedSalvo : null;

$stConfirmados = $pdo->prepare("
    SELECT j.id, j.nome, j.nivel, j.altura_cm, j.sexo, j.foto
    FROM batebola_inscricoes bi
    JOIN jogadores_batebola j ON j.id = bi.jogador_id
    WHERE bi.data_evento = ? AND bi.status = 'pago'
    ORDER BY j.nome ASC
");
$stConfirmados->execute([$dataSelecionada]);
$confirmados = $stConfirmados->fetchAll();

$times = $jaSorteou ? batebolaSortearTimes($confirmados, $seed) : [];

$corClasse = [
    'Azul'     => 'batebolaTime--azul',
    'Vermelho' => 'batebolaTime--vermelho',
    'Amarelo'  => 'batebolaTime--amarelo',
    'Verde'    => 'batebolaTime--verde',
];

$meses = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
$novoSeed = random_int(1, 999999);
?>
<!DOCTYPE html>
<html>
<head>
<title>MPG Academy - Admin - Bate Bola - Sortear Times</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="jogadores">
            <div class="row jogadores__header">
                <div class="col-md-8">
                    <h2>Bate Bola — <span>Sortear Times</span></h2>
                    <p>Monta times de 6 equilibrando nível de jogo, altura e mistura de homens/mulheres.</p>
                </div>
                <div class="col-md-4">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum"><?= count($confirmados) ?>/<?= BATEBOLA_MAX_VAGAS ?></span>
                        <span class="interessados__totalLabel">Confirmados</span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12" style="margin-bottom:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                    <form method="get" style="display:flex;align-items:center;gap:10px;">
                        <label style="color:#aaa;font-size:13px;">Domingo:</label>
                        <select name="data" onchange="this.form.submit()"
                                style="background:#1a1a1a;border:1px solid #333;border-radius:6px;color:#ddd;font-size:14px;padding:9px 12px;">
                            <?php foreach ($opcoes as $data): ?>
                                <?php
                                $dtOp = new DateTime($data);
                                $label = $dtOp->format('d') . ' ' . $meses[(int) $dtOp->format('n') - 1] . ' ' . $dtOp->format('Y');
                                if ($data === $proximoDomingo) $label .= ' (próximo)';
                                ?>
                                <option value="<?= $data ?>" <?= $data === $dataSelecionada ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if (!empty($confirmados)): ?>
                        <a class="btn btn--primary btn--sm" href="?data=<?= $dataSelecionada ?>&seed=<?= $novoSeed ?>">
                            <?= $jaSorteou ? 'Sortear novamente' : 'Sortear Times' ?>
                        </a>
                        <?php if ($jaSorteou): ?>
                            <a class="btn btn--gray btn--sm" href="?data=<?= $dataSelecionada ?>&cancelar=1">Cancelar sorteio</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($confirmados)): ?>
                <div class="row">
                    <div class="col-md-12">
                        <p style="color:#888;">Ninguém confirmou para esse domingo ainda — não dá pra sortear times.</p>
                    </div>
                </div>
            <?php elseif (!$jaSorteou): ?>
                <div class="row">
                    <div class="col-md-12">
                        <p style="color:#888;">
                            <?= count($confirmados) ?> jogador<?= count($confirmados) === 1 ? '' : 'es' ?> confirmado<?= count($confirmados) === 1 ? '' : 's' ?> pra esse domingo.
                            Clique em "Sortear Times" pra montar os times.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row batebolaTimes">
                    <?php foreach ($times as $time): ?>
                        <div class="col-md-6">
                            <div class="batebolaTime <?= $corClasse[$time['cor']] ?>">
                                <div class="batebolaTime__head">
                                    <h3>Time <?= $time['cor'] ?></h3>
                                    <span><?= count($time['jogadores']) ?> jogadores</span>
                                </div>
                                <ul class="batebolaTime__lista">
                                    <?php foreach ($time['jogadores'] as $j): ?>
                                        <li>
                                            <span class="jogadores__thumb">
                                                <?php if (!empty($j['foto'])): ?>
                                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($j['foto']) ?>" alt="<?= htmlspecialchars($j['nome']) ?>">
                                                <?php else: ?>
                                                    <i class="icon-user" aria-hidden="true"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="batebolaTime__nome"><?= htmlspecialchars($j['nome']) ?></span>
                                            <span class="batebolaTime__meta">
                                                <?= str_repeat('★', (int) $j['nivel']) . str_repeat('☆', 5 - (int) $j['nivel']) ?>
                                                · <?= $j['altura_cm'] ?>cm
                                                · <?= $j['sexo'] === 'feminino' ? 'F' : 'M' ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

</body>
</html>
