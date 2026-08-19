<?php
if (empty($_SESSION['jogador'])) {
    header('Location: ' . BASE_URL . '/batebola');
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/batebola.php';
require_once ROOT . '/config/mercadopago.php';

$pdo        = getDbConnection();
$jogadorId  = (int) $_SESSION['jogador']['id'];
$dataEvento = batebolaProximoDomingo($pdo);

$cfg   = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'valor_batebola'")->fetch();
$valor = $cfg ? (float) $cfg['valor'] : 17.00;

$stJog = $pdo->prepare("SELECT nome FROM jogadores_batebola WHERE id = ?");
$stJog->execute([$jogadorId]);
$perfil = $stJog->fetch();
if (!$perfil) {
    header('Location: ' . BASE_URL . '/batebola');
    exit;
}

$stInsc = $pdo->prepare("SELECT status, pix_qr_code, pix_qr_code_base64 FROM batebola_inscricoes WHERE jogador_id = ? AND data_evento = ?");
$stInsc->execute([$jogadorId, $dataEvento]);
$inscricao = $stInsc->fetch();

$vagasConfirmadas   = batebolaVagasConfirmadas($pdo, $dataEvento);
$vagasEsgotadas     = $vagasConfirmadas >= BATEBOLA_MAX_VAGAS && (!$inscricao || $inscricao['status'] !== 'pago');
$inscricoesFechadas = !batebolaInscricoesAbertas() && (!$inscricao || $inscricao['status'] !== 'pago');

$meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$dtEvento = new DateTime($dataEvento);
$dataFmtExtenso = $dtEvento->format('d') . ' de ' . $meses[(int) $dtEvento->format('n') - 1];
$dataFmtCurta   = $dtEvento->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>Pagamento Bate Bola | MPG Academy</title>
<?php include ROOT . '/includes/assets.php'; ?>
<style>
.bbPayPage { min-height: 80vh; display: flex; align-items: flex-start; justify-content: center; padding: 140px 16px 60px; background: #07090a; }
.bbPayCard { background: #111415; border: 1px solid rgba(255,213,0,.3); border-radius: 14px; width: 100%; max-width: 480px; padding: 32px; color: #fff; }
.bbPayCard__back { display: inline-block; color: #888; font-size: 13px; margin-bottom: 20px; text-decoration: none; }
.bbPayCard__back:hover { color: #ffd500; }
.bbPayCard__title { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.bbPayCard__sub { color: #999; font-size: 14px; margin-bottom: 24px; }
.bbPaySummary { background: #1a1a1a; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; }
.bbPaySummary__row { display: flex; justify-content: space-between; font-size: 13px; color: #aaa; padding: 5px 0; }
.bbPaySummary__row--total { font-size: 18px; font-weight: 800; color: #ffd500; border-top: 1px solid #2a2a2a; margin-top: 8px; padding-top: 12px; }
.bbPayBtn { width: 100%; padding: 15px; background: #ffd500; color: #0b0d0f; border: none; border-radius: 8px; font-size: 15px; font-weight: 800; cursor: pointer; text-transform: uppercase; }
.bbPayBtn:disabled { opacity: .55; cursor: not-allowed; }
.bbPayMsg { color: #ff8c8c; font-size: 13px; margin-top: 10px; text-align: center; }
.bbPayPix { text-align: center; padding: 8px 0; }
.bbPayPix img { width: 200px; height: 200px; border-radius: 8px; background: #fff; padding: 8px; margin: 0 auto 16px; display: block; }
.bbPayPix textarea { width: 100%; box-sizing: border-box; background: #1a1a1a; border: 1px solid #333; border-radius: 8px; color: #ccc; font-size: 11px; padding: 10px; font-family: monospace; resize: none; }
.bbPayCopyBtn { width: 100%; margin: 12px 0; padding: 13px; background: #00b37e; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
.bbPayWaiting { color: #ffd500; font-size: 13px; margin-top: 8px; }
.bbPayOk { text-align: center; padding: 24px 0; }
.bbPayOk__icon { font-size: 48px; margin-bottom: 14px; }
.bbPayOk h2 { color: #79ff45; font-size: 22px; margin-bottom: 8px; }
.bbPayOk p { color: #aaa; margin-bottom: 20px; }
.bbPayFull { text-align: center; padding: 24px 0; }
.bbPayFull__icon { font-size: 44px; margin-bottom: 14px; }
.bbPayFull h2 { color: #ff8c8c; font-size: 20px; margin-bottom: 8px; }
.bbPayFull p { color: #aaa; }
.bbPayClosed { text-align: center; padding: 24px 0; }
.bbPayClosed__icon { font-size: 44px; margin-bottom: 14px; }
.bbPayClosed h2 { color: #ffd500; font-size: 20px; margin-bottom: 8px; }
.bbPayClosed p { color: #aaa; }
</style>
</head>
<body>

<?php include ROOT . '/includes/header/header.php'; ?>

<main class="bbPayPage">
    <div class="bbPayCard">
        <a href="<?= BASE_URL ?>/batebolainicio" class="bbPayCard__back">&#8592; Voltar</a>

        <?php if ($inscricao && $inscricao['status'] === 'pago'): ?>
        <div class="bbPayOk">
            <div class="bbPayOk__icon">✅</div>
            <h2>Vaga garantida!</h2>
            <p><?= htmlspecialchars($perfil['nome']) ?>, sua vaga pro Bate Bola de <strong style="color:#fff;"><?= $dataFmtExtenso ?></strong> está confirmada.</p>
            <a href="<?= BASE_URL ?>/batebolainicio" class="btn btn--primary">Voltar ao início</a>
        </div>

        <?php elseif ($vagasEsgotadas): ?>
        <div class="bbPayFull">
            <div class="bbPayFull__icon">🚫</div>
            <h2>Vagas esgotadas</h2>
            <p>As <?= BATEBOLA_MAX_VAGAS ?> vagas do Bate Bola de <?= $dataFmtExtenso ?> já foram preenchidas. Fica ligado pro próximo domingo!</p>
        </div>

        <?php elseif ($inscricoesFechadas): ?>
        <div class="bbPayClosed">
            <div class="bbPayClosed__icon">🗓️</div>
            <h2>Lista fechada no momento</h2>
            <p>As inscrições e pagamentos do Bate Bola abrem toda segunda-feira às 06h e fecham sexta-feira às 23h59. Volta na segunda pra garantir sua vaga!</p>
        </div>

        <?php else: ?>
        <div id="bbPayForm">
            <h1 class="bbPayCard__title">Confirmar participação</h1>
            <p class="bbPayCard__sub">Bate Bola — <?= $dataFmtExtenso ?></p>

            <div class="bbPaySummary">
                <div class="bbPaySummary__row"><span>Jogador</span><span><?= htmlspecialchars($perfil['nome']) ?></span></div>
                <div class="bbPaySummary__row"><span>Data</span><span><?= $dataFmtCurta ?> (domingo)</span></div>
                <div class="bbPaySummary__row"><span>Horário</span><span>10h às 13h</span></div>
                <div class="bbPaySummary__row"><span>Local</span><span>Quadra Orion</span></div>
                <div class="bbPaySummary__row bbPaySummary__row--total"><span>Total (PIX)</span><span>R$ <?= number_format($valor, 2, ',', '.') ?></span></div>
            </div>

            <p style="color:#888;font-size:12px;margin-bottom:14px;text-align:center;">
                <?= $vagasConfirmadas ?>/<?= BATEBOLA_MAX_VAGAS ?> vagas confirmadas — sua vaga só é garantida após a confirmação do PIX.
            </p>

            <?php if ($inscricao && $inscricao['status'] === 'pendente' && !empty($inscricao['pix_qr_code'])): ?>
            <button class="bbPayBtn" id="btnGerarPixBatebola">Ver PIX gerado</button>
            <?php else: ?>
            <button class="bbPayBtn" id="btnGerarPixBatebola"><?= mpCheckoutModo($pdo) === 'pro' ? 'Pagar com PIX e confirmar vaga' : 'Gerar PIX e confirmar vaga' ?></button>
            <?php endif; ?>
            <div id="bbPayMsg" class="bbPayMsg" style="display:none;"></div>
        </div>

        <div id="bbPayPix" class="bbPayPix" style="display:none;">
            <h2 style="font-size:17px;margin-bottom:4px;">📱 Pague com PIX</h2>
            <p style="color:#999;font-size:13px;margin-bottom:16px;">Escaneie o QR Code ou copie o código abaixo.</p>
            <img id="bbPixQrImg" src="" alt="QR Code PIX">
            <textarea id="bbPixCopiaCola" rows="4" readonly></textarea>
            <button class="bbPayCopyBtn" id="bbPixCopyBtn">Copiar código PIX</button>
            <p class="bbPayWaiting" id="bbPayWaiting">⏳ Aguardando confirmação do pagamento…</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
<script>
var BASE_URL = "<?= BASE_URL ?>";
var ROTULO_BTN = "<?= mpCheckoutModo($pdo) === 'pro' ? 'Pagar com PIX e confirmar vaga' : 'Gerar PIX e confirmar vaga' ?>";
(function () {
    var btn = document.getElementById('btnGerarPixBatebola');
    if (!btn) return;

    var form    = document.getElementById('bbPayForm');
    var pixArea = document.getElementById('bbPayPix');
    var msg     = document.getElementById('bbPayMsg');
    var pollTimer = null;

    function iniciarPolling() {
        pollTimer = setInterval(function () {
            fetch(BASE_URL + '/services/site/status_pagamento_batebola.php', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                        if (data.success && data.status === 'pago') {
                        clearInterval(pollTimer);
                        window.location.reload();
                    }
                });
        }, 4000);
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.textContent = 'Gerando PIX...';
        msg.style.display = 'none';

        fetch(BASE_URL + '/services/site/criar_pagamento_batebola.php', {
            method: 'POST', credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            // Checkout Pro: o PIX é gerado na página do Mercado Pago.
            if (data.success && data.status === 'redirect' && data.init_point) {
                window.location.href = data.init_point;
                return;
            }
            if (data.success && data.status === 'pago') {
                window.location.reload();
                return;
            }
            if (data.success && data.status === 'pix_pending') {
                if (data.qr_code_base64) {
                    document.getElementById('bbPixQrImg').src = 'data:image/png;base64,' + data.qr_code_base64;
                }
                document.getElementById('bbPixCopiaCola').value = data.qr_code || '';
                form.style.display = 'none';
                pixArea.style.display = '';
                iniciarPolling();
                return;
            }
            msg.textContent = data.message || 'Erro ao gerar PIX. Tente novamente.';
            msg.style.display = '';
            btn.disabled = false;
            btn.textContent = 'Gerar PIX e confirmar vaga';
            if (data.esgotado || data.fechado) window.location.reload();
        })
        .catch(function () {
            msg.textContent = 'Erro de conexão. Tente novamente.';
            msg.style.display = '';
            btn.disabled = false;
            btn.textContent = 'Gerar PIX e confirmar vaga';
        });
    });

    // Se já existe pix pendente renderizado no PHP (reentrada na página), busca de novo e mostra.
    if (btn.textContent.indexOf('Ver PIX') !== -1) {
        btn.click();
    }

    document.getElementById('bbPixCopyBtn') && document.getElementById('bbPixCopyBtn').addEventListener('click', function () {
        var el = document.getElementById('bbPixCopiaCola');
        var b  = this;
        var done = function () {
            b.textContent = '✓ Código copiado!';
            setTimeout(function () { b.textContent = 'Copiar código PIX'; }, 3000);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(el.value).then(done);
        } else {
            el.select();
            document.execCommand('copy');
            done();
        }
    });
}());
</script>
</body>
</html>
