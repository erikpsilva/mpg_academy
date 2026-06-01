<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/mercadopago.php';

$pdo       = getDbConnection();
$modoTeste = mpModoTeste($pdo);

// Carrega emails de notificação cadastrados
$stEmails        = $pdo->query("SELECT id, email, nome FROM emails_notificacao WHERE ativo = 1 ORDER BY id");
$emailsNotificacao = $stEmails->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Configurações</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<style>
.configSection { margin-bottom: 32px; }
.configSection h3 { font-size: 14px; text-transform: uppercase; letter-spacing: .08em; color: #888; margin-bottom: 16px; }
.configCard { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; overflow: hidden; }
.configRow { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #222; gap: 24px; }
.configRow:last-child { border-bottom: none; }
.configRow__info strong { display: block; font-size: 15px; margin-bottom: 4px; color: #eee; }
.configRow__info p { font-size: 13px; color: #888; margin: 0; }
.configBadge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: .05em; margin-top: 6px; }
.configBadge--test { background: #2a2a00; color: #cccc00; border: 1px solid #666600; }
.configBadge--prod { background: #002a00; color: #00cc44; border: 1px solid #006622; }

/* Toggle Switch */
.toggle { position: relative; display: inline-block; width: 52px; height: 28px; flex-shrink: 0; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle__slider { position: absolute; inset: 0; background: #333; border-radius: 28px; cursor: pointer; transition: .25s; }
.toggle__slider::before { content: ''; position: absolute; left: 4px; top: 4px; width: 20px; height: 20px; background: #666; border-radius: 50%; transition: .25s; }
.toggle input:checked + .toggle__slider { background: #1a1a00; border: 1px solid #e5c200; }
.toggle input:checked + .toggle__slider::before { background: #e5c200; transform: translateX(24px); }
</style>
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

            <!-- ── Pagamentos ───────────────────────────────────────── -->
            <div class="configSection" style="margin-top:28px;">
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
                                    Public Key de teste: <code style="font-size:11px;color:#aaa;"><?= substr(MP_PUBLIC_KEY_TEST, 0, 24) ?>…</code>
                                <?php else: ?>
                                    Public Key de produção: <code style="font-size:11px;color:#aaa;"><?= substr(MP_PUBLIC_KEY_PROD, 0, 24) ?>…</code>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <div id="saveMsg" style="display:none;margin-top:12px;font-size:13px;color:#7ecf7e;"></div>

            <!-- ── E-mails de Notificação ───────────────────────────── -->
            <div class="configSection" style="margin-top:36px;">
                <h3>E-mails de Notificação de Atraso</h3>
                <div class="configCard">

                    <div class="configRow" style="flex-direction:column;align-items:flex-start;gap:16px;">
                        <div class="configRow__info">
                            <strong>Destinatários internos</strong>
                            <p>Esses e-mails receberão uma cópia sempre que um aluno for notificado de mensalidade em atraso (25+ dias).</p>
                        </div>

                        <!-- Lista de emails cadastrados -->
                        <div id="emailNotifList" style="width:100%;">
                            <?php if (empty($emailsNotificacao)): ?>
                            <p style="color:#666;font-size:13px;" id="emailNotifVazio">Nenhum e-mail cadastrado.</p>
                            <?php else: ?>
                            <?php foreach ($emailsNotificacao as $en): ?>
                            <div class="emailNotifRow" id="emailRow<?= $en['id'] ?>"
                                 style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #1e1e1e;">
                                <div style="flex:1;min-width:0;">
                                    <strong style="font-size:13px;color:#eee;"><?= htmlspecialchars($en['email']) ?></strong>
                                    <?php if ($en['nome']): ?>
                                    <span style="font-size:12px;color:#888;margin-left:8px;"><?= htmlspecialchars($en['nome']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn--sm btn--error btnRemoverEmail"
                                        data-id="<?= $en['id'] ?>"
                                        style="padding:4px 12px;font-size:12px;">Remover</button>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Formulário para adicionar -->
                        <div style="display:flex;gap:10px;flex-wrap:wrap;width:100%;margin-top:4px;">
                            <input type="email" id="novoEmailNotif" class="input"
                                   placeholder="email@exemplo.com"
                                   style="flex:1;min-width:200px;">
                            <input type="text" id="novoNomeNotif" class="input"
                                   placeholder="Nome (opcional)"
                                   style="width:180px;">
                            <button class="btn btn--primary" id="btnAdicionarEmail">Adicionar</button>
                        </div>
                        <div id="emailNotifMsg" style="display:none;font-size:13px;"></div>
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

(function () {
    var toggle  = document.getElementById('toggleModoteste');
    var badge   = document.getElementById('modoBadge');
    var credDesc = document.getElementById('credDesc');
    var msg     = document.getElementById('saveMsg');
    if (!toggle) return;

    toggle.addEventListener('change', function () {
        var isTeste = this.checked;
        msg.style.display = 'none';

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
                    ? 'Public Key de teste: <code style="font-size:11px;color:#aaa;">' + PK_TEST + '</code>'
                    : 'Public Key de produção: <code style="font-size:11px;color:#aaa;">' + PK_PROD + '</code>';
                msg.textContent       = 'Configuração salva.';
                msg.style.color       = '#7ecf7e';
                msg.style.display     = '';
            } else {
                toggle.checked = !isTeste;
                msg.textContent   = 'Erro ao salvar: ' + (data.message || '');
                msg.style.color   = '#cf7e7e';
                msg.style.display = '';
            }
        })
        .catch(function () {
            toggle.checked = !isTeste;
            msg.textContent   = 'Erro de comunicação.';
            msg.style.color   = '#cf7e7e';
            msg.style.display = '';
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
        msgEl.style.color   = ok ? '#7ecf7e' : '#cf7e7e';
        msgEl.style.display = '';
        setTimeout(function () { msgEl.style.display = 'none'; }, 3500);
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
                        lista.innerHTML = '<p style="color:#666;font-size:13px;">Nenhum e-mail cadastrado.</p>';
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
                div.style.cssText = 'display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #1e1e1e;';
                div.innerHTML = '<div style="flex:1;min-width:0;">'
                    + '<strong style="font-size:13px;color:#eee;">' + data.email + '</strong>'
                    + (data.nome ? '<span style="font-size:12px;color:#888;margin-left:8px;">' + data.nome + '</span>' : '')
                    + '</div>'
                    + '<button class="btn btn--sm btn--error btnRemoverEmail" data-id="' + data.id + '" style="padding:4px 12px;font-size:12px;">Remover</button>';
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
