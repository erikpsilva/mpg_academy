<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/batebola.php';
$pdo = getDbConnection();

$hojeStr = (new DateTime('today'))->format('Y-m-d');

$baseDomingo = new DateTime('today');
if ((int) $baseDomingo->format('w') !== 0) {
    $baseDomingo->modify('last sunday');
}
$cursor = (clone $baseDomingo)->modify('-4 weeks');

$domingos = [];
for ($i = 0; $i < 17; $i++) {
    $domingos[] = $cursor->format('Y-m-d');
    $cursor->modify('+7 days');
}

$bloqueados = $pdo->query("SELECT data_evento, motivo FROM batebola_dias_bloqueados")->fetchAll(PDO::FETCH_KEY_PAIR);

$confirmadosPorData = $pdo->query("
    SELECT data_evento, COUNT(*) AS total
    FROM batebola_inscricoes
    WHERE status = 'pago'
    GROUP BY data_evento
")->fetchAll(PDO::FETCH_KEY_PAIR);

$proximoDomingo = batebolaProximoDomingo($pdo);

$meses = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
?>
<!DOCTYPE html>
<html>
<head>
<title>MPG Academy - Admin - Bate Bola - Histórico</title>
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
                    <h2>Bate Bola — <span>Histórico</span></h2>
                    <p>Controle quais domingos vão ter Bate Bola. Marque como "sem Bate Bola" os domingos em que não haverá encontro (feriados, etc).</p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable jogadores__table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Status</th>
                                    <th>Confirmados</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($domingos as $data): ?>
                                    <?php
                                    $bloqueado   = array_key_exists($data, $bloqueados);
                                    $motivo      = $bloqueados[$data] ?? '';
                                    $confirmados = (int) ($confirmadosPorData[$data] ?? 0);
                                    $ehPassado   = $data < $hojeStr;
                                    $ehProximo   = $data === $proximoDomingo;

                                    if ($bloqueado) {
                                        $statusLabel = 'Sem Bate Bola';
                                        $statusMod   = 'inativo';
                                    } elseif ($ehPassado) {
                                        $statusLabel = 'Realizado';
                                        $statusMod   = 'ativo';
                                    } else {
                                        $statusLabel = 'Agendado';
                                        $statusMod   = 'pendente';
                                    }

                                    $dt = new DateTime($data);
                                    $dataFmt = $dt->format('d') . ' ' . $meses[(int) $dt->format('n') - 1] . ' ' . $dt->format('Y');
                                    ?>
                                    <tr>
                                        <td>
                                            <?= $dataFmt ?>
                                            <?php if ($ehProximo): ?> <span class="statusBadge statusBadge--pendente">PRÓXIMO</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="statusBadge statusBadge--<?= $statusMod ?>"><?= strtoupper($statusLabel) ?></span>
                                            <?php if ($bloqueado && $motivo): ?><br><small style="color:#888;"><?= htmlspecialchars($motivo) ?></small><?php endif; ?>
                                        </td>
                                        <td><?= $confirmados ?>/<?= BATEBOLA_MAX_VAGAS ?></td>
                                        <td>
                                            <?php if (!$ehPassado): ?>
                                                <button
                                                    class="btn btn--sm <?= $bloqueado ? 'btn--primary' : 'btn--error' ?>"
                                                    data-data="<?= $data ?>"
                                                    data-bloqueado="<?= $bloqueado ? '1' : '0' ?>"
                                                    onclick="toggleBatebolaDia(this)">
                                                    <?= $bloqueado ? 'Reativar' : 'Marcar sem Bate Bola' ?>
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
                </div>
            </div>
        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

function toggleBatebolaDia(btn) {
    var data      = btn.getAttribute('data-data');
    var bloqueado = btn.getAttribute('data-bloqueado') === '1';
    var motivo    = '';

    if (bloqueado) {
        if (!window.confirm('Reativar o Bate Bola em ' + data.split('-').reverse().join('/') + '?')) return;
    } else {
        motivo = window.prompt('Motivo (opcional):') || '';
    }

    btn.disabled = true;

    var body = new URLSearchParams({ data_evento: data, motivo: motivo });
    fetch(ADMIN_BASE_URL + '/services/toggle_batebola_dia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: body.toString(),
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.success) {
            window.location.reload();
        } else {
            alert(res.message || 'Erro ao salvar.');
            btn.disabled = false;
        }
    })
    .catch(function () {
        alert('Erro ao comunicar com o servidor.');
        btn.disabled = false;
    });
}
</script>

</body>
</html>
