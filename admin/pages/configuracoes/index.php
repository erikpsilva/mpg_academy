<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/mercadopago.php';

$pdo       = getDbConnection();
$modoTeste = mpModoTeste($pdo);

require_once ROOT . '/config/uniformes.php';
$valorUniforme       = uniformeValor($pdo);
$valorUniformeEquipe = uniformeValorEquipe($pdo);

// Cobrança de teste: uma avulsa, de valor baixo, do aluno de teste. Serve pra validar o
// fluxo de pagamento em produção sem tocar na mensalidade de ninguém.
$stTeste = $pdo->prepare("
    SELECT m.id, m.valor, m.status, a.nome, a.email
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
    WHERE a.email = 'teste.pagamento@mpgacademy.com.br' AND m.tipo = 'avulso'
    ORDER BY m.id DESC LIMIT 1
");
$stTeste->execute();
$cobrancaTeste = $stTeste->fetch();

$cfgMatricula = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'valor_matricula'")->fetch();
$valorMatricula = $cfgMatricula ? (float) $cfgMatricula['valor'] : 0.0;

$cfgMatriculaAtiva = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'matricula_ativa'")->fetch();
$matriculaAtiva = $cfgMatriculaAtiva === false || $cfgMatriculaAtiva['valor'] !== '0';

// Carrega emails de notificação cadastrados
$stEmails        = $pdo->query("SELECT id, email, nome FROM emails_notificacao WHERE ativo = 1 ORDER BY id");
$emailsNotificacao = $stEmails->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Configurações</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="alunos">
            <div class="row alunos__header">
                <div class="col-md-8">
                    <h2>Configura<span>ções</span></h2>
                    <p>Gerencie as configurações do sistema MPG Academy.</p>
                </div>
            </div>

            <!-- ── Matrícula ───────────────────────────────────────── -->
            <div class="configSection configSection--first">
                <h3>Matrícula</h3>
                <div class="configCard">

                    <div class="configRow">
                        <div class="configRow__info">
                            <strong>Cobrar taxa de matrícula</strong>
                            <p>Quando desativado, nenhum aluno novo é cobrado de matrícula, independente do valor configurado abaixo.</p>
                        </div>
                        <label class="toggle" title="Ativar/desativar cobrança de matrícula">
                            <input type="checkbox" id="toggleMatriculaAtiva" <?= $matriculaAtiva ? 'checked' : '' ?>>
                            <span class="toggle__slider"></span>
                        </label>
                    </div>

                    <div class="configRow configRow--stack">
                        <div class="configRow__info">
                            <strong>Taxa de matrícula</strong>
                            <p>Valor cobrado uma única vez quando o aluno é adicionado a uma turma pela primeira vez (somente se a cobrança estiver ativada acima).</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                            <span style="color:#aaa;font-size:14px;">R$</span>
                            <input type="number" id="inputValorMatricula" min="0" step="0.01"
                                   value="<?= number_format($valorMatricula, 2, '.', '') ?>"
                                   style="background:#1a1a1a;border:1px solid #333;border-radius:6px;color:#ddd;font-size:14px;padding:9px 12px;width:130px;">
                            <button class="btn btn--primary btn--sm" id="btnSalvarMatricula">Salvar</button>
                        </div>
                        <div id="matriculaMsg" class="configMsg" style="margin-top:8px;"></div>
                    </div>
                </div>
            </div>

            <!-- Uniformes -->
            <div class="configSection">
                <h3>Uniformes</h3>
                <div class="configCard">

                    <div class="configRow configRow--stack">
                        <div class="configRow__info">
                            <strong>Uniforme completo</strong>
                            <p>Camisa + calção. É o valor cobrado do aluno no pedido pelo site.</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                            <span style="color:#aaa;font-size:14px;">R$</span>
                            <input type="number" id="inputValorUniforme" min="0" step="0.01"
                                   value="<?= number_format($valorUniforme, 2, '.', '') ?>"
                                   style="background:#1a1a1a;border:1px solid #333;border-radius:6px;color:#ddd;font-size:14px;padding:9px 12px;width:130px;">
                            <button class="btn btn--primary btn--sm" id="btnSalvarUniforme">Salvar</button>
                        </div>
                        <div id="uniformeMsg" class="configMsg" style="margin-top:8px;"></div>
                    </div>

                    <div class="configRow configRow--stack">
                        <div class="configRow__info">
                            <strong>Camisa da equipe técnica</strong>
                            <p>Só a camisa, para professores e equipe MPG. Não é cobrada pelo sistema — o valor fica registrado no pedido só pra você saber quanto custou.</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                            <span style="color:#aaa;font-size:14px;">R$</span>
                            <input type="number" id="inputValorUniformeEquipe" min="0" step="0.01"
                                   value="<?= number_format($valorUniformeEquipe, 2, '.', '') ?>"
                                   style="background:#1a1a1a;border:1px solid #333;border-radius:6px;color:#ddd;font-size:14px;padding:9px 12px;width:130px;">
                            <button class="btn btn--primary btn--sm" id="btnSalvarUniformeEquipe">Salvar</button>
                        </div>
                        <div id="uniformeEquipeMsg" class="configMsg" style="margin-top:8px;"></div>
                    </div>

                </div>
            </div>

            <!-- Teste de pagamento -->
            <div class="configSection">
                <h3>Teste de pagamento</h3>
                <div class="configCard">

                    <div class="configRow configRow--stack">
                        <div class="configRow__info">
                            <strong>Cobrança do aluno de teste</strong>
                            <?php if ($cobrancaTeste): ?>
                            <p>
                                Cobrança avulsa de <strong><?= htmlspecialchars($cobrancaTeste['nome']) ?></strong>,
                                usada pra testar o pagamento de verdade sem mexer na mensalidade de ninguém.
                                Situação agora: <strong><?= htmlspecialchars($cobrancaTeste['status']) ?></strong>.
                            </p>
                            <?php else: ?>
                            <p style="color:#e57373;">
                                Aluno de teste não encontrado — rode o SQL de criação antes de usar esta seção.
                            </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($cobrancaTeste): ?>
                        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;flex-wrap:wrap;">
                            <span style="color:#aaa;font-size:14px;">R$</span>
                            <input type="number" id="inputCobrancaTeste" min="1" max="50" step="0.01"
                                   value="<?= number_format((float) $cobrancaTeste['valor'], 2, '.', '') ?>"
                                   style="background:#1a1a1a;border:1px solid #333;border-radius:6px;color:#ddd;font-size:14px;padding:9px 12px;width:110px;">
                            <button class="btn btn--primary btn--sm" id="btnSalvarCobrancaTeste">Salvar valor</button>
                            <button class="btn btn--gray btn--sm" id="btnResetarCobrancaTeste">Voltar para pendente</button>
                        </div>
                        <div id="cobrancaTesteMsg" class="configMsg" style="margin-top:8px;"></div>
                        <p style="margin-top:10px;color:#888;font-size:12px;line-height:1.6;">
                            Entre na área do aluno com <strong>teste.pagamento@mpgacademy.com.br</strong> e pague essa cobrança
                            para validar o fluxo ponta a ponta. Depois clique em <em>Voltar para pendente</em> para testar de novo —
                            isso também remove o lançamento de receita que a baixa gerou.
                        </p>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- ── Pagamentos ───────────────────────────────────────── -->
            <div class="configSection">
                <h3>Pagamentos — Mercado Pago</h3>
                <div class="configCard">

                    <div class="configRow">
                        <div class="configRow__info">
                            <strong>Modo de Teste</strong>
                            <p>Quando ativo, todas as cobranças usam as credenciais de sandbox do Mercado Pago. Nenhum valor real é cobrado.</p>
                            <span class="configBadge <?= $modoTeste ? 'configBadge--test' : 'configBadge--prod' ?>" id="modoBadge">
                                <?= $modoTeste ? 'SANDBOX — TESTE' : 'PRODUÇÃO — REAL' ?>
                            </span>
                        </div>
                        <label class="toggle" title="Ativar/desativar modo de teste">
                            <input type="checkbox" id="toggleModoteste" <?= $modoTeste ? 'checked' : '' ?>>
                            <span class="toggle__slider"></span>
                        </label>
                    </div>

                    <div class="configRow" id="credRow">
                        <div class="configRow__info">
                            <strong>Credenciais ativas</strong>
                            <p id="credDesc">
                                <?php if ($modoTeste): ?>
                                    Public Key de teste: <code><?= substr(MP_PUBLIC_KEY_TEST, 0, 24) ?>…</code>
                                <?php else: ?>
                                    Public Key de produção: <code><?= substr(MP_PUBLIC_KEY_PROD, 0, 24) ?>…</code>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <?php if (($_SESSION['usuario']['nivel_acesso'] ?? '') === 'admin'): ?>
                    <div class="configRow configRow--stack">
                        <div class="configRow__info">
                            <strong>Cobrança automática de hoje</strong>
                            <p>Cobra agora, na hora, todos os alunos com pagamento automático ativado cujas faturas já venceram — mesmo que os crons das 07:00/15:00 ainda não tenham rodado. Faturas já cobradas com sucesso hoje não são cobradas de novo.</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;margin-top:8px;">
                            <button class="btn btn--primary btn--sm" id="btnRodarCobranca">Rodar cobrança de hoje</button>
                        </div>
                        <div id="cobrancaMsg" class="configMsg" style="margin-top:8px;"></div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <div id="saveMsg" class="configMsg"></div>

            <!-- ── E-mails de Notificação ───────────────────────────── -->
            <div class="configSection">
                <h3>E-mails de Notificação de Atraso</h3>
                <div class="configCard">

                    <div class="configRow configRow--stack">
                        <div class="configRow__info">
                            <strong>Destinatários internos</strong>
                            <p>Esses e-mails receberão uma cópia sempre que um aluno for notificado de mensalidade em atraso (25+ dias).</p>
                        </div>

                        <!-- Lista de emails cadastrados -->
                        <div id="emailNotifList" class="emailNotifList">
                            <?php if (empty($emailsNotificacao)): ?>
                            <p class="emailNotifEmpty" id="emailNotifVazio">Nenhum e-mail cadastrado.</p>
                            <?php else: ?>
                            <?php foreach ($emailsNotificacao as $en): ?>
                            <div class="emailNotifRow" id="emailRow<?= $en['id'] ?>">
                                <div class="emailNotifRow__info">
                                    <strong><?= htmlspecialchars($en['email']) ?></strong>
                                    <?php if ($en['nome']): ?>
                                    <span><?= htmlspecialchars($en['nome']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn--sm btn--error btnRemoverEmail"
                                        data-id="<?= $en['id'] ?>">Remover</button>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Formulário para adicionar -->
                        <div class="emailNotifForm">
                            <input type="email" id="novoEmailNotif" class="input"
                                   placeholder="email@exemplo.com">
                            <input type="text" id="novoNomeNotif" class="input"
                                   placeholder="Nome (opcional)">
                            <button class="btn btn--primary" id="btnAdicionarEmail">Adicionar</button>
                        </div>
                        <div id="emailNotifMsg" class="configMsg"></div>
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
var PK_TEST = "<?= substr(MP_PUBLIC_KEY_TEST, 0, 24) ?>…";
var PK_PROD = "<?= substr(MP_PUBLIC_KEY_PROD, 0, 24) ?>";

// ── Matrícula ─────────────────────────────────────────────────────────────────
(function () {
    var btn = document.getElementById('btnSalvarMatricula');
    var input = document.getElementById('inputValorMatricula');
    var msg = document.getElementById('matriculaMsg');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var valor = parseFloat(input.value);
        if (isNaN(valor) || valor < 0) {
            msg.textContent = 'Informe um valor válido (0 para desativar).';
            msg.className   = 'configMsg is-error';
            return;
        }
        btn.disabled = true;
        msg.className = 'configMsg';

        var body = new URLSearchParams({ chave: 'valor_matricula', valor: valor.toFixed(2) });
        fetch(ADMIN_BASE_URL + '/services/save_configuracao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                msg.textContent = valor > 0
                    ? 'Taxa de R$ ' + valor.toFixed(2).replace('.', ',') + ' salva.'
                    : 'Taxa de matrícula desativada.';
                msg.className = 'configMsg is-success';
            } else {
                msg.textContent = 'Erro: ' + (data.message || '');
                msg.className   = 'configMsg is-error';
            }
        })
        .catch(function () {
            msg.textContent = 'Erro de comunicação.';
            msg.className   = 'configMsg is-error';
        })
        .finally(function () { btn.disabled = false; });
    });
}());

// ── Matrícula ativa/inativa ──────────────────────────────────────────────────
(function () {
    var toggle = document.getElementById('toggleMatriculaAtiva');
    var msg    = document.getElementById('matriculaMsg');
    if (!toggle) return;

    toggle.addEventListener('change', function () {
        var ativo = this.checked;
        msg.className = 'configMsg';

        var body = new URLSearchParams({ chave: 'matricula_ativa', valor: ativo ? '1' : '0' });
        fetch(ADMIN_BASE_URL + '/services/save_configuracao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                msg.textContent = ativo ? 'Cobrança de matrícula ativada.' : 'Cobrança de matrícula desativada.';
                msg.className   = 'configMsg is-success';
            } else {
                toggle.checked  = !ativo;
                msg.textContent = 'Erro ao salvar: ' + (data.message || '');
                msg.className   = 'configMsg is-error';
            }
        })
        .catch(function () {
            toggle.checked  = !ativo;
            msg.textContent = 'Erro de comunicação.';
            msg.className   = 'configMsg is-error';
        });
    });
}());

(function () {
    var toggle  = document.getElementById('toggleModoteste');
    var badge   = document.getElementById('modoBadge');
    var credDesc = document.getElementById('credDesc');
    var msg     = document.getElementById('saveMsg');
    if (!toggle) return;

    toggle.addEventListener('change', function () {
        var isTeste = this.checked;
        msg.className = 'configMsg';
        msg.textContent = '';

        var body = new URLSearchParams({ chave: 'pagamento_modo_teste', valor: isTeste ? '1' : '0' });
        fetch(ADMIN_BASE_URL + '/services/save_configuracao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                badge.textContent     = isTeste ? 'SANDBOX — TESTE' : 'PRODUÇÃO — REAL';
                badge.className       = 'configBadge ' + (isTeste ? 'configBadge--test' : 'configBadge--prod');
                credDesc.innerHTML    = isTeste
                    ? 'Public Key de teste: <code>' + PK_TEST + '</code>'
                    : 'Public Key de produção: <code>' + PK_PROD + '</code>';
                msg.textContent       = 'Configuração salva.';
                msg.className         = 'configMsg is-success';
            } else {
                toggle.checked = !isTeste;
                msg.textContent   = 'Erro ao salvar: ' + (data.message || '');
                msg.className     = 'configMsg is-error';
            }
        })
        .catch(function () {
            toggle.checked = !isTeste;
            msg.textContent   = 'Erro de comunicação.';
            msg.className     = 'configMsg is-error';
        });
    });
}());

// ── Rodar cobrança automática manualmente ────────────────────────────────────
(function () {
    var btn = document.getElementById('btnRodarCobranca');
    var msg = document.getElementById('cobrancaMsg');
    if (!btn) return;

    btn.addEventListener('click', function () {
        if (!confirm('Isso vai tentar cobrar agora, na hora, todos os alunos com pagamento automático ativado e fatura já vencida. Faturas já cobradas com sucesso hoje não são cobradas de novo. Continuar?')) return;

        btn.disabled    = true;
        btn.textContent = 'Cobrando...';
        msg.className   = 'configMsg';
        msg.textContent = '';

        fetch(ADMIN_BASE_URL + '/services/rodar_cobranca_automatica.php', {
            method: 'POST', credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            msg.textContent = data.message || (data.success ? 'Concluído.' : 'Erro ao rodar cobrança.');
            // Verde só se rodou sem nenhuma falha de cobrança — "success:true" da API só
            // confirma que a chamada funcionou, não que todas as cobranças deram certo.
            var semFalha = data.success && (!data.falha || data.falha === 0);
            msg.className = 'configMsg ' + (semFalha ? 'is-success' : 'is-error');
        })
        .catch(function () {
            msg.textContent = 'Erro de comunicação.';
            msg.className   = 'configMsg is-error';
        })
        .finally(function () {
            btn.disabled    = false;
            btn.textContent = 'Rodar cobrança de hoje';
        });
    });
}());

// ── E-mails de notificação ────────────────────────────────────────────────────
(function () {
    var btnAdd  = document.getElementById('btnAdicionarEmail');
    var inputEmail = document.getElementById('novoEmailNotif');
    var inputNome  = document.getElementById('novoNomeNotif');
    var msgEl   = document.getElementById('emailNotifMsg');
    var lista   = document.getElementById('emailNotifList');

    function showMsg(texto, ok) {
        msgEl.textContent   = texto;
        msgEl.className     = 'configMsg ' + (ok ? 'is-success' : 'is-error');
        setTimeout(function () { msgEl.className = 'configMsg'; }, 3500);
    }

    function bindRemover(btn) {
        btn.addEventListener('click', function () {
            var id = this.dataset.id;
            if (!confirm('Remover este e-mail?')) return;
            var body = new URLSearchParams({ acao: 'remove', id: id });
            fetch(ADMIN_BASE_URL + '/services/save_email_notificacao.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    var row = document.getElementById('emailRow' + id);
                    if (row) row.remove();
                    if (!lista.querySelector('.emailNotifRow')) {
                        lista.innerHTML = '<p class="emailNotifEmpty" id="emailNotifVazio">Nenhum e-mail cadastrado.</p>';
                    }
                } else {
                    showMsg(data.message || 'Erro ao remover.', false);
                }
            });
        });
    }

    // Bind nos botões existentes
    document.querySelectorAll('.btnRemoverEmail').forEach(bindRemover);

    btnAdd.addEventListener('click', function () {
        var email = inputEmail.value.trim();
        var nome  = inputNome.value.trim();
        if (!email) { showMsg('Informe um e-mail.', false); return; }

        btnAdd.disabled = true;
        var body = new URLSearchParams({ acao: 'add', email: email, nome: nome });
        fetch(ADMIN_BASE_URL + '/services/save_email_notificacao.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                // Remove msg vazia
                var vazio = lista.querySelector('p');
                if (vazio) vazio.remove();

                // Cria linha
                var div = document.createElement('div');
                div.className = 'emailNotifRow';
                div.id = 'emailRow' + data.id;
                div.innerHTML = '<div class="emailNotifRow__info">'
                    + '<strong>' + data.email + '</strong>'
                    + (data.nome ? '<span>' + data.nome + '</span>' : '')
                    + '</div>'
                    + '<button class="btn btn--sm btn--error btnRemoverEmail" data-id="' + data.id + '">Remover</button>';
                lista.appendChild(div);
                bindRemover(div.querySelector('.btnRemoverEmail'));

                inputEmail.value = '';
                inputNome.value  = '';
                showMsg('E-mail adicionado.', true);
            } else {
                showMsg(data.message || 'Erro ao adicionar.', false);
            }
        })
        .catch(function () { showMsg('Erro de comunicação.', false); })
        .finally(function () { btnAdd.disabled = false; });
    });
}());
</script>

<script>
/**
 * Preços dos uniformes.
 *
 * Os dois usam o mesmo save_configuracao.php das outras chaves — só muda qual chave é
 * gravada. Ficarem aqui evita ter que mexer em código toda vez que o fornecedor reajusta.
 */
(function () {
    function ligarPreco(idInput, idBotao, idMsg, chave, rotulo) {
        var input = document.getElementById(idInput);
        var btn   = document.getElementById(idBotao);
        var msg   = document.getElementById(idMsg);
        if (!input || !btn || !msg) return;

        btn.addEventListener('click', function () {
            var valor = parseFloat(input.value);
            if (isNaN(valor) || valor < 0) {
                msg.textContent = 'Informe um valor válido.';
                msg.className   = 'configMsg is-error';
                return;
            }

            btn.disabled  = true;
            msg.className = 'configMsg';

            var body = new URLSearchParams({ chave: chave, valor: valor.toFixed(2) });
            fetch(ADMIN_BASE_URL + '/services/save_configuracao.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    msg.textContent = rotulo + ': R$ ' + valor.toFixed(2).replace('.', ',') + ' salvo.';
                    msg.className   = 'configMsg is-ok';
                } else {
                    msg.textContent = data.message || 'Não foi possível salvar.';
                    msg.className   = 'configMsg is-error';
                }
            })
            .catch(function () {
                msg.textContent = 'Erro de conexão.';
                msg.className   = 'configMsg is-error';
            })
            .finally(function () { btn.disabled = false; });
        });
    }

    ligarPreco('inputValorUniforme',       'btnSalvarUniforme',       'uniformeMsg',       'valor_uniforme',        'Uniforme completo');
    ligarPreco('inputValorUniformeEquipe', 'btnSalvarUniformeEquipe', 'uniformeEquipeMsg', 'valor_uniforme_equipe', 'Camisa da equipe técnica');
}());
</script>

<script>
// Cobrança de teste: muda o valor e devolve pra pendente, pra poder testar o pagamento
// de verdade quantas vezes precisar.
(function () {
    var input = document.getElementById('inputCobrancaTeste');
    var msg   = document.getElementById('cobrancaTesteMsg');
    if (!input || !msg) return;   // aluno de teste ainda não criado

    function chamar(dados, botao, textoOriginal) {
        botao.disabled = true;
        msg.className = 'configMsg';

        fetch(ADMIN_BASE_URL + '/services/salvar_cobranca_teste.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams(dados).toString()
        })
        .then(function (r) {
            return r.text().then(function (t) {
                try { return JSON.parse(t); }
                catch (e) { return { success: false, message: 'Erro ' + r.status + ' no servidor.' }; }
            });
        })
        .then(function (d) {
            msg.textContent = d.message || (d.success ? 'Feito.' : 'Não foi possível.');
            msg.className   = 'configMsg ' + (d.success ? 'is-ok' : 'is-error');
            botao.disabled  = false;
            botao.textContent = textoOriginal;
        })
        .catch(function () {
            msg.textContent = 'Erro de conexão.';
            msg.className   = 'configMsg is-error';
            botao.disabled  = false;
            botao.textContent = textoOriginal;
        });
    }

    document.getElementById('btnSalvarCobrancaTeste').addEventListener('click', function () {
        var v = parseFloat(input.value);
        if (isNaN(v) || v < 1 || v > 50) {
            msg.textContent = 'Use um valor entre R$ 1,00 e R$ 50,00.';
            msg.className   = 'configMsg is-error';
            return;
        }
        chamar({ acao: 'valor', valor: v.toFixed(2) }, this, 'Salvar valor');
    });

    document.getElementById('btnResetarCobrancaTeste').addEventListener('click', function () {
        chamar({ acao: 'resetar' }, this, 'Voltar para pendente');
    });
}());
</script>

</body>
</html>
