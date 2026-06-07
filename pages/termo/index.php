<?php
define('ROOT', dirname(__DIR__, 2));
require_once ROOT . '/config/app.php';
require_once ROOT . '/config/database.php';

$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');

$pdo   = getDbConnection();
$termo = null;
$aula  = null;
$erro  = '';

if (!$token) {
    $erro = 'Link inválido.';
} else {
    $stmt = $pdo->prepare("
        SELECT ts.*,
               ae.id AS aula_id, ae.data_agendada,
               at.nome AS aluno_nome, at.data_nascimento,
               at.responsavel_nome, at.responsavel_email,
               t.nome AS turma_nome,
               q.nome AS quadra_nome, q.rua, q.numero, q.bairro, q.cidade, q.estado
        FROM termo_assinaturas ts
        JOIN aulas_experimentais ae ON ae.id = ts.aula_experimental_id
        JOIN alunos_teste at ON at.id = ae.aluno_teste_id
        JOIN turmas t ON t.id = ae.turma_id
        JOIN quadras q ON q.id = t.quadra_id
        WHERE ts.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $termo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$termo) $erro = 'Link inválido ou expirado.';
}

$jaAssinou = $termo && !empty($termo['assinado_responsavel_em']);
$concluido = $termo && !empty($termo['assinado_escola_em']) && $jaAssinou;

$logoUrl = appBaseUrl() . '/images/logo.png';

function fmtDataBr($d) {
    if (!$d) return '—';
    $dt = new DateTime($d);
    $m  = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    return $dt->format('d') . ' de ' . $m[(int)$dt->format('n')-1] . ' de ' . $dt->format('Y');
}
function fmtHora($dt) {
    if (!$dt) return '—';
    return (new DateTime($dt))->format('d/m/Y \à\s H:i');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Termo de Responsabilidade – MPG Academy</title>
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

.container { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }

.status-bar {
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 14px;
}
.status-bar--concluido { background: #d1fae5; border: 1.5px solid #10b981; color: #065f46; }
.status-bar--aguardando { background: #fef3c7; border: 1.5px solid #f59e0b; color: #78350f; }
.status-bar--pendente { background: #fee2e2; border: 1.5px solid #ef4444; color: #7f1d1d; }
.status-bar__icon { font-size: 22px; flex-shrink: 0; }
.status-bar__info { flex: 1; }
.status-bar__label { font-size: 12px; font-weight: 500; opacity: .8; }

.card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    margin-bottom: 24px;
    overflow: hidden;
}
.card__head {
    background: #0b0d0f;
    padding: 14px 24px;
    color: #ffd500;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.card__body { padding: 24px; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
.info-item label { font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 3px; }
.info-item span { font-size: 14px; color: #222; font-weight: 500; }

.termo-texto {
    font-size: 13.5px;
    line-height: 1.85;
    color: #333;
    counter-reset: clausula;
}
.termo-texto h2 { font-size: 15px; font-weight: 700; color: #0b0d0f; margin: 20px 0 8px; border-bottom: 2px solid #ffd500; padding-bottom: 6px; }
.termo-texto p  { margin-bottom: 12px; }
.termo-texto .clausula { font-weight: 700; color: #0b0d0f; }

/* Assinaturas */
.assinaturas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 560px) { .assinaturas-grid { grid-template-columns: 1fr; } .info-grid { grid-template-columns: 1fr; } }
.assrow {
    border: 1.5px solid #e0e0e0;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.assrow--signed { border-color: #10b981; background: #f0fdf4; }
.assrow__label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .5px; }
.assrow__nome { font-family: 'Great Vibes', cursive; font-size: 28px; color: #0b0d0f; min-height: 40px; }
.assrow__meta { font-size: 11px; color: #888; }
.assrow__pending { font-size: 13px; color: #aaa; font-style: italic; }

/* Formulário de assinatura */
.sign-form { display: flex; flex-direction: column; gap: 14px; }
.sign-form label { font-size: 12px; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: .4px; display: block; margin-bottom: 4px; }
.sign-form input {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    transition: border-color .2s;
    outline: none;
}
.sign-form input:focus { border-color: #ffd500; }
.sign-preview {
    border: 1.5px dashed #ddd;
    border-radius: 8px;
    padding: 10px 16px;
    min-height: 52px;
    font-family: 'Great Vibes', cursive;
    font-size: 32px;
    color: #0b0d0f;
    background: #fafafa;
    transition: all .2s;
}
.sign-preview.has-text { border-color: #ffd500; background: #fffdf0; }
.btn-assinar {
    background: #ffd500;
    color: #0b0d0f;
    border: none;
    border-radius: 10px;
    padding: 14px 28px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.btn-assinar:hover { background: #e6c000; }
.btn-assinar:disabled { opacity: .5; cursor: not-allowed; }
.sign-aviso { font-size: 12px; color: #888; line-height: 1.5; margin-top: 4px; }
.sign-msg { padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; display: none; }
.sign-msg--ok  { background: #d1fae5; color: #065f46; }
.sign-msg--err { background: #fee2e2; color: #7f1d1d; }

.btn-print {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #0b0d0f;
    color: #ffd500;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    margin-top: 8px;
}
.btn-print:hover { background: #222; }

@media print {
    .page-header, .btn-print, .sign-form, .status-bar { print-color-adjust: exact; }
    .no-print { display: none !important; }
}
</style>
</head>
<body>

<div class="page-header">
    <img src="<?= $logoUrl ?>" alt="MPG Academy">
    <span class="page-header__title">Termo de Responsabilidade</span>
</div>

<div class="container">

<?php if ($erro): ?>
<div class="status-bar status-bar--pendente">
    <span class="status-bar__icon">⚠️</span>
    <div class="status-bar__info"><strong><?= htmlspecialchars($erro) ?></strong></div>
</div>

<?php else: ?>

<!-- Status bar -->
<?php if ($concluido): ?>
<div class="status-bar status-bar--concluido">
    <span class="status-bar__icon">✅</span>
    <div class="status-bar__info">
        <div>Documento assinado por ambas as partes</div>
        <div class="status-bar__label">Válido como assinatura eletrônica simples – Lei nº 14.063/2020</div>
    </div>
    <button class="btn-print no-print" onclick="window.print()">🖨 Imprimir</button>
</div>
<?php elseif ($jaAssinou): ?>
<div class="status-bar status-bar--aguardando">
    <span class="status-bar__icon">🕐</span>
    <div class="status-bar__info">
        <div>Sua assinatura foi recebida. Aguardando assinatura da escola.</div>
        <div class="status-bar__label">Este documento ficará disponível neste link após a conclusão.</div>
    </div>
</div>
<?php elseif (!empty($termo['assinado_escola_em'])): ?>
<div class="status-bar status-bar--aguardando">
    <span class="status-bar__icon">✍️</span>
    <div class="status-bar__info">
        <div>A escola já assinou. Aguardando sua assinatura abaixo.</div>
        <div class="status-bar__label">Leia o termo completo antes de assinar.</div>
    </div>
</div>
<?php else: ?>
<div class="status-bar status-bar--pendente">
    <span class="status-bar__icon">📋</span>
    <div class="status-bar__info">
        <div>Aguardando assinaturas de ambas as partes.</div>
        <div class="status-bar__label">Leia o termo completo abaixo e assine.</div>
    </div>
</div>
<?php endif; ?>

<!-- Dados da aula -->
<div class="card">
    <div class="card__head">Dados do Aluno</div>
    <div class="card__body">
        <div class="info-grid">
            <div class="info-item"><label>Aluno</label><span><?= htmlspecialchars($termo['aluno_nome']) ?></span></div>
            <div class="info-item"><label>Turma</label><span><?= htmlspecialchars($termo['turma_nome']) ?></span></div>
            <div class="info-item"><label>Local</label><span><?= htmlspecialchars($termo['quadra_nome']) ?></span></div>
            <div class="info-item"><label>Data da Aula</label><span><?= fmtDataBr($termo['data_agendada']) ?></span></div>
            <div class="info-item"><label>Responsável</label><span><?= htmlspecialchars($termo['responsavel_nome'] ?? '—') ?></span></div>
        </div>
    </div>
</div>

<!-- Termo -->
<div class="card">
    <div class="card__head">Termo de Autorização e Responsabilidade – Menor de Idade</div>
    <div class="card__body">
        <div class="termo-texto">
            <p>Eu, responsável legal pelo(a) menor <strong><?= htmlspecialchars($termo['aluno_nome']) ?></strong>, declaro para os devidos fins que autorizo a participação do(a) referido(a) menor nas atividades esportivas de voleibol oferecidas pela <strong>MPG Academy</strong>, conforme as cláusulas e condições estabelecidas a seguir.</p>

            <h2>Cláusula 1 – Da Autorização</h2>
            <p>Autorizo o(a) menor a participar da <strong>aula experimental</strong> e, caso haja matrícula, das demais atividades regulares da <strong><?= htmlspecialchars($termo['turma_nome']) ?></strong> ministrada no local <strong><?= htmlspecialchars($termo['quadra_nome']) ?></strong>, ciente de que se tratam de atividades físicas com movimentos esportivos que exigem condicionamento físico adequado.</p>

            <h2>Cláusula 2 – Da Aptidão Física</h2>
            <p>Declaro que o(a) menor se encontra em boas condições de saúde e apto(a) à prática de atividades físicas esportivas, não apresentando nenhuma contraindicação médica que impeça sua participação. Responsabilizo-me por comunicar à MPG Academy qualquer alteração em seu estado de saúde.</p>

            <h2>Cláusula 3 – Da Responsabilidade</h2>
            <p>Assumo plena responsabilidade por quaisquer eventos decorrentes da participação do(a) menor nas atividades, isentando a MPG Academy, seus professores, funcionários e parceiros de responsabilidade civil por acidentes ou imprevistos que não decorram de negligência comprovada da escola.</p>

            <h2>Cláusula 4 – Uso de Imagem</h2>
            <p>Autorizo o uso da imagem e voz do(a) menor em fotos e vídeos produzidos pela MPG Academy para fins institucionais, redes sociais e divulgação das atividades, sem fins lucrativos diretos ao menor.</p>

            <h2>Cláusula 5 – Da Validade Eletrônica</h2>
            <p>As partes reconhecem que a assinatura eletrônica deste documento possui plena validade jurídica, nos termos da <strong>Lei nº 14.063/2020</strong> e do <strong>Marco Civil da Internet (Lei nº 12.965/2014)</strong>, sendo gerado registro de identidade, data, hora e endereço IP de cada assinante.</p>

            <p style="margin-top:20px;color:#888;font-size:12px;">
                São Paulo, <?= fmtDataBr(date('Y-m-d')) ?>
            </p>
        </div>
    </div>
</div>

<!-- Assinaturas -->
<div class="card">
    <div class="card__head">Assinaturas</div>
    <div class="card__body">
        <div class="assinaturas-grid">
            <!-- Escola -->
            <div class="assrow <?= !empty($termo['assinado_escola_em']) ? 'assrow--signed' : '' ?>">
                <div class="assrow__label">Responsável pela Escola</div>
                <?php if (!empty($termo['assinado_escola_em'])): ?>
                    <div class="assrow__nome"><?= htmlspecialchars($termo['assinante_escola_nome']) ?></div>
                    <div class="assrow__meta">MPG Academy<br><?= fmtHora($termo['assinado_escola_em']) ?></div>
                <?php else: ?>
                    <div class="assrow__pending">Aguardando assinatura da escola</div>
                <?php endif; ?>
            </div>

            <!-- Responsável -->
            <div class="assrow <?= $jaAssinou ? 'assrow--signed' : '' ?>">
                <div class="assrow__label">Responsável pelo Aluno</div>
                <?php if ($jaAssinou): ?>
                    <div class="assrow__nome"><?= htmlspecialchars($termo['responsavel_nome_assinado']) ?></div>
                    <div class="assrow__meta">CPF: <?= substr($termo['responsavel_cpf_assinado'], 0, 3) . '.***.***-' . substr($termo['responsavel_cpf_assinado'], -2) ?><br><?= fmtHora($termo['assinado_responsavel_em']) ?></div>
                <?php else: ?>
                    <div class="assrow__pending">Aguardando assinatura</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Formulário de assinatura (só mostra se responsável ainda não assinou) -->
<?php if (!$jaAssinou): ?>
<div class="card no-print">
    <div class="card__head">✍ Assinar como Responsável</div>
    <div class="card__body">
        <form class="sign-form" id="signForm">
            <div>
                <label>Seu nome completo <span style="color:#e00">*</span></label>
                <input type="text" id="signNome" placeholder="Digite seu nome completo" autocomplete="name" required>
                <div class="sign-preview" id="signPreview"></div>
            </div>
            <div>
                <label>CPF <span style="color:#e00">*</span></label>
                <input type="text" id="signCpf" placeholder="000.000.000-00" maxlength="14" required>
            </div>
            <p class="sign-aviso">Ao clicar em "Assinar", você confirma que leu e concorda com todos os termos acima. Será gerado um registro com seu nome, CPF, data, hora e IP para fins de comprovação legal.</p>
            <div class="sign-msg" id="signMsg"></div>
            <button type="submit" class="btn-assinar" id="signBtn">✍ Assinar o Termo</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php endif; // fim do else do $erro ?>

</div><!-- /container -->

<script>
var TOKEN = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';
var BASE  = '<?= rtrim(appBaseUrl(), '/') ?>';

// Preview da assinatura em tempo real
var nomeInput = document.getElementById('signNome');
var preview   = document.getElementById('signPreview');
if (nomeInput) {
    nomeInput.addEventListener('input', function () {
        var v = this.value.trim();
        preview.textContent = v;
        preview.classList.toggle('has-text', v.length > 0);
    });
}

// Máscara CPF
var cpfInput = document.getElementById('signCpf');
if (cpfInput) {
    cpfInput.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 9)      v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d+)/, '$1.$2.$3-$4');
        else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d+)/, '$1.$2.$3');
        else if (v.length > 3) v = v.replace(/^(\d{3})(\d+)/, '$1.$2');
        this.value = v;
    });
}

// Submit
var form = document.getElementById('signForm');
var msg  = document.getElementById('signMsg');
var btn  = document.getElementById('signBtn');
if (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var nome = nomeInput.value.trim();
        var cpf  = cpfInput.value.replace(/\D/g, '');
        if (!nome || cpf.length < 11) {
            showMsg('Preencha nome e CPF completo.', false);
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Assinando...';

        var fd = new FormData();
        fd.append('token', TOKEN);
        fd.append('nome', nome);
        fd.append('cpf', cpf);

        fetch(BASE + '/services/site/assinar_responsavel.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    showMsg('✅ Assinatura registrada com sucesso! A página será atualizada.', true);
                    setTimeout(function () { location.reload(); }, 2000);
                } else {
                    btn.disabled = false;
                    btn.textContent = '✍ Assinar o Termo';
                    showMsg(res.message || 'Erro ao assinar.', false);
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = '✍ Assinar o Termo';
                showMsg('Erro de conexão. Tente novamente.', false);
            });
    });
}

function showMsg(text, ok) {
    msg.textContent = text;
    msg.className = 'sign-msg ' + (ok ? 'sign-msg--ok' : 'sign-msg--err');
    msg.style.display = 'block';
}
</script>
</body>
</html>
