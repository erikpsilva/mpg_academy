<?php
if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__, 2));
    require_once ROOT . '/config/app.php';
}
require_once ROOT . '/config/database.php';

$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
$erro  = '';

if (!$token) {
    $erro = 'Link inválido.';
    $contrato = null;
} else {
    $pdo = getDbConnection();
    $st  = $pdo->prepare("
        SELECT pc.*, p.nome AS prof_nome, p.sobrenome AS prof_sobrenome,
               p.cpf AS prof_cpf, p.dia_pagamento
        FROM professor_contratos pc
        JOIN professores p ON p.id = pc.professor_id
        WHERE pc.token = ?
        LIMIT 1
    ");
    $st->execute([$token]);
    $contrato = $st->fetch(PDO::FETCH_ASSOC);
    if (!$contrato) $erro = 'Link inválido ou expirado.';
}

$jaSigned     = $contrato && !empty($contrato['assinado_em']);
$profNome     = $contrato ? trim($contrato['prof_nome'] . ' ' . $contrato['prof_sobrenome']) : '';
$profCpfRaw   = $contrato['prof_cpf'] ?? '';
$profCpfFmt   = '';
if ($profCpfRaw) {
    $d = preg_replace('/\D/', '', $profCpfRaw);
    $profCpfFmt = strlen($d) === 11
        ? substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2)
        : $profCpfRaw;
}
$diaPgto        = (int)($contrato['dia_pagamento'] ?? 0);
$assinadoEmpresa  = $contrato && !empty($contrato['assinado_empresa_em']);
$empresaNome      = $contrato['assinado_empresa_nome'] ?? '';
$temConteudoHtml  = $contrato && !empty($contrato['conteudo_html']);
$logoUrl   = appBaseUrl() . '/images/logo.png';
$sigUrl    = appBaseUrl() . '/contrato?token=' . $token;

function fmtHora2($dt) {
    if (!$dt) return '—';
    return (new DateTime($dt))->format('d/m/Y \à\s H:i');
}
function maskCpf2($cpf) {
    if (!$cpf) return '—';
    $d = preg_replace('/\D/', '', $cpf);
    return strlen($d) === 11 ? substr($d,0,3) . '.***.***-' . substr($d,9,2) : $cpf;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contrato de Prestação de Serviços – MPG Academy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #f4f5f7; font-family: 'Inter', sans-serif; color: #1a1a2e; min-height: 100vh; }

.page-header {
    background: #0b0d0f;
    border-bottom: 3px solid #ffd500;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.page-header img { height: 40px; }
.page-header__title { color: #ffd500; font-size: 13px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }

.container { max-width: 800px; margin: 0 auto; padding: 32px 16px 64px; }

.status-bar {
    border-radius: 12px; padding: 16px 20px; margin-bottom: 28px;
    display: flex; align-items: center; gap: 12px;
    font-weight: 600; font-size: 14px;
}
.status-bar--assinado  { background: #d1fae5; border: 1.5px solid #10b981; color: #065f46; }
.status-bar--pendente  { background: #fef3c7; border: 1.5px solid #f59e0b; color: #78350f; }
.status-bar--erro      { background: #fee2e2; border: 1.5px solid #ef4444; color: #7f1d1d; }
.status-bar__icon { font-size: 22px; flex-shrink: 0; }
.status-bar__info { flex: 1; }
.status-bar__label { font-size: 12px; font-weight: 500; opacity: .8; }

.card {
    background: #fff; border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 24px;
}
.card__header {
    padding: 18px 24px 14px;
    border-bottom: 1px solid #f0f0f0;
    display: flex; align-items: center; gap: 12px;
}
.card__header-icon { font-size: 22px; }
.card__header-title { font-size: 17px; font-weight: 700; }
.card__header-sub   { font-size: 12px; color: #666; margin-top: 2px; }
.card__body { padding: 20px 24px; }

/* Contrato HTML */
.c-doc { font-size: 13.5px; line-height: 1.85; color: #333; }
.c-aviso {
    background: #fffbeb; border: 1.5px solid #fcd34d; border-radius: 8px;
    padding: 14px 18px; margin-bottom: 24px; font-size: 13px;
    color: #78350f; font-style: italic;
}
.c-secao { margin-bottom: 22px; }
.c-secaoTitulo {
    font-size: 14px; font-weight: 700; color: #0b0d0f;
    border-bottom: 2px solid #ffd500; padding-bottom: 5px;
    margin-bottom: 10px; text-transform: uppercase; letter-spacing: .3px;
}
.c-doc p { margin-bottom: 8px; }
.c-bullet { padding-left: 16px; }
.c-rodape { margin-top: 32px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #555; font-size: 13px; }

.sig-box {
    border: 2px dashed #d1d5db; border-radius: 12px;
    min-height: 80px; padding: 12px 18px;
    display: flex; align-items: center; justify-content: center;
    margin-top: 10px;
}
.sig-preview { font-family: 'Great Vibes', cursive; font-size: 32px; color: #1a1a2e; }
.sig-placeholder { color: #aaa; font-size: 14px; }

.field { margin-bottom: 18px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 7px; text-transform: uppercase; }
.field input {
    width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
    font-family: 'Inter', sans-serif; font-size: 15px; height: 48px;
    padding: 0 14px; color: #1a1a2e; transition: border-color .2s;
}
.field input:focus { border-color: #ffd500; box-shadow: 0 0 0 3px rgba(255,213,0,.15); outline: none; }
.field input[readonly] { background: #f9fafb; color: #374151; cursor: default; }

.pgto-info {
    background: #fffbeb; border: 1.5px solid #fcd34d;
    border-radius: 10px; padding: 14px 18px;
    display: flex; align-items: center; gap: 12px;
    font-size: 14px; color: #78350f;
}
.pgto-info strong { color: #92400e; }

.btn-submit {
    background: #ffd500; border: none; border-radius: 8px;
    color: #1a1a2e; cursor: pointer; font-family: 'Inter', sans-serif;
    font-size: 15px; font-weight: 700; height: 50px; padding: 0 32px; width: 100%;
    transition: background .2s;
}
.btn-submit:hover { background: #f0c800; }
.btn-submit:disabled { background: #e5e7eb; color: #9ca3af; cursor: not-allowed; }

.form-msg { font-size: 14px; margin-top: 12px; font-weight: 600; }

.signed-block {
    background: #f0fdf4; border: 1.5px solid #10b981; border-radius: 12px;
    padding: 20px 24px; margin-top: 12px;
}
.signed-name {
    font-family: 'Great Vibes', cursive; font-size: 32px;
    color: #065f46; margin-bottom: 8px;
}
.signed-meta { color: #374151; font-size: 13px; }
.signed-meta span { display: block; margin-bottom: 4px; }

.legal-note { color: #6b7280; font-size: 11px; margin-top: 16px; line-height: 1.6; }

.print-btn {
    display: none;
    background: #fff; border: 1.5px solid #d1d5db; border-radius: 8px;
    color: #374151; cursor: pointer; font-family: 'Inter', sans-serif;
    font-size: 14px; font-weight: 600; height: 44px; padding: 0 24px;
    margin-top: 16px; transition: border-color .2s;
}
.print-btn:hover { border-color: #ffd500; }
<?php if ($jaSigned): ?>.print-btn { display: inline-flex; align-items: center; gap: 8px; }<?php endif; ?>

@media print {
    .page-header, .status-bar, form, .print-btn, .btn-submit { display: none !important; }
    body { background: #fff; }
    .card { box-shadow: none; border: 1px solid #eee; page-break-inside: avoid; }
}
</style>
</head>
<body>

<div class="page-header">
    <img src="<?= $logoUrl ?>" alt="MPG Academy">
    <span class="page-header__title">Contrato de Prestação de Serviços</span>
</div>

<div class="container">

<?php if ($erro): ?>
    <div class="status-bar status-bar--erro">
        <span class="status-bar__icon">❌</span>
        <div class="status-bar__info">
            <div><?= htmlspecialchars($erro) ?></div>
        </div>
    </div>
<?php else: ?>

    <?php if ($jaSigned): ?>
    <div class="status-bar status-bar--assinado">
        <span class="status-bar__icon">✅</span>
        <div class="status-bar__info">
            <div>Contrato assinado digitalmente</div>
            <div class="status-bar__label">Assinado em <?= fmtHora2($contrato['assinado_em']) ?></div>
        </div>
    </div>
    <?php else: ?>
    <div class="status-bar status-bar--pendente">
        <span class="status-bar__icon">✍️</span>
        <div class="status-bar__info">
            <div>Aguardando sua assinatura</div>
            <div class="status-bar__label">Leia o contrato abaixo e assine no final da página</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dados do professor -->
    <div class="card">
        <div class="card__header">
            <span class="card__header-icon">👤</span>
            <div>
                <div class="card__header-title"><?= $profNome ?></div>
                <div class="card__header-sub">Prestador de serviço</div>
            </div>
        </div>
    </div>

    <?php if ($diaPgto > 0): ?>
    <!-- Condições de pagamento -->
    <div class="card">
        <div class="card__header">
            <span class="card__header-icon">💰</span>
            <div>
                <div class="card__header-title">Condições de Pagamento</div>
                <div class="card__header-sub">Conforme cadastro do prestador</div>
            </div>
        </div>
        <div class="card__body">
            <div class="pgto-info">
                <span style="font-size:22px">📅</span>
                <span>Data de pagamento: <strong>todo dia <?= $diaPgto ?> de cada mês</strong></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contrato -->
    <div class="card">
        <div class="card__header">
            <span class="card__header-icon">📄</span>
            <div>
                <div class="card__header-title">Contrato de Prestação de Serviços Esportivos</div>
                <div class="card__header-sub">Leia o documento completo antes de assinar</div>
            </div>
        </div>
        <div class="card__body">
            <?php if ($temConteudoHtml): ?>
            <?= $contrato['conteudo_html'] ?>
            <?php else: ?>
            <p style="color:#888;font-size:14px;">Contrato não disponível.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assinatura do professor -->
    <div class="card">
        <div class="card__header">
            <span class="card__header-icon">✍️</span>
            <div>
                <div class="card__header-title">Assinatura do Prestador</div>
                <div class="card__header-sub">Sua assinatura tem validade legal conforme Lei 14.063/2020</div>
            </div>
        </div>
        <div class="card__body">

        <?php if ($jaSigned): ?>
            <div class="signed-block">
                <div class="signed-name"><?= htmlspecialchars($contrato['assinado_nome']) ?></div>
                <div class="signed-meta">
                    <span>CPF: <?= maskCpf2($contrato['assinado_cpf']) ?></span>
                    <span>Assinado em: <?= fmtHora2($contrato['assinado_em']) ?></span>
                </div>
            </div>
            <button class="print-btn" onclick="window.print()">🖨️ Imprimir / salvar PDF</button>
        <?php else: ?>
            <form id="formAssinar">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="field">
                    <label>Nome completo<?= $profNome ? ' (preenchido automaticamente)' : ' *' ?></label>
                    <input type="text" name="nome" id="inputNomeSign"
                           value="<?= htmlspecialchars($profNome) ?>"
                           placeholder="Seu nome completo"
                           <?= $profNome ? 'readonly' : '' ?> required>
                </div>
                <div class="field">
                    <label>CPF<?= $profCpfFmt ? ' (preenchido automaticamente)' : ' *' ?></label>
                    <input type="text" name="cpf" id="inputCpfSign"
                           value="<?= htmlspecialchars($profCpfFmt) ?>"
                           placeholder="000.000.000-00" maxlength="14"
                           <?= $profCpfFmt ? 'readonly' : '' ?> required>
                </div>

                <div class="field">
                    <label>Prévia da assinatura</label>
                    <div class="sig-box">
                        <span class="sig-preview" id="sigPreview"></span>
                        <span class="sig-placeholder" id="sigPlaceholder">Digite seu nome acima para visualizar</span>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="btnAssinar">Assinar contrato</button>
                <div class="form-msg" id="formMsg" style="display:none"></div>
            </form>

            <p class="legal-note">
                Ao assinar, você declara ter lido e aceito todos os termos do contrato acima.
                Esta assinatura eletrônica tem validade jurídica conforme a <strong>Lei nº 14.063/2020</strong>
                e o <strong>Marco Civil da Internet (Lei 12.965/2014)</strong>.
                São registrados: nome completo, CPF, data/hora e endereço IP.
            </p>
        <?php endif; ?>

        </div>
    </div>

    <!-- Assinatura da empresa -->
    <div class="card">
        <div class="card__header">
            <span class="card__header-icon">🏢</span>
            <div>
                <div class="card__header-title">Assinatura da MPG Academy</div>
                <div class="card__header-sub">Assinatura do contratante</div>
            </div>
        </div>
        <div class="card__body">
            <?php if ($assinadoEmpresa): ?>
            <div class="signed-block">
                <div class="signed-name"><?= htmlspecialchars($empresaNome) ?></div>
                <div class="signed-meta">
                    <span>Assinado em: <?= fmtHora2($contrato['assinado_empresa_em']) ?></span>
                </div>
            </div>
            <?php else: ?>
            <div style="color:#888;font-size:14px;display:flex;align-items:center;gap:10px;padding:6px 0;">
                <span style="font-size:22px">⏳</span>
                <span>Aguardando assinatura da MPG Academy.</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>
</div>

<script>
// Máscara CPF
var cpfEl = document.getElementById('inputCpfSign');
if (cpfEl) {
    cpfEl.addEventListener('input', function () {
        var v = this.value.replace(/\D/g,'').slice(0,11);
        v = v.replace(/(\d{3})(\d)/,'$1.$2');
        v = v.replace(/(\d{3})(\d)/,'$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/,'$1-$2');
        this.value = v;
    });
}

// Prévia da assinatura
var nomeEl = document.getElementById('inputNomeSign');
function atualizarPrevia() {
    var preview = document.getElementById('sigPreview');
    var placeholder = document.getElementById('sigPlaceholder');
    if (!preview) return;
    var val = nomeEl ? nomeEl.value.trim() : '';
    if (val) {
        preview.textContent = val;
        placeholder.style.display = 'none';
    } else {
        preview.textContent = '';
        placeholder.style.display = '';
    }
}
if (nomeEl) {
    nomeEl.addEventListener('input', atualizarPrevia);
    atualizarPrevia(); // dispara ao carregar (nome pré-preenchido)
}

// Submissão
var formSign = document.getElementById('formAssinar');
if (formSign) {
    formSign.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btnAssinar');
        var msg = document.getElementById('formMsg');
        btn.disabled = true; btn.textContent = 'Assinando...';
        msg.style.display = 'none';

        fetch('<?= appBaseUrl() ?>/services/site/assinar_contrato_professor.php', {
            method: 'POST',
            body: new FormData(this),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                msg.textContent   = data.message || 'Erro ao assinar.';
                msg.style.color   = '#dc2626';
                msg.style.display = '';
                btn.disabled = false;
                btn.textContent = 'Assinar contrato';
            }
        });
    });
}
</script>
</body>
</html>
