<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/mercadopago.php';
require_once ROOT . '/config/uniformes.php';

// Módulo escondido do aluno (interruptor em config/uniformes.php).
if (!UNIFORMES_VISIVEL_ALUNO) {
    header('Location: ' . BASE_URL . '/areadoaluno');
    exit;
}

$aluno    = $_SESSION['aluno'];
$pedidoId = (int) ($_GET['pedido_id'] ?? 0);

if ($pedidoId <= 0) {
    header('Location: ' . BASE_URL . '/pedidouniforme');
    exit;
}

$pdo = getDbConnection();

uniformeExpirarReservas($pdo);

// Os segundos restantes vêm do próprio MySQL (TIMESTAMPDIFF sobre NOW()) — o fuso do PHP
// pode não bater com o do banco, e aí o contador mostraria horas a mais ou já zerado.
$stPedido = $pdo->prepare("
    SELECT p.id, p.genero, p.modelo, p.nome_camisa, p.numero, p.tamanho_camisa, p.tamanho_shorts, p.valor,
           p.status_pagamento, p.reserva_expira_em,
           TIMESTAMPDIFF(SECOND, NOW(), p.reserva_expira_em) AS segundos_restantes,
           COALESCE(t.nome, '') AS turma_nome
    FROM pedidos_uniforme p
    LEFT JOIN turmas t ON t.id = p.turma_id
    WHERE p.id = ? AND p.aluno_id = ?
");
$stPedido->execute([$pedidoId, (int) $aluno['id']]);
$pedido = $stPedido->fetch();

if (!$pedido) {
    header('Location: ' . BASE_URL . '/pedidouniforme');
    exit;
}

$jaPago   = $pedido['status_pagamento'] === 'pago';
$expirado = in_array($pedido['status_pagamento'], ['expirado', 'cancelado'], true);

$total       = (float) $pedido['valor'];
$generoLabel = $pedido['genero'] === 'feminino' ? 'Feminino' : 'Masculino';
$modeloLabel = UNIFORME_MODELO_LABEL[$pedido['modelo']] ?? $pedido['modelo'];

$publicKey = mpPublicKey($pdo);
$modoTeste = mpModoTeste($pdo);

// Segundos restantes da reserva — o contador na tela usa isso.
$segundosRestantes = 0;
if (!$jaPago && !$expirado && $pedido['segundos_restantes'] !== null) {
    $segundosRestantes = max(0, (int) $pedido['segundos_restantes']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Pagamento do Uniforme</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="uniformPay">
    <div class="uniformPay__card">

        <?php if ($jaPago): ?>

            <div class="uniformPay__state">
                <div class="uniformPay__stateIcon">&#9989;</div>
                <h1>Pedido confirmado!</h1>
                <p>Seu uniforme <strong><?= htmlspecialchars($pedido['nome_camisa']) ?> #<?= (int) $pedido['numero'] ?></strong> já está pago e entrou na fila de produção.</p>
                <a class="uniformPay__btn" href="<?= BASE_URL ?>/areadoaluno#meus-uniformes">Acompanhar meu pedido</a>
            </div>

        <?php elseif ($expirado): ?>

            <div class="uniformPay__state">
                <div class="uniformPay__stateIcon">&#8987;</div>
                <h1>Reserva expirada</h1>
                <p>O número <strong>#<?= (int) $pedido['numero'] ?></strong> ficou reservado por <?= UNIFORME_RESERVA_MINUTOS ?> minutos e o pagamento não foi concluído a tempo, então ele voltou a ficar disponível.</p>
                <a class="uniformPay__btn" href="<?= BASE_URL ?>/pedidouniforme">Refazer o pedido</a>
            </div>

        <?php else: ?>

            <a href="<?= BASE_URL ?>/pedidouniforme" class="uniformPay__back">&#8592; Voltar para o pedido</a>

            <div id="paymentForm">
                <?php if ($modoTeste): ?>
                <div class="uniformPay__testBadge">MODO DE TESTE — nenhum valor real será cobrado</div>
                <?php endif; ?>

                <h1 class="uniformPay__title">Pagar uniforme</h1>
                <p class="uniformPay__sub"><?= $generoLabel ?> — <?= htmlspecialchars($modeloLabel) ?></p>

                <div class="uniformPay__summary">
                    <div class="uniformPay__row"><span>Nome na camiseta</span><span><?= htmlspecialchars($pedido['nome_camisa']) ?></span></div>
                    <div class="uniformPay__row"><span>Número</span><span>#<?= (int) $pedido['numero'] ?></span></div>
                    <div class="uniformPay__row"><span>Tam. camisa</span><span><?= htmlspecialchars($pedido['tamanho_camisa']) ?></span></div>
                    <div class="uniformPay__row"><span>Tam. <?= htmlspecialchars(mb_strtolower(explode(' ', uniformeLabelPeca($pedido['genero'], 'shorts'))[0])) ?></span><span><?= htmlspecialchars($pedido['tamanho_shorts']) ?></span></div>
                    <?php if (!empty($pedido['turma_nome'])): ?>
                    <div class="uniformPay__row"><span>Turma</span><span><?= htmlspecialchars($pedido['turma_nome']) ?></span></div>
                    <?php endif; ?>
                    <div class="uniformPay__row"><span>Conjunto</span><span>Camisa + shorts + meião</span></div>
                    <div class="uniformPay__row uniformPay__row--total">
                        <span>Total à vista</span><span>R$ <?= number_format($total, 2, ',', '.') ?></span>
                    </div>
                </div>

                <?php if ($segundosRestantes > 0): ?>
                <p class="uniformPay__timer" id="uniformTimer" data-seconds="<?= $segundosRestantes ?>">
                    Número <strong>#<?= (int) $pedido['numero'] ?></strong> reservado por mais <strong id="uniformTimerValue">--:--</strong>
                </p>
                <?php endif; ?>

                <div id="payError" class="uniformPay__error" role="alert" style="display:none;"></div>

                <?php if (mpCheckoutModo($pdo) === 'pro'): ?>
                <!--
                    Checkout Pro: cartão e PIX ficam na página do Mercado Pago. A reserva do
                    número continua valendo os 30 minutos enquanto o aluno paga lá.
                -->
                <div class="uniformPay__proBox" style="text-align:center;padding:8px 0;">
                    <p style="color:#bbb;font-size:14px;line-height:1.6;margin-bottom:16px;">
                        Você será levado para o <strong style="color:#fff;">Mercado Pago</strong> para pagar
                        com <strong style="color:#fff;">cartão ou PIX</strong>, e volta para cá em seguida.
                    </p>
                    <button type="button" id="btnCheckoutProUniforme" class="uniformPay__btn">Ir para o pagamento</button>
                </div>
                <?php else: ?>
                <div class="uniformPay__methods">
                    <button type="button" class="uniformPay__method is-active" id="btnMethodCard" onclick="selectMethod('card')">
                        <i class="icon-creditcard"></i>Crédito / Débito
                    </button>
                    <button type="button" class="uniformPay__method" id="btnMethodPix" onclick="selectMethod('pix')">
                        <i>&#9635;</i>PIX
                    </button>
                </div>

                <div id="areaCard">
                    <p class="uniformPay__installmentsHint">
                        Parcele em até <?= UNIFORME_PARCELAS_MAX ?>x no cartão de crédito. Se o parcelamento escolhido tiver juros, o valor total já aparece somado na opção — e é cobrado do cliente na fatura do cartão.
                    </p>
                    <div id="cardPaymentBrick_container"></div>
                </div>

                <div id="areaPix" style="display:none;">
                    <p class="uniformPay__pixHint">
                        Clique abaixo para gerar o QR Code PIX de
                        <strong>R$ <?= number_format($total, 2, ',', '.') ?></strong>.
                        O pedido é confirmado automaticamente assim que o pagamento cair.
                    </p>
                    <button id="btnGerarPix" class="uniformPay__btn">Gerar QR Code PIX</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Aprovado -->
            <div id="paymentSuccess" class="uniformPay__state" style="display:none;">
                <div class="uniformPay__stateIcon">&#9989;</div>
                <h1>Pagamento aprovado!</h1>
                <p>Seu pedido de uniforme foi confirmado e já entrou na nossa fila de produção.</p>
                <a class="uniformPay__btn" href="<?= BASE_URL ?>/areadoaluno#meus-uniformes">Acompanhar meu pedido</a>
            </div>

            <!-- Em análise -->
            <div id="paymentPending" class="uniformPay__state" style="display:none;">
                <div class="uniformPay__stateIcon">&#9203;</div>
                <h1>Pagamento em análise</h1>
                <p>Assim que for aprovado, seu pedido é confirmado automaticamente.</p>
                <a class="uniformPay__btn" href="<?= BASE_URL ?>/areadoaluno#meus-uniformes">Ir para a área do aluno</a>
            </div>

            <!-- PIX gerado -->
            <div id="paymentPix" class="uniformPay__state" style="display:none;">
                <h1>Pague com PIX</h1>
                <p>Escaneie o QR Code ou copie o código abaixo. O pedido confirma sozinho depois do pagamento.</p>
                <img id="pixQrImg" src="" alt="QR Code PIX" class="uniformPay__qr">
                <textarea id="pixCopiaCola" class="uniformPay__pixCode" readonly rows="4"></textarea>
                <button class="uniformPay__btn uniformPay__btn--pix" onclick="copiarPix()">Copiar código PIX</button>
                <p class="uniformPay__aguardando" id="pixAguardando" style="display:none;">
                    &#9203; Aguardando a confirmação do pagamento… esta tela atualiza sozinha.
                </p>
                <a class="uniformPay__link" href="<?= BASE_URL ?>/areadoaluno#meus-uniformes">Ir para a área do aluno</a>
            </div>

        <?php endif; ?>
    </div>
</main>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>

<?php if (!$jaPago && !$expirado): ?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
var BASE_URL      = "<?= BASE_URL ?>";
var MP_PUBLIC_KEY = "<?= $publicKey ?>";
var PEDIDO_ID     = <?= $pedidoId ?>;
var TOTAL_AMOUNT  = <?= $total ?>;
var ALUNO_EMAIL   = "<?= htmlspecialchars($aluno['email'] ?? '') ?>";
var PARCELAS_MAX  = <?= UNIFORME_PARCELAS_MAX ?>;

function selectMethod(method) {
    document.getElementById('btnMethodCard').classList.toggle('is-active', method === 'card');
    document.getElementById('btnMethodPix').classList.toggle('is-active', method === 'pix');
    document.getElementById('areaCard').style.display = method === 'card' ? '' : 'none';
    document.getElementById('areaPix').style.display  = method === 'pix'  ? '' : 'none';
}

var PAGAMENTO_TIMEOUT_MS = 45000;

/** Mostra um painel e esconde os outros (o QR do PIX não pode sobrar na tela de sucesso). */
function mostrarTela(id) {
    ['paymentForm', 'paymentSuccess', 'paymentPending', 'paymentPix'].forEach(function (painel) {
        var el = document.getElementById(painel);
        if (el) el.style.display = (painel === id) ? '' : 'none';
    });
}

function mostrarErroPagamento(texto) {
    var box = document.getElementById('payError');
    if (!box) return;
    box.textContent = texto;
    box.style.display = '';
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/** POST com timeout e parse defensivo — ver comentário em pages/pagamento. */
function postPagamento(url, payload) {
    var box = document.getElementById('payError');
    if (box) box.style.display = 'none';

    var controller = ('AbortController' in window) ? new AbortController() : null;
    var estourou   = false;
    var timer = setTimeout(function () { estourou = true; if (controller) controller.abort(); }, PAGAMENTO_TIMEOUT_MS);

    var opts = {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    };
    if (controller) opts.signal = controller.signal;

    return fetch(url, opts)
        .then(function (r) { return r.text(); })
        .then(function (texto) {
            clearTimeout(timer);
            try { return JSON.parse(texto); }
            catch (e) { console.error('Resposta não-JSON:', texto.slice(0, 500)); throw new Error('resposta_invalida'); }
        })
        .catch(function (err) {
            clearTimeout(timer);
            if (estourou) { var e = new Error('timeout'); e.timeout = true; throw e; }
            throw err;
        });
}

/**
 * Acompanha um pagamento que voltou "em análise" até o Mercado Pago decidir, em vez de
 * consultar uma vez só e deixar a tela parada esperando uma confirmação que pode nunca vir.
 */
function acompanharAnalise() {
    var tentativas = 0;
    var MAX = 20; // ~2 min a cada 6s

    var timer = setInterval(function () {
        tentativas++;
        if (tentativas > MAX) { clearInterval(timer); return; }

        verificarPagamento({
            silencioso: true,
            aoRecusar: function (data) {
                clearInterval(timer);
                mostrarRecusa(data);
            }
        }).then(function (pago) {
            if (pago) clearInterval(timer);
        });
    }, 6000);
}

/** Troca a tela de "em análise" pela recusa real, com motivo e orientação. */
function mostrarRecusa(data) {
    var motivo = (data && data.motivo) ? data.motivo : 'o pagamento não foi autorizado';
    var acao   = (data && data.acao)   ? data.acao   : 'Tente outro cartão ou pague via PIX.';

    var box = document.getElementById('paymentPending');
    if (!box) return;

    box.innerHTML =
        '<div class="uniformPay__stateIcon">&#10060;</div>'
      + '<h1>Pagamento não aprovado</h1>'
      + '<p>Motivo: ' + motivo + '.</p>'
      + '<p>' + acao + '</p>'
      + '<button type="button" class="uniformPay__btn" onclick="location.reload();">Tentar de novo</button>';
}

/** Job de verificação do pedido de uniforme. */
function verificarPagamento(opcoes) {
    opcoes = opcoes || {};
    var body = new URLSearchParams({ contexto: 'uniforme', referencia_id: PEDIDO_ID });

    return fetch(BASE_URL + '/services/site/verificar_pagamento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: body.toString()
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success && data.pago) {
            mostrarTela('paymentSuccess');
            if (opcoes.aoConfirmar) opcoes.aoConfirmar(data);
            return true;
        }
        // Recusa definitiva vale mesmo no modo silencioso — ver acompanharAnalise().
        if (data.recusado && opcoes.aoRecusar) opcoes.aoRecusar(data);
        if (!opcoes.silencioso && opcoes.aoNegar) opcoes.aoNegar(data);
        return false;
    })
    .catch(function () {
        if (!opcoes.silencioso && opcoes.aoNegar) opcoes.aoNegar(null);
        return false;
    });
}

// ── Contador da reserva ─────────────────────────────────────────────────────────
(function () {
    var box = document.getElementById('uniformTimer');
    if (!box) return;

    var restante = parseInt(box.getAttribute('data-seconds'), 10) || 0;
    var valor    = document.getElementById('uniformTimerValue');

    function tick() {
        if (restante <= 0) {
            box.classList.add('is-expired');
            box.innerHTML = 'A reserva do número expirou. <a href="' + BASE_URL + '/pedidouniforme">Refazer o pedido</a>.';
            return;
        }
        var min = Math.floor(restante / 60);
        var seg = restante % 60;
        valor.textContent = min + ':' + (seg < 10 ? '0' : '') + seg;
        restante--;
        setTimeout(tick, 1000);
    }
    tick();
}());

// ── Checkout Pro ─────────────────────────────────────────────────────────────
// Pede a preferência ao servidor e manda o aluno pro Mercado Pago. A confirmação do
// pedido não depende dele voltar: o webhook identifica pelo external_reference.
(function () {
    var btn = document.getElementById('btnCheckoutProUniforme');
    if (!btn) return;   // modo transparente

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.textContent = 'Preparando...';

        postPagamento(BASE_URL + '/services/site/criar_pagamento_uniforme.php', { pedido_id: PEDIDO_ID })
        .then(function (d) {
            if (d.success && d.status === 'redirect' && d.init_point) {
                window.location.href = d.init_point;
                return;
            }
            if (d.expirado) {
                window.location.href = BASE_URL + '/pagamentouniforme?pedido_id=' + PEDIDO_ID;
                return;
            }
            mostrarErroPagamento(d.message || 'Não foi possível preparar o pagamento.');
            btn.disabled = false;
            btn.textContent = 'Ir para o pagamento';
        })
        .catch(function () {
            mostrarErroPagamento('Erro de conexão. Tente de novo.');
            btn.disabled = false;
            btn.textContent = 'Ir para o pagamento';
        });
    });
}());

// ── Brick de cartão ─────────────────────────────────────────────────────────────
(function () {
    // No Checkout Pro esse container não existe — sem a guarda o SDK quebra o script todo.
    if (!document.getElementById('cardPaymentBrick_container')) return;

var mp = new MercadoPago(MP_PUBLIC_KEY, { locale: 'pt-BR' });

    mp.bricks().create('cardPayment', 'cardPaymentBrick_container', {
        initialization: {
            amount: TOTAL_AMOUNT,
            payer:  { email: ALUNO_EMAIL }
        },
        customization: {
            // maxInstallments só limita o teto do seletor do Brick — ele mesmo consulta o MP
            // pra saber quantas parcelas o cartão/valor realmente permite e quais têm juros.
            paymentMethods: { maxInstallments: PARCELAS_MAX }
        },
        callbacks: {
            onReady: function () {},
            onSubmit: function (formData) {
                return new Promise(function (resolve, reject) {
                    postPagamento(
                        BASE_URL + '/services/site/criar_pagamento_uniforme.php',
                        Object.assign({}, formData, { pedido_id: PEDIDO_ID })
                    )
                    .then(function (data) {
                        if (data.success && data.status === 'approved') {
                            mostrarTela('paymentSuccess');
                            resolve();
                            return;
                        }
                        if (data.success) {
                            // Uma consulta só não basta: o antifraude do MP responde
                            // in_process na hora e pode recusar segundos depois. Aqui isso
                            // é ainda mais grave que na mensalidade — a reserva do número
                            // expira em 30 min, então deixar a tela parada em "em análise"
                            // faz o aluno perder o número esperando por nada.
                            mostrarTela('paymentPending');
                            acompanharAnalise();
                            resolve();
                            return;
                        }
                        if (data.expirado) {
                            window.location.href = BASE_URL + '/pagamentouniforme?pedido_id=' + PEDIDO_ID;
                            reject(new Error('reserva_expirada'));
                            return;
                        }
                        mostrarErroPagamento(data.message || 'Pagamento não autorizado. Confira os dados ou tente outro cartão.');
                        reject(new Error(data.message || 'pagamento_recusado'));
                    })
                    .catch(function (err) {
                        // Pode ter cobrado mesmo sem resposta — confirma antes de falar algo.
                        verificarPagamento({
                            aoConfirmar: function () { resolve(); },
                            aoNegar: function () {
                                mostrarErroPagamento(err && err.timeout
                                    ? 'A operação demorou demais e foi interrompida. Confira seus pedidos antes de tentar de novo.'
                                    : 'Não conseguimos confirmar o pagamento agora. Confira seus pedidos antes de tentar de novo.');
                                reject(err || new Error('falha_pagamento'));
                            }
                        });
                    });
                });
            },
            onError: function (error) {
                console.error('MP Brick error:', error);
                var msg = (error && error.message) ? error.message : '';
                mostrarErroPagamento(msg
                    ? 'Não foi possível enviar o pagamento: ' + msg
                    : 'Confira os dados do cartão e tente novamente.');
            }
        }
    });
}());

// ── PIX ─────────────────────────────────────────────────────────────────────────
var __btnPixUni = document.getElementById('btnGerarPix');
if (__btnPixUni) __btnPixUni.addEventListener('click', function () {
    var btn = this;
    btn.disabled    = true;
    btn.textContent = 'Gerando...';

    postPagamento(BASE_URL + '/services/site/criar_pagamento_uniforme.php', {
        pedido_id:         PEDIDO_ID,
        payment_method_id: 'pix',
        payer:             { email: ALUNO_EMAIL }
    })
    .then(function (data) {
        if (data.success && data.status === 'pix_pending') {
            if (data.qr_code_base64) {
                document.getElementById('pixQrImg').src = 'data:image/png;base64,' + data.qr_code_base64;
            }
            document.getElementById('pixCopiaCola').value = data.qr_code || '';
            mostrarTela('paymentPix');
            iniciarPollingPix();
            return;
        }
        if (data.success && data.status === 'approved') {
            mostrarTela('paymentSuccess');
            return;
        }
        btn.disabled    = false;
        btn.textContent = 'Tentar novamente';
        mostrarErroPagamento(data.message || 'Não foi possível gerar o PIX. Tente novamente.');
    })
    .catch(function (err) {
        btn.disabled    = false;
        btn.textContent = 'Tentar novamente';
        mostrarErroPagamento(err && err.timeout
            ? 'A geração do PIX demorou demais. Tente novamente.'
            : 'Erro de conexão ao gerar o PIX. Tente novamente.');
    });
});

/** Confirma o PIX sozinho na tela, sem depender só do webhook. */
function iniciarPollingPix() {
    var aviso = document.getElementById('pixAguardando');
    if (aviso) aviso.style.display = '';

    var tentativas = 0;
    var timer = setInterval(function () {
        tentativas++;
        if (tentativas > 100) { // ~10 min a cada 6s
            clearInterval(timer);
            if (aviso) aviso.textContent = 'Ainda não identificamos o pagamento. Assim que cair, seu pedido é confirmado automaticamente.';
            return;
        }
        verificarPagamento({ silencioso: true }).then(function (pago) {
            if (pago) clearInterval(timer);
        });
    }, 6000);
}

function copiarPix() {
    var texto = document.getElementById('pixCopiaCola').value;
    var btn   = document.querySelector('.uniformPay__btn--pix');
    var done  = function () {
        btn.textContent = 'Código copiado!';
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
<?php endif; ?>

</body>
</html>
