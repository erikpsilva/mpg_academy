<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/mercadopago.php';

$aluno         = $_SESSION['aluno'];
$mensalidadeId = (int) ($_GET['mensalidade_id'] ?? 0);

if ($mensalidadeId <= 0) {
    header('Location: ' . BASE_URL . '/mensalidades');
    exit;
}

$pdo = getDbConnection();

$stMens = $pdo->prepare("
    SELECT m.id, m.referencia, m.tipo, m.descricao, m.valor, m.matricula_valor, m.vencimento, m.status,
           COALESCE(t.nome, '') AS turma_nome
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.id = ? AND m.aluno_id = ? AND m.status != 'pago'
");
$stMens->execute([$mensalidadeId, $aluno['id']]);
$mens = $stMens->fetch();

if (!$mens) {
    header('Location: ' . BASE_URL . '/mensalidades');
    exit;
}

$valor          = (float) $mens['valor'];
$matriculaValor = (float) ($mens['matricula_valor'] ?? 0);
$valorMensalidade = $valor - $matriculaValor; // valor da mensalidade sem a matrícula
$hoje       = new DateTime('today');
$venc       = new DateTime($mens['vencimento']);
$isAtrasado = $mens['status'] === 'atrasado';
$dias       = 0;
$multa      = 0.0;
$juros      = 0.0;
$total      = $valor;

if ($isAtrasado) {
    $dias  = (int) $venc->diff($hoje)->days;
    $multa = $valor * 0.05;
    $base  = $valor + $multa;
    $juros = $base * 0.005 * $dias;
    $total = round($base + $juros, 2);
}

$isAvulso = ($mens['tipo'] ?? 'mensalidade') === 'avulso';
$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
if ($isAvulso) {
    $refLabel = htmlspecialchars($mens['descricao'] ?? 'Cobrança extra');
} else {
    [$refAno, $refMes] = explode('-', $mens['referencia']);
    $refLabel = ($meses[$refMes] ?? $refMes) . '/' . $refAno;
}

$publicKey = mpPublicKey($pdo);
$modoTeste = mpModoTeste($pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Pagamento</title>
<?php include ROOT . '/includes/assets.php'; ?>
<style>
.payPage { min-height: 80vh; display: flex; align-items: flex-start; justify-content: center; padding: 48px 16px; }
.payCard { background: #111; border: 1px solid #222; border-radius: 14px; width: 100%; max-width: 520px; padding: 32px; }
.payCard__back { display: inline-block; color: #888; font-size: 13px; margin-bottom: 24px; text-decoration: none; }
.payCard__back:hover { color: #e5c200; }
.payCard__title { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
.payCard__sub { color: #888; font-size: 14px; margin-bottom: 24px; }
.payCard__summary { background: #1a1a1a; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; }
.payCard__summaryRow { display: flex; justify-content: space-between; font-size: 13px; color: #aaa; padding: 4px 0; }
.payCard__summaryRow--total { font-size: 17px; font-weight: 700; color: #fff; border-top: 1px solid #2a2a2a; margin-top: 8px; padding-top: 12px; }
.payCard__testBadge { background: #2a2a00; color: #cccc00; border: 1px solid #666600; border-radius: 6px; font-size: 11px; font-weight: 700; padding: 4px 10px; display: inline-block; margin-bottom: 16px; }
/* Seletor de método */
.payMethodSelect { display: flex; gap: 8px; margin-bottom: 20px; }
.payMethodBtn {
    flex: 1; padding: 12px 8px; background: #1a1a1a; border: 1px solid #333;
    border-radius: 8px; color: #aaa; font-size: 12px; font-weight: 600;
    cursor: pointer; text-align: center; transition: all .15s;
}
.payMethodBtn:hover { border-color: #555; color: #fff; }
.payMethodBtn.is-active { border-color: #e5c200; color: #e5c200; background: #1f1d00; }
.payMethodBtn i { display: block; font-size: 20px; margin-bottom: 4px; }
/* Sucesso / Pendente */
#paymentSuccess { text-align: center; padding: 32px 0; }
#paymentSuccess h2 { font-size: 22px; margin-bottom: 8px; color: #7ecf7e; }
#paymentSuccess p { color: #aaa; margin-bottom: 24px; }
/* PIX resultado */
#paymentPix { text-align: center; padding: 16px 0; }
#paymentPix h2 { font-size: 18px; margin-bottom: 4px; }
#paymentPix p { color: #aaa; font-size: 13px; margin-bottom: 20px; }
.pixQr { width: 200px; height: 200px; border-radius: 8px; background: #fff; padding: 8px;
          margin: 0 auto 16px; display: block; }
.pixCopiaField { width: 100%; background: #1a1a1a; border: 1px solid #333; border-radius: 8px;
                  color: #ccc; font-size: 11px; padding: 10px; resize: none;
                  font-family: monospace; box-sizing: border-box; }
.pixCopyBtn { width: 100%; margin: 12px 0; padding: 14px; background: #00b37e; color: #fff;
               border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
</head>
<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="payPage">
    <div class="payCard">
        <a href="<?= BASE_URL ?>/mensalidades" class="payCard__back">&#8592; Voltar para Mensalidades</a>

        <!-- ── Formulário de pagamento ──────────────────────────────────── -->
        <div id="paymentForm">
            <?php if ($modoTeste): ?>
            <div class="payCard__testBadge">MODO DE TESTE — nenhum valor real será cobrado</div>
            <?php endif; ?>

            <h1 class="payCard__title"><?= $isAvulso ? 'Pagar Cobrança' : 'Pagar Mensalidade' ?></h1>
            <p class="payCard__sub"><?= $isAvulso ? htmlspecialchars($mens['descricao'] ?? '') : htmlspecialchars($mens['turma_nome']) ?></p>

            <div class="payCard__summary">
                <div class="payCard__summaryRow">
                    <span>Referência</span><span><?= $refLabel ?></span>
                </div>
                <div class="payCard__summaryRow">
                    <span>Vencimento</span><span><?= $venc->format('d/m/Y') ?></span>
                </div>
                <?php if ($matriculaValor > 0): ?>
                <div class="payCard__summaryRow">
                    <span>Mensalidade</span>
                    <span>R$ <?= number_format($valorMensalidade, 2, ',', '.') ?></span>
                </div>
                <div class="payCard__summaryRow">
                    <span>Taxa de matrícula</span>
                    <span>R$ <?= number_format($matriculaValor, 2, ',', '.') ?></span>
                </div>
                <?php else: ?>
                <div class="payCard__summaryRow">
                    <span>Valor</span>
                    <span>R$ <?= number_format($valor, 2, ',', '.') ?></span>
                </div>
                <?php endif; ?>
                <?php if ($isAtrasado): ?>
                <div class="payCard__summaryRow">
                    <span>Multa (5%)</span>
                    <span>R$ <?= number_format($multa, 2, ',', '.') ?></span>
                </div>
                <div class="payCard__summaryRow">
                    <span>Juros (0,5%/dia — <?= $dias ?> dias)</span>
                    <span>R$ <?= number_format($juros, 2, ',', '.') ?></span>
                </div>
                <?php endif; ?>
                <div class="payCard__summaryRow payCard__summaryRow--total">
                    <span>Total</span>
                    <span>R$ <?= number_format($total, 2, ',', '.') ?></span>
                </div>
            </div>

            <!-- Seletor de método -->
            <div class="payMethodSelect">
                <button class="payMethodBtn is-active" id="btnMethodCard" onclick="selectMethod('card')">
                    <i class="icon-creditcard"></i>Crédito / Débito
                </button>
                <button class="payMethodBtn" id="btnMethodPix" onclick="selectMethod('pix')">
                    <i>&#9635;</i>PIX
                </button>
            </div>

            <!-- Área cartão -->
            <div id="areaCard">
                <div id="cardPaymentBrick_container"></div>
            </div>

            <!-- Área PIX -->
            <div id="areaPix" style="display:none;text-align:center;padding:8px 0;">
                <p style="color:#aaa;font-size:13px;margin-bottom:16px;">
                    Clique abaixo para gerar o QR Code PIX de
                    <strong style="color:#fff;">R$ <?= number_format($total, 2, ',', '.') ?></strong>.
                </p>
                <button id="btnGerarPix"
                        style="width:100%;padding:14px;background:#e5c200;color:#111;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;">
                    Gerar QR Code PIX
                </button>
            </div>
        </div>

        <!-- ── Aprovado ──────────────────────────────────────────────────── -->
        <div id="paymentSuccess" style="display:none;text-align:center;padding:32px 0;">
            <div style="font-size:48px;margin-bottom:16px;">&#9989;</div>
            <h2 style="font-size:22px;margin-bottom:8px;color:#7ecf7e;">Pagamento aprovado!</h2>
            <p style="color:#aaa;margin-bottom:24px;">
                Mensalidade <strong><?= $refLabel ?></strong> quitada com sucesso.
            </p>
            <a href="<?= BASE_URL ?>/mensalidades" class="btn btn--primary">Ver mensalidades</a>
        </div>

        <!-- ── Em análise (cartão) ───────────────────────────────────────── -->
        <div id="paymentPending" style="display:none;text-align:center;padding:24px 0;">
            <p style="color:#cccc00;">&#9203; Pagamento em análise. Você receberá uma confirmação em breve.</p>
            <a href="<?= BASE_URL ?>/mensalidades" class="btn btn--primary" style="margin-top:12px;">
                Ver mensalidades
            </a>
        </div>

        <!-- ── PIX gerado ────────────────────────────────────────────────── -->
        <div id="paymentPix" style="display:none;text-align:center;padding:16px 0;">
            <div style="font-size:36px;margin-bottom:8px;">&#128241;</div>
            <h2 style="font-size:18px;margin-bottom:4px;">Pague com PIX</h2>
            <p style="color:#aaa;font-size:13px;margin-bottom:20px;">
                Escaneie o QR Code ou copie o código abaixo.<br>
                O status é atualizado em alguns minutos após o pagamento.
            </p>
            <img id="pixQrImg" src="" alt="QR Code PIX" class="pixQr">
            <textarea id="pixCopiaCola" class="pixCopiaField" readonly rows="4"></textarea>
            <button class="pixCopyBtn" onclick="copiarPix()">Copiar código PIX</button>
            <a href="<?= BASE_URL ?>/mensalidades"
               style="display:block;color:#888;font-size:13px;text-decoration:none;margin-top:4px;">
                Ir para Mensalidades
            </a>
        </div>
    </div>
</main>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>

<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
var BASE_URL       = "<?= BASE_URL ?>";
var MP_PUBLIC_KEY  = "<?= $publicKey ?>";
var MENSALIDADE_ID = <?= $mensalidadeId ?>;
var TOTAL_AMOUNT   = <?= $total ?>;
var ALUNO_EMAIL    = "<?= htmlspecialchars($aluno['email'] ?? '') ?>";

// ── Seletor de método ────────────────────────────────────────────────────────
function selectMethod(method) {
    document.getElementById('btnMethodCard').classList.toggle('is-active', method === 'card');
    document.getElementById('btnMethodPix').classList.toggle('is-active', method === 'pix');
    document.getElementById('areaCard').style.display  = method === 'card' ? '' : 'none';
    document.getElementById('areaPix').style.display   = method === 'pix'  ? '' : 'none';
}

// ── Brick cartão ─────────────────────────────────────────────────────────────
(function () {
    var mp            = new MercadoPago(MP_PUBLIC_KEY, { locale: 'pt-BR' });
    var bricksBuilder = mp.bricks();

    bricksBuilder.create('cardPayment', 'cardPaymentBrick_container', {
        initialization: {
            amount: TOTAL_AMOUNT,
            payer:  { email: ALUNO_EMAIL },
        },
        customization: {
            paymentMethods: { maxInstallments: 1 },
        },
        callbacks: {
            onReady: function () {},
            onSubmit: function (formData) {
                return new Promise(function (resolve, reject) {
                    fetch(BASE_URL + '/services/site/criar_pagamento.php', {
                        method:      'POST',
                        headers:     { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body:        JSON.stringify(Object.assign({}, formData, { mensalidade_id: MENSALIDADE_ID })),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success && data.status === 'approved') {
                            document.getElementById('paymentForm').style.display    = 'none';
                            document.getElementById('paymentSuccess').style.display = '';
                            resolve();
                        } else if (data.success) {
                            document.getElementById('paymentForm').style.display    = 'none';
                            document.getElementById('paymentPending').style.display = '';
                            resolve();
                        } else {
                            reject();
                        }
                    })
                    .catch(reject);
                });
            },
            onError: function (error) {
                console.error('MP Brick error:', error);
            },
        },
    });
}());

// ── PIX ──────────────────────────────────────────────────────────────────────
document.getElementById('btnGerarPix').addEventListener('click', function () {
    var btn = this;
    btn.disabled    = true;
    btn.textContent = 'Gerando...';

    fetch(BASE_URL + '/services/site/criar_pagamento.php', {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body:        JSON.stringify({
            mensalidade_id:    MENSALIDADE_ID,
            payment_method_id: 'pix',
            payer:             { email: ALUNO_EMAIL },
        }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success && data.status === 'pix_pending') {
            if (data.qr_code_base64) {
                document.getElementById('pixQrImg').src = 'data:image/png;base64,' + data.qr_code_base64;
            }
            document.getElementById('pixCopiaCola').value        = data.qr_code || '';
            document.getElementById('paymentForm').style.display = 'none';
            document.getElementById('paymentPix').style.display  = '';
        } else {
            btn.disabled    = false;
            btn.textContent = 'Tentar novamente';
            alert(data.message || 'Erro ao gerar PIX. Tente novamente.');
        }
    })
    .catch(function () {
        btn.disabled    = false;
        btn.textContent = 'Tentar novamente';
        alert('Erro de conexão. Tente novamente.');
    });
});

function copiarPix() {
    var texto = document.getElementById('pixCopiaCola').value;
    var btn   = document.querySelector('.pixCopyBtn');
    var done  = function () {
        btn.textContent = '&#10003; Código copiado!';
        setTimeout(function () { btn.textContent = 'Copiar código PIX'; }, 3000);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(done);
    } else {
        document.getElementById('pixCopiaCola').select();
        document.execCommand('copy');
        done();
    }
}
</script>

</body>
</html>
