<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Login</title>

<?php include ROOT . '/admin/includes/assets.php';?>

</head>

<body>

<section class="adminLogin">
    <div class="adminLogin__brandPanel" aria-hidden="true">
        <img src="<?= ADMIN_BASE_URL ?>/images/logo.png" alt="">
        <strong>MPG Academy</strong>
        <span>Admin</span>
    </div>

    <div class="adminLogin__content">
        <form class="adminLogin__card formGroup" autocomplete="on">
            <header class="adminLogin__head">
                <img class="adminLogin__logo" src="<?= ADMIN_BASE_URL ?>/images/logo.png" alt="MPG Academy">
                <span>Painel administrativo</span>
                <h1>Area de acesso</h1>
                <p>Entre com seus dados para gerenciar alunos, comunicados e cadastros.</p>
            </header>

            <div class="adminLogin__fields">
                <div class="formGroup__item">
                    <label for="loginEmail">E-mail</label>
                    <input class="input" type="email" name="loginEmail" id="loginEmail" placeholder="Digite seu e-mail" autocomplete="email">
                    <span class="errorText">Digite um e-mail valido</span>
                </div>

                <div class="formGroup__item">
                    <label for="loginPassword">Senha</label>
                    <input class="input" type="password" name="loginPassword" id="loginPassword" placeholder="Digite sua senha" autocomplete="current-password">
                    <span class="errorText">A senha deve ter ao menos 6 caracteres</span>
                </div>
            </div>

            <button class="btn btn--primary adminLogin__submit" id="enviarLogin" type="submit">Entrar</button>

            <footer class="adminLogin__footer">
                <a href="<?= BASE_URL ?>">Voltar ao site</a>
                <small>MPG Academy</small>
            </footer>
        </form>
    </div>
</section>

<?php include ROOT . '/admin/includes/scripts.php';?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL = "<?= BASE_URL ?>";
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/login/login.js?v' . $version . '"></script>';
?>

</body>
</html>
