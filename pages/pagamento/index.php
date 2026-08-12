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
    SELECT m.id, m.referencia, m.tipo, m.descricao, m.valor, m.matricula_valor, m.proporcional_valor, m.desconto_aula_valor, m.vencimento, m.status,
           COALESCE(t.nome, '') AS turma_nome
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.id = ? AND m.aluno_id = ? AND m.status != 'pago'
");
$stMens->execute([$mensalidadeId, $aluno['id']]);
$mens = $stMens->fetch();

$stAuto = $pdo->prepare("SELECT auto_pagamento, cartao_final4 FROM alunos WHERE id = ?");
$stAuto->execute([$aluno['id']]);
$autoInfo        = $stAuto->fetch();
$autoPagamentoOn = !empty($autoInfo['auto_pagamento']);
$cartaoFinal4    = $autoInfo['cartao_final4'] ?? '';

if (!$mens) {
    header('Location: ' . BASE_URL . '/mensalidades');
    exit;
}

$valor             = (float) $mens['valor'];
$matriculaValor    = (float) ($mens['matricula_valor'] ?? 0);
$proporcionalValor = (float) ($mens['proporcional_valor'] ?? 0);
$descontoAulaValor = (float) ($mens['desconto_aula_valor'] ?? 0);
$valorMensalidade  = $valor - $matriculaValor - $proporcionalValor + $descontoAulaValor; // valor da mensalidade do mês atual, sem matrícula/proporcional/desconto de aula cancelada
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
/* Salvar cartão / cobrança automática */
.payCard__saveCard {
    display: flex; align-items: flex-start; gap: 10px; cursor: pointer;
    background: #1a1a1a; border: 1px solid #333; border-radius: 8px;
    padding: 12px 14px; margin-bottom: 16px; font-size: 13px; color: #ccc; line-height: 1.4;
}
.payCard__saveCard input { width: 16px; height: 16px; margin-top: 1px; accent-color: #e5c200; cursor: pointer; flex-shrink: 0; }
.payCard__autoNotice { color: #7ecf7e; font-size: 12px; margin-top: 8px; }
.payCard__autoNotice a { color: #e5c200; text-decoration: underline; }
.payCard__autoOn { background: rgba(126,207,126,.08); border: 1px solid rgba(126,207,126,.3);
                    border-radius: 8px; color: #7ecf7e; font-size: 12px; line-height: 1.5;
                    padding: 10px 12px; margin-bottom: 16px; }
.payCard__error { background: rgba(205,0,0,.12); border: 1px solid rgba(205,0,0,.5); border-radius: 8px;
                   color: #ff9d9d; font-size: 13px; line-height: 1.5; padding: 12px 14px; margin-bottom: 16px; }
.pixAguardando { color: #cccc00; font-size: 13px; margin-top: 14px; }
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
                <?php if ($matriculaValor > 0 || $proporcionalValor > 0 || $descontoAulaValor > 0): ?>
                <div class="payCard__summaryRow">
                    <span>Mensalidade</span>
                    <span>R$ <?= number_format($valorMensalidade, 2, ',', '.') ?></span>
                </div>
                <?php if ($proporcionalValor > 0): ?>
                <div class="payCard__summaryRow">
                    <span>Proporcional (mês anterior)</span>
                    <span>R$ <?= number_format($proporcionalValor, 2, ',', '.') ?></span>
                </div>
                <?php endif; ?>
                <?php if ($matriculaValor > 0): ?>
                <div class="payCard__summaryRow">
                    <span>Taxa de matrícula</span>
                    <span>R$ <?= number_format($matriculaValor, 2, ',', '.') ?></span>
                </div>
                <?php endif; ?>
                <?php if ($descontoAulaValor > 0): ?>
                <div class="payCard__summaryRow">
                    <span>Desconto (aula cancelada)</span>
                    <span>- R$ <?= number_format($descontoAulaValor, 2, ',', '.') ?></span>
                </div>
                <?php endif; ?>
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

            <div id="payError" class="payCard__error" role="alert" style="display:none;"></div>

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
                <?php
                // O checkbox NUNCA vem pré-marcado, nem pra quem já tem cobrança automática.
                // Antes ele vinha marcado nesse caso, e isso obrigava todo pagamento desses
                // alunos a passar pelo caminho de "salvar cartão" — que era justamente o que
                // quebrava a cobrança (ver criar_pagamento.php). Regravar um cartão que já
                // está salvo não traz benefício nenhum e só adiciona risco à cobrança.
                ?>
                <?php if (!$autoPagamentoOn): ?>
                <label class="payCard__saveCard">
                    <input type="checkbox" id="chkSalvarCartao">
                    <span>Quero deixar a mensalidade no automático todo mês (mostramos como ativar depois do pagamento)</span>
                </label>
                <?php else: ?>
                <p class="payCard__autoOn">
                    &#10003; Cobrança automática ativa<?php if ($cartaoFinal4): ?> no cartão final <?= htmlspecialchars($cartaoFinal4) ?><?php endif; ?>.
                    Este pagamento é avulso e não altera isso.
                </p>
                <?php endif; ?>
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
            <p id="pendingAviso" style="color:#cccc00;">
                &#9203; Pagamento em análise pelo Mercado Pago… esta tela atualiza sozinha.
            </p>
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
            <p class="pixAguardando" id="pixAguardando" style="display:none;">
                &#9203; Aguardando a confirmação do pagamento… esta tela atualiza sozinha.
            </p>
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

var PAGAMENTO_TIMEOUT_MS = 45000;

// ── Seletor de método ────────────────────────────────────────────────────────
function selectMethod(method) {
    document.getElementById('btnMethodCard').classList.toggle('is-active', method === 'card');
    document.getElementById('btnMethodPix').classList.toggle('is-active', method === 'pix');
    document.getElementById('areaCard').style.display  = method === 'card' ? '' : 'none';
    document.getElementById('areaPix').style.display   = method === 'pix'  ? '' : 'none';
}

/**
 * Mostra um painel e esconde todos os outros. Precisa esconder TODOS (não só o
 * formulário): quando o polling do PIX confirma o pagamento, a tela do QR Code ainda
 * está aberta e ficaria empilhada junto com a de sucesso.
 */
function mostrarTela(id) {
    ['paymentForm', 'paymentSuccess', 'paymentPending', 'paymentPix'].forEach(function (painel) {
        var el = document.getElementById(painel);
        if (el) el.style.display = (painel === id) ? '' : 'none';
    });
}

/** Mensagem de erro visível — antes o aluno só via a barra girando. */
function mostrarErroPagamento(texto) {
    var box = document.getElementById('payError');
    if (!box) return;
    box.textContent = texto;
    box.style.display = '';
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function limparErroPagamento() {
    var box = document.getElementById('payError');
    if (box) box.style.display = 'none';
}

/**
 * POST do pagamento com timeout e parse defensivo.
 *
 * Dois motivos de travamento que isso resolve:
 *  - requisição que nunca responde (a promise ficava pendente pra sempre);
 *  - resposta que não é JSON (um warning do PHP junto do corpo fazia r.json() estourar).
 */
function postPagamento(url, payload) {
    limparErroPagamento();

    var controller = ('AbortController' in window) ? new AbortController() : null;
    var estourou   = false;
    var timer = setTimeout(function () {
        estourou = true;
        if (controller) controller.abort();
    }, PAGAMENTO_TIMEOUT_MS);

    var opts = {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body:        JSON.stringify(payload)
    };
    if (controller) opts.signal = controller.signal;

    return fetch(url, opts)
        .then(function (r) { return r.text(); })
        .then(function (texto) {
            clearTimeout(timer);
            try {
                return JSON.parse(texto);
            } catch (e) {
                console.error('Resposta não-JSON do servidor:', texto.slice(0, 500));
                throw new Error('resposta_invalida');
            }
        })
        .catch(function (err) {
            clearTimeout(timer);
            if (estourou) { var e = new Error('timeout'); e.timeout = true; throw e; }
            throw err;
        });
}

/**
 * Job de verificação: pergunta ao servidor (que reconsulta o Mercado Pago) se a cobrança
 * foi de fato aprovada. Roda depois de toda tentativa de pagamento — assim uma resposta
 * perdida ou um webhook que não chegou não deixam o aluno no escuro.
 */
function verificarPagamento(opcoes) {
    opcoes = opcoes || {};

    var body = new URLSearchParams({ contexto: 'mensalidade', referencia_id: MENSALIDADE_ID });

    return fetch(BASE_URL + '/services/site/verificar_pagamento.php', {
        method:      'POST',
        headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body:        body.toString()
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success && data.pago) {
            mostrarTela('paymentSuccess');
            if (opcoes.aoConfirmar) opcoes.aoConfirmar(data);
            return true;
        }
        // Recusa definitiva vale mesmo no modo silencioso: o polling roda em silêncio
        // justamente esperando o desfecho, e o desfecho pode ser "recusado".
        if (data.recusado && opcoes.aoRecusar) opcoes.aoRecusar(data);
        if (!opcoes.silencioso && opcoes.aoNegar) opcoes.aoNegar(data);
        return false;
    })
    .catch(function () {
        if (!opcoes.silencioso && opcoes.aoNegar) opcoes.aoNegar(null);
        return false;
    });
}

/**
 * Acompanha um pagamento de cartão que voltou "em análise" até o Mercado Pago decidir.
 *
 * O antifraude do MP responde `in_process` na hora e só conclui segundos depois. Enquanto
 * isso não existia, a tela mostrava "em análise" e parava ali: quando a recusa vinha, o
 * aluno continuava olhando uma mensagem dizendo que receberia uma confirmação em breve.
 */
function acompanharAnalise() {
    var aviso  = document.getElementById('pendingAviso');
    var tentativas = 0;
    var MAX = 20; // ~2 min a cada 6s — o antifraude decide em segundos

    var timer = setInterval(function () {
        tentativas++;

        if (tentativas > MAX) {
            clearInterval(timer);
            if (aviso) {
                aviso.innerHTML = '&#9203; O Mercado Pago ainda está analisando. Assim que houver '
                                + 'resposta sua mensalidade é atualizada sozinha — não é preciso pagar de novo.';
            }
            return;
        }

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

/** Troca a tela de "em análise" pela recusa real, com o motivo e o que fazer. */
function mostrarRecusa(data) {
    var motivo = (data && data.motivo) ? data.motivo : 'o pagamento não foi autorizado';
    var acao   = (data && data.acao)   ? data.acao   : 'Tente outro cartão ou pague via PIX.';

    var box = document.getElementById('paymentPending');
    if (!box) return;

    box.innerHTML =
        '<div style="font-size:40px;margin-bottom:12px;">&#10060;</div>'
      + '<h2 style="font-size:20px;margin-bottom:8px;color:#e57373;">Pagamento não aprovado</h2>'
      + '<p style="color:#ccc;margin-bottom:8px;">Motivo: ' + motivo + '.</p>'
      + '<p style="color:#aaa;font-size:13px;margin-bottom:20px;">' + acao + '</p>'
      + '<button type="button" class="btn btn--primary" onclick="location.reload();">Tentar de novo</button>';
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
                    // O checkbox só existe pra quem ainda não tem cobrança automática.
                    var chk = document.getElementById('chkSalvarCartao');
                    var salvarCartao = !!(chk && chk.checked);

                    // Sem timeout, uma requisição que nunca responde deixa a promise pendente
                    // e a barra do Brick girando indefinidamente — era o que o aluno via.
                    postPagamento(
                        BASE_URL + '/services/site/criar_pagamento.php',
                        Object.assign({}, formData, { mensalidade_id: MENSALIDADE_ID, salvar_cartao: salvarCartao })
                    )
                    .then(function (data) {
                        if (data.success && data.status === 'approved') {
                            // Salvar cartão é feito em /meuperfil, num fluxo separado que não
                            // interfere na cobrança — misturar os dois já quebrou pagamento.
                            if (data.oferecer_auto) {
                                var p = document.createElement('p');
                                p.className = 'payCard__autoNotice';
                                p.innerHTML = 'Para deixar a mensalidade no automático todo mês, '
                                            + '<a href="' + BASE_URL + '/meuperfil#pagamento-automatico">ative em Meu Perfil</a>.';
                                document.getElementById('paymentSuccess').appendChild(p);
                            }
                            mostrarTela('paymentSuccess');
                            resolve();
                            return;
                        }

                        if (data.success) {
                            // pending/in_process: o MP ainda não decidiu. Uma consulta só não
                            // basta — o antifraude costuma recusar alguns segundos DEPOIS da
                            // resposta do checkout, e sem acompanhar até o fim a tela ficava
                            // parada em "em análise" pra sempre, esperando uma confirmação
                            // que nunca chegaria. Agora acompanha até aprovar ou recusar.
                            mostrarTela('paymentPending');
                            acompanharAnalise();
                            resolve();
                            return;
                        }

                        // Recusa conhecida: mostra o motivo e devolve o formulário pro aluno.
                        mostrarErroPagamento(data.message || 'Pagamento não autorizado. Confira os dados ou tente outro cartão.');
                        reject(new Error(data.message || 'pagamento_recusado'));
                    })
                    .catch(function (err) {
                        // Rede caiu, timeout estourou ou a resposta não era JSON. O dinheiro
                        // pode ter saído mesmo assim, então pergunta ao servidor antes de
                        // dizer qualquer coisa ao aluno.
                        verificarPagamento({
                            aoConfirmar: function () { resolve(); },
                            aoNegar: function () {
                                mostrarErroPagamento(
                                    err && err.timeout
                                        ? 'A operação demorou demais e foi interrompida. Confira suas mensalidades antes de tentar de novo — se a cobrança tiver passado, ela já vai aparecer como paga.'
                                        : 'Não conseguimos confirmar o pagamento agora. Confira suas mensalidades antes de tentar de novo.'
                                );
                                reject(err || new Error('falha_pagamento'));
                            }
                        });
                    });
                });
            },
            onError: function (error) {
                console.error('MP Brick error:', error);
                // O Brick também erra por validação de campo — sem mostrar nada, o aluno
                // fica sem saber o que aconteceu.
                var msg = (error && error.message) ? error.message : '';
                mostrarErroPagamento(msg
                    ? 'Não foi possível enviar o pagamento: ' + msg
                    : 'Confira os dados do cartão e tente novamente.');
            },
        },
    });
}());

// ── PIX ──────────────────────────────────────────────────────────────────────
document.getElementById('btnGerarPix').addEventListener('click', function () {
    var btn = this;
    btn.disabled    = true;
    btn.textContent = 'Gerando...';

    postPagamento(BASE_URL + '/services/site/criar_pagamento.php', {
        mensalidade_id:    MENSALIDADE_ID,
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

/**
 * Enquanto o QR está na tela, pergunta ao servidor de tempos em tempos se o PIX caiu —
 * assim a confirmação aparece sozinha pro aluno, sem depender do webhook e sem precisar
 * recarregar a página. Para sozinho depois de ~10 minutos pra não ficar batendo à toa.
 */
function iniciarPollingPix() {
    var aviso = document.getElementById('pixAguardando');
    if (aviso) aviso.style.display = '';

    var tentativas = 0;
    var MAX = 100; // ~10 min a cada 6s

    var timer = setInterval(function () {
        tentativas++;

        if (tentativas > MAX) {
            clearInterval(timer);
            if (aviso) aviso.textContent = 'Ainda não identificamos o pagamento. Assim que cair, sua mensalidade é atualizada automaticamente.';
            return;
        }

        verificarPagamento({ silencioso: true }).then(function (pago) {
            if (pago) clearInterval(timer);
        });
    }, 6000);
}

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
