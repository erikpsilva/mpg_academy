<?php
/**
 * Página de pagamento para o app mobile — carregada via WebView.
 * Autentica via Bearer token na URL. Usa MP Bricks.
 */
define('ROOT', __DIR__);
require_once ROOT . '/config/app.php';
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/mercadopago.php';
require_once ROOT . '/services/site/mobile_auth.php';

// Autentica via token passado na URL
$urlToken = trim($_GET['token'] ?? '');
if ($urlToken) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $urlToken;
}

$pdo   = getDbConnection();
$aluno = getMobileAluno();

if (!$aluno) {
    http_response_code(401);
    echo '<!DOCTYPE html><html><body style="background:#050505;color:#ff5a5a;font-family:sans-serif;padding:24px;">
        <p>Sessão inválida. Faça login novamente.</p></body></html>';
    exit;
}

$mensalidadeId = (int) ($_GET['mensalidade_id'] ?? 0);
if ($mensalidadeId <= 0) { http_response_code(400); exit; }

// Busca mensalidade
$stmt = $pdo->prepare("
    SELECT m.id, m.referencia, m.valor, m.vencimento, m.status, t.nome AS turma_nome
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.id = ? AND m.aluno_id = ? AND m.status != 'pago'
");
$stmt->execute([$mensalidadeId, $aluno['id']]);
$mens = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mens) {
    echo '<!DOCTYPE html><html><body style="background:#050505;color:#ff5a5a;font-family:sans-serif;padding:24px;">
        <p>Mensalidade não encontrada ou já paga.</p></body></html>';
    exit;
}

// Calcula total com multa/juros se atrasada
$valor = (float) $mens['valor'];
$hoje  = new DateTime('today');
$venc  = new DateTime($mens['vencimento']);
$total = $valor;
$multa = 0; $juros = 0; $dias = 0;

if ($mens['status'] === 'atrasado') {
    $dias  = (int) $venc->diff($hoje)->days;
    $multa = $valor * 0.05;
    $base  = $valor + $multa;
    $juros = $base * 0.005 * $dias;
    $total = round($base + $juros, 2);
}

$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
[$rAno, $rMes] = explode('-', $mens['referencia']);
$refLabel = ($meses[$rMes] ?? $rMes) . '/' . $rAno;

$publicKey = mpPublicKey($pdo);
$modoTeste = mpModoTeste($pdo);
$apiBase   = appBaseUrl();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>Pagamento</title>
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet" media="none">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #050505; color: #fff; font-family: -apple-system, sans-serif; padding: 20px; min-height: 100vh; }
    .badge-teste { background: #2a2a00; color: #cccc00; border: 1px solid #666600; border-radius: 6px; font-size: 11px; font-weight: 700; padding: 4px 10px; display: inline-block; margin-bottom: 16px; }
    .summary { background: #111; border: 1px solid #2a2a2a; border-radius: 12px; padding: 18px; margin-bottom: 20px; }
    .summary-row { display: flex; justify-content: space-between; font-size: 14px; color: #aaa; padding: 5px 0; }
    .summary-total { font-size: 18px; font-weight: 900; color: #fff; border-top: 1px solid #2a2a2a; margin-top: 8px; padding-top: 12px; }
    .summary-total span:last-child { color: #e5c200; }
    #paymentSuccess { text-align: center; padding: 40px 20px; display: none; }
    #paymentSuccess h2 { color: #79ff45; font-size: 22px; margin-bottom: 10px; }
    #paymentSuccess p { color: #aaa; font-size: 14px; }
    #paymentPending { text-align: center; padding: 40px 20px; display: none; }
    #paymentPending p { color: #ffcc00; font-size: 14px; }
    .ql-toolbar, .ql-container { display: none !important; }
  </style>
</head>
<body>

<?php if ($modoTeste): ?>
<div class="badge-teste">MODO TESTE — nenhum valor real será cobrado</div>
<?php endif; ?>

<div class="summary">
  <div class="summary-row"><span>Mensalidade</span><span><?= $refLabel ?></span></div>
  <div class="summary-row"><span>Turma</span><span><?= htmlspecialchars($mens['turma_nome'] ?? '—') ?></span></div>
  <?php if ($mens['status'] === 'atrasado'): ?>
  <div class="summary-row"><span>Valor original</span><span>R$ <?= number_format($valor,2,',','.') ?></span></div>
  <div class="summary-row"><span>Multa (5%)</span><span>R$ <?= number_format($multa,2,',','.') ?></span></div>
  <div class="summary-row"><span>Juros (0,5%/dia × <?= $dias ?>d)</span><span>R$ <?= number_format($juros,2,',','.') ?></span></div>
  <?php endif; ?>
  <div class="summary-row summary-total"><span>Total</span><span>R$ <?= number_format($total,2,',','.') ?></span></div>
</div>

<div id="paymentError" style="display:none;background:rgba(255,45,45,0.15);border:1px solid #ff5a5a;border-radius:8px;padding:14px;margin-bottom:16px;color:#ff5a5a;font-size:13px;line-height:1.5;word-break:break-word;"></div>

<div id="paymentForm">
  <div id="cardPaymentBrick_container"></div>
</div>

<div id="paymentSuccess">
  <h2>✅ Pagamento aprovado!</h2>
  <p>Mensalidade <?= $refLabel ?> quitada com sucesso.</p>
</div>

<div id="paymentPending">
  <p>⏳ Pagamento em análise. Aguarde a confirmação.</p>
</div>

<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
var MP_KEY     = '<?= $publicKey ?>';
var TOTAL      = <?= $total ?>;
var MENS_ID    = <?= $mensalidadeId ?>;
var EMAIL      = '<?= htmlspecialchars($aluno['email']) ?>';
var TOKEN_URL  = '<?= htmlspecialchars($urlToken) ?>';
var API_BASE   = '<?= $apiBase ?>';

var mp = new MercadoPago(MP_KEY, { locale: 'pt-BR' });
var bricksBuilder = mp.bricks();

bricksBuilder.create('cardPayment', 'cardPaymentBrick_container', {
  initialization: {
    amount: TOTAL,
    payer:  { email: EMAIL },
  },
  customization: {
    paymentMethods: { maxInstallments: 1 },
    visual: {
      style: {
        theme: 'dark',
        customVariables: {
          baseColor:       '#e5c200',
          baseColorFirstVariant: '#111111',
          baseColorSecondVariant: '#1a1a1a',
          textPrimaryColor: '#ffffff',
          textSecondaryColor: '#aaaaaa',
          inputBackgroundColor: '#1a1a1a',
          inputBorderColor: '#2a2a2a',
          formBackgroundColor: '#111111',
          borderRadiusFull: '8px',
          formPadding: '0px',
        },
      },
    },
  },
  callbacks: {
    onReady: function () {},
    onSubmit: function (formData) {
      return new Promise(function (resolve, reject) {
        var errDiv = document.getElementById('paymentError');
        errDiv.style.display = 'none';

        fetch(API_BASE + '/services/site/criar_pagamento_mobile.php', {
          method:  'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + TOKEN_URL,
          },
          body: JSON.stringify(Object.assign({}, formData, { mensalidade_id: MENS_ID })),
        })
        .then(function (r) {
          if (!r.ok) {
            return r.text().then(function(t) { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200)); });
          }
          return r.json();
        })
        .then(function (data) {
          if (data.success && data.status === 'approved') {
            document.getElementById('paymentForm').style.display    = 'none';
            document.getElementById('paymentSuccess').style.display = 'block';
            // Notifica o app React Native
            if (window.ReactNativeWebView) {
              window.ReactNativeWebView.postMessage(JSON.stringify({ success: true, status: 'approved', mensalidade_id: MENS_ID }));
            }
            resolve();
          } else if (data.success) {
            document.getElementById('paymentForm').style.display    = 'none';
            document.getElementById('paymentPending').style.display = 'block';
            if (window.ReactNativeWebView) {
              window.ReactNativeWebView.postMessage(JSON.stringify({ success: true, status: 'pending', mensalidade_id: MENS_ID }));
            }
            resolve();
          } else {
            errDiv.textContent   = '❌ ' + (data.message || 'Pagamento recusado. Verifique os dados do cartão.');
            errDiv.style.display = 'block';
            reject();
          }
        })
        .catch(function(err) {
          errDiv.textContent   = '❌ Erro ao processar: ' + err.message;
          errDiv.style.display = 'block';
          reject();
        });
      });
    },
    onError: function (error) {
      var errDiv = document.getElementById('paymentError');
      errDiv.textContent   = '❌ Erro no formulário: ' + JSON.stringify(error);
      errDiv.style.display = 'block';
    },
  },
});
</script>
</body>
</html>
