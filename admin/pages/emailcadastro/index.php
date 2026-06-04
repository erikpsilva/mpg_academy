<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Enviar Email de Cadastro</title>
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
                    <h2>Enviar Email de <span>Cadastro</span></h2>
                    <p>Envie o link de cadastro da plataforma para o e-mail do aluno.</p>
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

                        <div class="emailCadastro__field">
                            <label for="emailAluno">
                                E-mail do aluno <span>*</span>
                            </label>
                            <input type="email" id="emailAluno" name="email" class="input"
                                   placeholder="aluno@exemplo.com" required>
                        </div>

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
                            <span><?= BASE_URL ?>/cadastro</span>
                        </div>

                        <button type="submit" class="btn btn--primary emailCadastro__submit" id="btnEnviar">
                            Enviar e-mail de cadastro
                        </button>
                    </form>

                    <div id="resultMsg" class="emailCadastro__feedback"></div>
                </div>

                <p class="emailCadastro__note">
                    O aluno receberá um e-mail com o link para criar sua conta na plataforma MPG Academy.
                </p>
            </div>
        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

document.getElementById('formEnviarCadastro').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btnEnviar');
    var msg = document.getElementById('resultMsg');
    var nome     = document.getElementById('nomeAluno').value.trim();
    var email    = document.getElementById('emailAluno').value.trim();
    var mensagem = document.getElementById('mensagemExtra').value.trim();

    btn.disabled    = true;
    btn.textContent = 'Enviando...';
    msg.className   = 'emailCadastro__feedback';
    msg.textContent = '';

    var fd = new FormData();
    fd.append('nome',     nome);
    fd.append('email',    email);
    fd.append('mensagem', mensagem);

    fetch(ADMIN_BASE_URL + '/services/enviar_email_cadastro.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        msg.className          = 'emailCadastro__feedback ' + (data.success ? 'emailCadastro__feedback--success' : 'emailCadastro__feedback--error');
        msg.textContent        = data.message;
        if (data.success) {
            document.getElementById('nomeAluno').value     = '';
            document.getElementById('emailAluno').value    = '';
            document.getElementById('mensagemExtra').value = '';
        }
    })
    .catch(function () {
        msg.className        = 'emailCadastro__feedback emailCadastro__feedback--error';
        msg.textContent      = 'Erro de comunicação.';
    })
    .finally(function () {
        btn.disabled    = false;
        btn.textContent = 'Enviar e-mail de cadastro';
    });
});
</script>

</body>
</html>
