<?php
include ROOT . '/admin/includes/auth_check.php';
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();
$turmas = $pdo->query("SELECT id,nome,faixa_etaria,nivel FROM turmas WHERE status='ativa' ORDER BY faixa_etaria,nome")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Enviar Cadastro</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="emailCadastro">
            <div class="row alunos__header">
                <div class="col-md-8">
                    <h2>Enviar formulário de <span>Cadastro</span></h2>
                    <p>Escolha a turma e envie pelo WhatsApp um link individual já vinculado à vaga.</p>
                </div>
            </div>

            <div class="emailCadastro__grid">
                <div class="emailCadastro__card">
                    <form id="formEnviarCadastro">

                        <div class="emailCadastro__field">
                            <label for="nomeAluno">
                                Nome do aluno <span>*</span>
                            </label>
                            <input type="text" id="nomeAluno" name="nome" class="input"
                                   placeholder="Ex: João Silva" required>
                        </div>

                        <div class="emailCadastro__field"><label for="whatsappAluno">WhatsApp <span>*</span></label><input type="tel" id="whatsappAluno" name="whatsapp" class="input" inputmode="numeric" autocomplete="tel" maxlength="15" placeholder="(11) 99999-9999" required><small class="emailCadastro__hint">Digite o DDD e o número do celular.</small></div>
                        <div class="emailCadastro__field"><label for="turmaAluno">Turma de entrada <span>*</span></label><select id="turmaAluno" name="turma_id" class="input" required><option value="">Selecione a turma</option><?php foreach($turmas as $turma): ?><option value="<?= (int)$turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?> — <?= htmlspecialchars(ucfirst($turma['faixa_etaria'])) ?></option><?php endforeach; ?></select></div>

                        <div class="emailCadastro__field">
                            <label for="mensagemExtra">
                                Mensagem personalizada <small>(opcional)</small>
                            </label>
                            <textarea id="mensagemExtra" name="mensagem" class="input"
                                      placeholder="Ex: Bem-vindo à MPG Academy! Sua vaga está confirmada na turma de Sábado."
                                      rows="4"></textarea>
                        </div>

                        <div class="emailCadastro__preview">
                            <strong>Link que será enviado:</strong>
                            <span>O link individual será criado depois de selecionar a turma.</span>
                        </div>

                        <button type="submit" class="btn btn--primary emailCadastro__submit" id="btnEnviar">
                            Enviar cadastro pelo WhatsApp
                        </button>
                    </form>

                    <div id="resultMsg" class="emailCadastro__feedback"></div>
                    <div id="generatedLinkBox" class="emailCadastro__generated" hidden>
                        <label for="generatedLink">Link individual gerado</label>
                        <div class="emailCadastro__generatedRow">
                            <input type="text" id="generatedLink" readonly aria-label="Link individual de cadastro">
                            <button type="button" id="copyGeneratedLink">Copiar link</button>
                        </div>
                        <small>Pronto para colar com Ctrl+V no WhatsApp ou em outra conversa.</small>
                    </div>
                </div>

                <p class="emailCadastro__note">
                    O link é individual, expira em 7 dias e só pode ser usado uma vez. Ao concluir, o aluno entra na turma escolhida e segue para o uniforme.
                </p>
            </div>
        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

var whatsappInput = document.getElementById('whatsappAluno');
function formatarCelular(value) {
    var digits = String(value || '').replace(/\D/g, '');
    if (digits.length > 11 && digits.slice(0, 2) === '55') digits = digits.slice(2);
    digits = digits.slice(0, 11);
    if (digits.length <= 2) return digits ? '(' + digits : '';
    if (digits.length <= 7) return '(' + digits.slice(0, 2) + ') ' + digits.slice(2);
    if (digits.length <= 10) return '(' + digits.slice(0, 2) + ') ' + digits.slice(2, 6) + '-' + digits.slice(6);
    return '(' + digits.slice(0, 2) + ') ' + digits.slice(2, 7) + '-' + digits.slice(7);
}
whatsappInput.addEventListener('input', function () { this.value = formatarCelular(this.value); });
whatsappInput.addEventListener('blur', function () { this.value = formatarCelular(this.value); });

document.getElementById('formEnviarCadastro').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btnEnviar');
    var msg = document.getElementById('resultMsg');
    var nome     = document.getElementById('nomeAluno').value.trim();
    var whatsapp = document.getElementById('whatsappAluno').value.trim();
    var turmaId  = document.getElementById('turmaAluno').value;
    var mensagem = document.getElementById('mensagemExtra').value.trim();
    var linkBox   = document.getElementById('generatedLinkBox');

    btn.disabled    = true;
    btn.textContent = 'Enviando...';
    msg.className   = 'emailCadastro__feedback';
    msg.textContent = '';
    linkBox.hidden  = true;

    var fd = new FormData();
    fd.append('nome',     nome);
    fd.append('whatsapp', whatsapp);
    fd.append('turma_id', turmaId);
    fd.append('mensagem', mensagem);

    fetch(ADMIN_BASE_URL + '/services/enviar_convite_cadastro.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        msg.className          = 'emailCadastro__feedback ' + (data.success ? 'emailCadastro__feedback--success' : 'emailCadastro__feedback--error');
        msg.textContent        = data.message;
        if (data.success) {
            document.getElementById('generatedLink').value = data.link || '';
            linkBox.hidden = !data.link;
            document.getElementById('nomeAluno').value     = '';
            document.getElementById('whatsappAluno').value = '';
            document.getElementById('turmaAluno').value = '';
            document.getElementById('mensagemExtra').value = '';
        }
    })
    .catch(function () {
        msg.className        = 'emailCadastro__feedback emailCadastro__feedback--error';
        msg.textContent      = 'Erro de comunicação.';
    })
    .finally(function () {
        btn.disabled    = false;
        btn.textContent = 'Enviar cadastro pelo WhatsApp';
    });
});

document.getElementById('copyGeneratedLink').addEventListener('click', async function () {
    var btn   = this;
    var input = document.getElementById('generatedLink');
    try {
        await navigator.clipboard.writeText(input.value);
    } catch (e) {
        input.focus();
        input.select();
        document.execCommand('copy');
    }
    btn.textContent = 'Copiado!';
    setTimeout(function () { btn.textContent = 'Copiar link'; }, 1800);
});
</script>

</body>
</html>
