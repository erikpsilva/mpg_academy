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
    SELECT m.id, m.referencia, m.valor, m.vencimento, m.status, t.nome AS turma_nome
    FROM mensalidades m
    JOIN turmas t ON t.id = m.turma_id
    WHERE m.id = ? AND m.aluno_id = ? AND m.status != 'pago'
");
$stMens->execute([$mensalidadeId, $aluno['id']]);
$mens = $stMens->fetch();

if (!$mens) {
    header('Location: ' . BASE_URL . '/mensalidades');
    exit;
}

// Calcula total
$valor = (float) $mens['valor'];
$hoje  = new DateTime('today');
$venc  = new DateTime($mens['vencimento']);
$isAtrasado = $mens['status'] === 'atrasado';
$dias  = 0;
$multa = 0.0;
$juros = 0.0;
$total = $valor;

if ($isAtrasado) {
    $dias  = (int) $venc->diff($hoje)->days;
    $multa = $valor * 0.05;
    $base  = $valor + $multa;
    $juros = $base * 0.005 * $dias;
    $total = round($base + $juros, 2);
}

$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
[$refAno, $refMes] = explode('-', $mens['referencia']);
$refLabel = ($meses[$refMes] ?? $refMes) . '/' . $refAno;

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
#paymentSuccess { text-align: center; padding: 32px 0; }
#paymentSuccess .paySuccess__icon { font-size: 48px; margin-bottom: 16px; }
#paymentSuccess h2 { font-size: 22px; margin-bottom: 8px; color: #7ecf7e; }
#paymentSuccess p { color: #aaa; margin-bottom: 24px; }
</style>
</head>
<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="payPage">
    <div class="payCard">
        <a href="<?= BASE_URL ?>/mensalidades" class="payCard__back">&#8592; Voltar para Mensalidades</a>

        <div id="paymentForm">
            <?php if ($modoTeste): ?>
            <div class="payCard__testBadge">MODO DE TESTE — nenhum valor real será cobrado</div>
            <?php endif; ?>

            <h1 class="payCard__title">Pagar Mensalidade</h1>
            <p class="payCard__sub"><?= htmlspecialchars($mens['turma_nome']) ?></p>

            <div class="payCard__summary">
                <div class="payCard__summaryRow">
                    <span>Referência</span>
                    <span><?= $refLabel ?></span>
                </div>
                <div class="payCard__summaryRow">
                    <span>Vencimento</span>
                    <span><?= $venc->format('d/m/Y') ?></span>
                </div>
                <div class="payCard__summaryRow">
                    <span>Valor original</span>
                    <span>R$ <?= number_format($valor, 2, ',', '.') ?></span>
                </div>
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

            <div id="cardPaymentBrick_container"></div>
        </div>

        <div id="paymentSuccess" style="display:none;">
            <div class="paySuccess__icon">✅</div>
            <h2>Pagamento aprovado!</h2>
            <p>Mensalidade <strong><?= $refLabel ?></strong> quitada com sucesso.</p>
            <a href="<?= BASE_URL ?>/mensalidades" class="btn btn--primary">Ver mensalidades</a>
        </div>

        <div id="paymentPending" style="display:none;text-align:center;padding:24px 0;">
            <p style="color:#cccc00;">⏳ Pagamento em análise. Você receberá uma confirmação em breve.</p>
            <a href="<?= BASE_URL ?>/mensalidades" class="btn btn--primary" style="margin-top:12px;">Ver mensalidades</a>
        </div>
    </div>
</main>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>

<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
var BASE_URL         = "<?= BASE_URL ?>";
var MP_PUBLIC_KEY    = "<?= $publicKey ?>";
var MENSALIDADE_ID   = <?= $mensalidadeId ?>;
var TOTAL_AMOUNT     = <?= $total ?>;
var ALUNO_EMAIL      = "<?= htmlspecialchars($aluno['email'] ?? '') ?>";

(function () {
    var mp             = new MercadoPago(MP_PUBLIC_KEY, { locale: 'pt-BR' });
    var bricksBuilder  = mp.bricks();

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
                            // pending / in_process
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
</script>

</body>
</html>
