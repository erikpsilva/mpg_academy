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

        <section class="alunos">
            <div class="row alunos__header">
                <div class="col-md-8">
                    <h2>Enviar Email de <span>Cadastro</span></h2>
                    <p>Envie o link de cadastro da plataforma para o e-mail do aluno.</p>
                </div>
            </div>

            <div style="max-width:560px;margin-top:28px;">

                <!-- Formulário -->
                <div class="alunos__detalheCard" style="padding:28px 32px;">
                    <form id="formEnviarCadastro">

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">
                                Nome do aluno <span style="color:#e53535;">*</span>
                            </label>
                            <input type="text" id="nomeAluno" name="nome" class="input"
                                   placeholder="Ex: João Silva" required style="width:100%;">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">
                                E-mail do aluno <span style="color:#e53535;">*</span>
                            </label>
                            <input type="email" id="emailAluno" name="email" class="input"
                                   placeholder="aluno@exemplo.com" required style="width:100%;">
                        </div>

                        <div style="margin-bottom:24px;">
                            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">
                                Mensagem personalizada <small style="text-transform:none;font-size:11px;">(opcional)</small>
                            </label>
                            <textarea id="mensagemExtra" name="mensagem" class="input"
                                      placeholder="Ex: Bem-vindo à MPG Academy! Sua vaga está confirmada na turma de Sábado."
                                      rows="3" style="width:100%;resize:vertical;"></textarea>
                        </div>

                        <div style="background:rgba(229,194,0,.07);border:1px solid rgba(229,194,0,.25);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#ccc;">
                            <strong style="color:#e5c200;">Link que será enviado:</strong><br>
                            <span style="color:#888;font-size:12px;word-break:break-all;"><?= BASE_URL ?>/cadastro</span>
                        </div>

                        <button type="submit" class="btn btn--primary" id="btnEnviar" style="width:100%;">
                            Enviar e-mail de cadastro
                        </button>
                    </form>

                    <div id="resultMsg" style="display:none;margin-top:16px;padding:12px 16px;border-radius:8px;font-size:14px;"></div>
                </div>

                <!-- Histórico de envios recentes (opcional futuro) -->
                <p style="color:#555;font-size:12px;margin-top:16px;text-align:center;">
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
    msg.style.display = 'none';

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
        msg.style.display      = '';
        msg.style.background   = data.success ? 'rgba(46,182,16,0.12)' : 'rgba(255,45,45,0.12)';
        msg.style.border       = '1px solid ' + (data.success ? '#79ff45' : '#ff5a5a');
        msg.style.color        = data.success ? '#79ff45' : '#ff5a5a';
        msg.textContent        = data.message;
        if (data.success) {
            document.getElementById('nomeAluno').value     = '';
            document.getElementById('emailAluno').value    = '';
            document.getElementById('mensagemExtra').value = '';
        }
    })
    .catch(function () {
        msg.style.display    = '';
        msg.style.background = 'rgba(255,45,45,0.12)';
        msg.style.border     = '1px solid #ff5a5a';
        msg.style.color      = '#ff5a5a';
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
