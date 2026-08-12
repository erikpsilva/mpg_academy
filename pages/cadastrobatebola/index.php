<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>Cadastro Bate Bola | MPG Academy</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="bateBolaSignup">
    <section class="bateBolaSignup__hero">
        <div class="container">
            <nav class="bateBolaSignup__breadcrumb" aria-label="Navegação estrutural">
                <a href="<?= BASE_URL ?>">Início</a><i class="icon-go" aria-hidden="true"></i>
                <a href="<?= BASE_URL ?>/batebola">Bate Bola</a><i class="icon-go" aria-hidden="true"></i>
                <span>Cadastro</span>
            </nav>
            <span class="bateBolaSignup__eyebrow">Entre para o nosso time</span>
            <h1>Cadastro <span>Bate Bola</span></h1>
        </div>
    </section>

    <section class="bateBolaSignup__content">
        <div class="container">
            <div class="bateBolaSignup__grid">
                <aside class="bateBolaSignupInfo">
                    <span class="bateBolaSignup__eyebrow">Domingo é dia de vôlei</span>
                    <h2>Faça parte dessa comunidade.</h2>
                    <p>Um cadastro simples para você viver o vôlei com respeito, diversão e união.</p>
                    <ul>
                        <li><i class="icon-calendar" aria-hidden="true"></i><span><strong>Todo domingo</strong>Das 10h às 13h</span></li>
                        <li><i class="icon-zonanorte" aria-hidden="true"></i><span><strong>Quadra Orion</strong>Zona Norte de São Paulo</span></li>
                        <li><i class="icon-comunidade" aria-hidden="true"></i><span><strong>12 a 24 pessoas</strong>Bate bola amigável e organizado</span></li>
                    </ul>
                </aside>

                <div class="bateBolaSignupCard">
                    <div class="bateBolaSignupCard__head">
                        <img src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">
                        <div><span>Novo participante</span><h2>Crie sua conta</h2><p>Preencha os dados abaixo para continuar.</p></div>
                    </div>

                    <form class="bateBolaSignupForm" id="bateBolaSignupForm" action="<?= BASE_URL ?>/services/site/register_jogador_batebola.php" data-redirect="<?= BASE_URL ?>/batebola" method="post" enctype="multipart/form-data">
                        <label class="bateBolaSignupField bateBolaSignupField--full bateBolaSignupField--photo">
                            <span>Foto do rosto <b>*</b></span>
                            <label class="bateBolaSignup__photo" for="bateBolaFoto">
                                <input type="file" name="foto" id="bateBolaFoto" accept="image/*" required>
                                <span class="bateBolaSignup__photoPreview">
                                    <i class="icon-user" aria-hidden="true"></i>
                                </span>
                                <span class="bateBolaSignup__photoAdd" aria-hidden="true">+</span>
                            </label>
                        </label>
                        <label class="bateBolaSignupField bateBolaSignupField--full">
                            <span>Nome completo <b>*</b></span>
                            <div><i class="icon-user" aria-hidden="true"></i><input type="text" name="nome" placeholder="Digite seu nome completo" autocomplete="name" required></div>
                        </label>
                        <label class="bateBolaSignupField">
                            <span>Celular <b>*</b></span>
                            <div><i class="icon-celphone" aria-hidden="true"></i><input type="tel" name="celular" placeholder="(11) 99999-9999" autocomplete="tel" required></div>
                        </label>
                        <label class="bateBolaSignupField">
                            <span>Altura (cm) <b>*</b></span>
                            <div><i class="icon-condicionamentofisico" aria-hidden="true"></i><input type="text" inputmode="numeric" name="altura_cm" placeholder="Ex: 178" required></div>
                        </label>
                        <label class="bateBolaSignupField">
                            <span>Sexo <b>*</b></span>
                            <div><i class="icon-user" aria-hidden="true"></i>
                                <select name="sexo" required>
                                    <option value="">Selecione</option>
                                    <option value="masculino">Masculino</option>
                                    <option value="feminino">Feminino</option>
                                </select>
                            </div>
                        </label>
                        <label class="bateBolaSignupField">
                            <span>E-mail <b>*</b></span>
                            <div><i class="icon-mail" aria-hidden="true"></i><input type="email" name="email" placeholder="seuemail@exemplo.com" autocomplete="email" required></div>
                        </label>
                        <label class="bateBolaSignupField">
                            <span>Senha <b>*</b></span>
                            <div><i class="icon-areadoaluno" aria-hidden="true"></i><input type="password" name="senha" placeholder="Crie uma senha" autocomplete="new-password" required></div>
                        </label>
                        <label class="bateBolaSignupField">
                            <span>Confirmar senha <b>*</b></span>
                            <div><i class="icon-check" aria-hidden="true"></i><input type="password" name="confirmar_senha" placeholder="Digite a senha novamente" autocomplete="new-password" required></div>
                        </label>
                        <button class="bateBolaSignupForm__submit" type="submit">Criar minha conta <i class="icon-go" aria-hidden="true"></i></button>
                        <small class="bateBolaSignupForm__status" id="bateBolaSignupStatus"></small>
                    </form>

                    <p class="bateBolaSignupCard__login">Já possui cadastro? <a href="<?= BASE_URL ?>/batebola">Voltar e fazer login</a></p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
<script src="<?= BASE_URL ?>/pages/cadastrobatebola/cadastrobatebola.js?v=<?= time() ?>"></script>
</body>
</html>
