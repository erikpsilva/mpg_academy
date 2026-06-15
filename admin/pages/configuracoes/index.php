<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/mercadopago.php';

$pdo       = getDbConnection();
$modoTeste = mpModoTeste($pdo);

$cfgMatricula = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'valor_matricula'")->fetch();
$valorMatricula = $cfgMatricula ? (float) $cfgMatricula['valor'] : 0.0;

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
                    <div class="configRow configRow--stack">
                        <div class="configRow__info">
                            <strong>Taxa de matrícula</strong>
                            <p>Valor cobrado uma única vez quando o aluno é adicionado a uma turma pela primeira vez. Use <strong>0</strong> para desativar a cobrança.</p>
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

</body>
</html>
