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

$souAdmin = ($_SESSION['usuario']['nivel_acesso'] ?? '') === 'admin';

$stConfirmados = $pdo->prepare("
    SELECT bi.id AS inscricao_id, j.nome, j.celular, j.altura_cm, j.foto, bi.pago_em
    FROM batebola_inscricoes bi
    JOIN jogadores_batebola j ON j.id = bi.jogador_id
    WHERE bi.data_evento = ? AND bi.status = 'pago'
    ORDER BY j.nome ASC
");
$stConfirmados->execute([$dataSelecionada]);
$confirmados = $stConfirmados->fetchAll();

$meses = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
?>
<!DOCTYPE html>
<html>
<head>
<title>MPG Academy - Admin - Bate Bola - Lista de Presença</title>
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
                    <h2>Bate Bola — <span>Lista de Presença</span></h2>
                    <p>Jogadores que já pagaram e garantiram vaga no domingo selecionado.</p>
                </div>
                <div class="col-md-4">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum"><?= count($confirmados) ?>/<?= BATEBOLA_MAX_VAGAS ?></span>
                        <span class="interessados__totalLabel">Confirmados</span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12" style="margin-bottom:18px;">
                    <form method="get">
                        <label style="color:#aaa;font-size:13px;margin-right:10px;">Domingo:</label>
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
                </div>
            </div>

            <?php if ($souAdmin): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="bbIncluir">
                        <div class="bbIncluir__texto">
                            <h3>Incluir alguém na lista</h3>
                            <p>
                                Use quando você já recebeu por fora (PIX na mão, dinheiro).
                                A pessoa entra como <strong>paga</strong> e o movimento aparece no sininho como lembrete.
                            </p>
                        </div>
                        <div class="bbIncluir__acao">
                            <select id="bbJogadorSelect">
                                <option value="">Carregando jogadores...</option>
                            </select>
                            <button type="button" class="btn btn--primary" id="bbIncluirBtn" disabled>Incluir como pago</button>
                        </div>
                    </div>
                    <p class="bbIncluir__msg" id="bbIncluirMsg" style="display:none;"></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable jogadores__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Foto</th>
                                    <th>Nome</th>
                                    <th>Celular</th>
                                    <th>Altura</th>
                                    <th>Pago em</th>
                                    <?php if ($souAdmin): ?><th>Ação</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($confirmados)): ?>
                                    <tr><td colspan="<?= $souAdmin ? 7 : 6 ?>" class="interessados__loading">Ninguém confirmou para esse domingo ainda.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($confirmados as $i => $c): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td>
                                                <span class="jogadores__thumb">
                                                    <?php if (!empty($c['foto'])): ?>
                                                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($c['foto']) ?>" alt="<?= htmlspecialchars($c['nome']) ?>" data-lightbox>
                                                    <?php else: ?>
                                                        <i class="icon-user" aria-hidden="true"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($c['nome']) ?></td>
                                            <td><?= htmlspecialchars($c['celular']) ?></td>
                                            <td><?= $c['altura_cm'] ? $c['altura_cm'] . ' cm' : '—' ?></td>
                                            <td><?= $c['pago_em'] ? (new DateTime($c['pago_em']))->format('d/m/Y H:i') : '—' ?></td>
                                            <?php if ($souAdmin): ?>
                                                <td>
                                                    <button class="btn btn--error btn--sm"
                                                            data-inscricao-id="<?= $c['inscricao_id'] ?>"
                                                            data-nome="<?= htmlspecialchars($c['nome']) ?>"
                                                            onclick="abrirRemoverConfirmacao(this)">
                                                        Excluir
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($souAdmin): ?>
        <div class="confirmModal" id="removerConfirmacaoModal">
            <div class="confirmModal__box">
                <h3>Remover confirmação</h3>
                <p>Tem certeza que deseja excluir <strong id="removerConfirmacaoNome"></strong> da lista de confirmados?<br>A vaga volta a ficar disponível.</p>
                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="removerConfirmacaoCancelar">Cancelar</button>
                    <button class="btn btn--error" id="removerConfirmacaoConfirmar">Sim, excluir</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<?php include ROOT . '/includes/lightbox.php'; ?>
<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<?php if ($souAdmin): ?>
<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var BB_DATA_EVENTO = "<?= $dataSelecionada ?>";
var __inscricaoParaRemover = null;

// Abrir a lista já dá as movimentações por vistas — zera o badge do sino, igual aos
// pedidos de uniforme. O histórico em batebola_movimentacoes continua intacto.
fetch(ADMIN_BASE_URL + '/services/marcar_movimentacoes_batebola_vistas.php', {
    method: 'POST', credentials: 'same-origin'
}).catch(function () {});

// ── Incluir jogador na lista (já pago por fora) ──────────────────────────────
(function () {
    var select = document.getElementById('bbJogadorSelect');
    var botao  = document.getElementById('bbIncluirBtn');
    var msg    = document.getElementById('bbIncluirMsg');
    if (!select || !botao) return;

    function aviso(texto, ok) {
        msg.textContent   = texto;
        msg.style.color   = ok ? '#7ecf7e' : '#e57373';
        msg.style.display = '';
    }

    var ESTRELAS = function (n) {
        return '★'.repeat(Math.max(0, n)) + '☆'.repeat(Math.max(0, 5 - n));
    };

    fetch(ADMIN_BASE_URL + '/services/get_jogadores_fora_lista.php?data_evento=' + encodeURIComponent(BB_DATA_EVENTO),
          { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                select.innerHTML = '<option value="">Erro ao carregar</option>';
                aviso(data.message || 'Não foi possível carregar os jogadores.', false);
                return;
            }
            if (!data.jogadores.length) {
                select.innerHTML = '<option value="">Todos os jogadores já estão na lista</option>';
                return;
            }
            select.innerHTML = '<option value="">Escolha um jogador…</option>' +
                data.jogadores.map(function (j) {
                    var d = document.createElement('option');
                    d.value = j.id;
                    d.textContent = j.nome + '  ·  ' + ESTRELAS(j.nivel) + (j.altura_cm ? '  ·  ' + j.altura_cm + 'cm' : '');
                    return d.outerHTML;
                }).join('');
            botao.disabled = false;
        })
        .catch(function () {
            select.innerHTML = '<option value="">Erro de conexão</option>';
        });

    botao.addEventListener('click', function () {
        var id = select.value;
        if (!id) { aviso('Escolha um jogador primeiro.', false); return; }

        botao.disabled = true;
        botao.textContent = 'Incluindo...';

        var body = new URLSearchParams({ jogador_id: id, data_evento: BB_DATA_EVENTO });
        fetch(ADMIN_BASE_URL + '/services/adicionar_confirmacao_batebola.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString(),
        })
        // Parse defensivo: resposta não-JSON não pode virar erro mudo.
        .then(function (r) {
            return r.text().then(function (t) {
                try { return JSON.parse(t); }
                catch (e) {
                    console.error('Resposta não-JSON ao incluir:', t.slice(0, 500));
                    return { success: false, message: 'O servidor respondeu com erro ' + r.status + '.' };
                }
            });
        })
        .then(function (res) {
            if (res.success) { window.location.reload(); return; }
            aviso(res.message || 'Não foi possível incluir.', false);
            botao.disabled = false;
            botao.textContent = 'Incluir como pago';
        })
        .catch(function () {
            aviso('Erro de conexão.', false);
            botao.disabled = false;
            botao.textContent = 'Incluir como pago';
        });
    });
}());

function abrirRemoverConfirmacao(btn) {
    __inscricaoParaRemover = btn.getAttribute('data-inscricao-id');
    document.getElementById('removerConfirmacaoNome').textContent = btn.getAttribute('data-nome');
    document.getElementById('removerConfirmacaoModal').classList.add('confirmModal--open');
}

document.getElementById('removerConfirmacaoCancelar').addEventListener('click', function () {
    document.getElementById('removerConfirmacaoModal').classList.remove('confirmModal--open');
    __inscricaoParaRemover = null;
});

document.getElementById('removerConfirmacaoModal').addEventListener('click', function (e) {
    if (e.target === this) {
        this.classList.remove('confirmModal--open');
        __inscricaoParaRemover = null;
    }
});

document.getElementById('removerConfirmacaoConfirmar').addEventListener('click', function () {
    if (!__inscricaoParaRemover) return;
    var btn = this;
    btn.disabled = true;

    var body = new URLSearchParams({ inscricao_id: __inscricaoParaRemover });
    fetch(ADMIN_BASE_URL + '/services/remover_confirmacao_batebola.php', {
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
            alert(res.message || 'Erro ao excluir.');
            btn.disabled = false;
        }
    })
    .catch(function () {
        alert('Erro ao comunicar com o servidor.');
        btn.disabled = false;
    });
});
</script>
<?php endif; ?>

</body>
</html>
