<?php
// Necessário pro aviso da janela de inscrição (includes/batebola_janela.php).
require_once ROOT . '/config/batebola.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>Bate Bola MPG Academy</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="bateBola">
    <section class="bateBolaHero">
        <div class="container">
            <div class="bateBolaHero__grid">
                <div class="bateBolaHero__content">
                    <nav class="bateBolaBreadcrumb" aria-label="Navegação estrutural">
                        <a href="<?= BASE_URL ?>">Início</a><i class="icon-go" aria-hidden="true"></i><span>Bate Bola</span>
                    </nav>
                    <span class="bateBolaEyebrow">Domingo é dia de vôlei</span>
                    <h1>Seu domingo começa <span>em quadra.</span></h1>
                    <p class="bateBolaHero__lead">O Bate Bola da MPG Academy é um encontro para jogar, encontrar a galera e curtir o vôlei em um ambiente leve, organizado e acolhedor.</p>

                    <?php include ROOT . '/includes/batebola_janela.php'; ?>

                    <div class="bateBolaHero__actions">
                        <?php if (!empty($_SESSION['jogador'])): ?>
                        <a class="bateBolaButton bateBolaButton--primary" href="<?= BASE_URL ?>/batebolainicio">Acessar minha conta <i class="icon-go" aria-hidden="true"></i></a>
                        <?php else: ?>
                        <button class="bateBolaButton bateBolaButton--primary" type="button" data-batebola-login-open>Acessar minha conta <i class="icon-go" aria-hidden="true"></i></button>
                        <?php endif; ?>
                        <a class="bateBolaButton bateBolaButton--outline" href="https://wa.me/5511972330097" target="_blank" rel="noopener"><i class="icon-whatsapp" aria-hidden="true"></i> Tirar uma dúvida</a>
                    </div>
                </div>

                <aside class="bateBolaSchedule" aria-label="Informações do Bate Bola">
                    <span class="bateBolaSchedule__tag">Toda semana</span>
                    <h2>Bate Bola MPG</h2>
                    <dl>
                        <div><dt><i class="icon-calendar" aria-hidden="true"></i> Quando</dt><dd>Domingos</dd></div>
                        <div><dt><i class="icon-timescompetitivos" aria-hidden="true"></i> Horário</dt><dd>Das 10h às 13h</dd></div>
                        <div>
                            <dt><i class="icon-zonanorte" aria-hidden="true"></i> Local</dt>
                            <dd><?= BATEBOLA_LOCAL_NOME ?><small><?= BATEBOLA_LOCAL_ENDERECO ?></small></dd>
                        </div>
                    </dl>
                    <p>3 horas para jogar, se divertir e viver o vôlei com a comunidade MPG.</p>
                </aside>
            </div>
        </div>
    </section>

    <section class="bateBolaNumbers" aria-label="Como funciona">
        <div class="container"><div class="bateBolaNumbers__grid">
            <article><strong>12</strong><span>mínimo de jogadores</span></article>
            <article><strong>24</strong><span>máximo de jogadores</span></article>
            <article><strong>3h</strong><span>de quadra aos domingos</span></article>
            <article class="bateBolaNumbers__price"><strong>Valor dividido</strong><span>O valor de cada encontro varia conforme o número de participantes presentes.</span></article>
        </div></div>
    </section>

    <section class="bateBolaAbout">
        <div class="container">
            <div class="bateBolaSectionTitle">
                <span class="bateBolaEyebrow">Vôlei do jeito que a gente gosta</span>
                <h2>Jogo bom é jogo com respeito.</h2>
                <p>O Bate Bola é amigável e feito para todo mundo aproveitar. Competitividade saudável é bem-vinda, mas respeito, parceria e união vêm sempre em primeiro lugar.</p>
            </div>
            <div class="bateBolaValues">
                <article><i class="icon-comunidade" aria-hidden="true"></i><h3>Respeito em quadra</h3><p>Valorize cada jogador, evite discussões e ajude a manter um clima positivo do início ao fim.</p></article>
                <article><i class="icon-timesativossvg" aria-hidden="true"></i><h3>União do grupo</h3><p>Organize, incentive e celebre as boas jogadas. Aqui todo mundo faz parte do time.</p></article>
                <article><i class="icon-evolucaotecnica" aria-hidden="true"></i><h3>Diversão e evolução</h3><p>Jogue com vontade, experimente, aprenda e aproveite cada rally sem perder a leveza.</p></article>
            </div>
        </div>
    </section>

    <section class="bateBolaPrepare">
        <div class="container"><div class="bateBolaPrepare__grid">
            <div><span class="bateBolaEyebrow">Antes de sair de casa</span><h2>Prepare sua mochila.</h2><p>Alguns cuidados simples deixam suas três horas de jogo mais confortáveis e seguras.</p></div>
            <ul>
                <li><i class="icon-check" aria-hidden="true"></i><span><strong>Água</strong> para se manter hidratado durante todo o encontro.</span></li>
                <li><i class="icon-check" aria-hidden="true"></i><span><strong>Joelheira</strong> para mais proteção e confiança nas defesas.</span></li>
                <li><i class="icon-check" aria-hidden="true"></i><span><strong>Roupa confortável</strong> e tênis adequado para prática esportiva.</span></li>
                <li><i class="icon-check" aria-hidden="true"></i><span><strong>Boa energia</strong> para apoiar o grupo e fazer um grande domingo.</span></li>
            </ul>
        </div></div>
    </section>

</main>

<div class="bateBolaModal" id="bateBolaLoginModal" aria-hidden="true">
    <div class="bateBolaModal__overlay" data-batebola-login-close></div>
    <div class="bateBolaModal__dialog" role="dialog" aria-modal="true" aria-labelledby="bateBolaLoginTitle">
        <button class="bateBolaModal__close" type="button" aria-label="Fechar login" data-batebola-login-close>Fechar</button>
        <div class="bateBolaLogin">
            <img src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">
            <h2 id="bateBolaLoginTitle">Faça <span>login</span> na sua conta</h2>
            <p>Bem-vindo de volta ao Bate Bola!</p>
            <form action="<?= BASE_URL ?>/services/site/jogador_login.php" method="post" class="bateBolaLogin__form" id="bateBolaLoginForm" data-redirect="<?= BASE_URL ?>/batebolainicio">
                <div class="bateBolaLogin__field">
                    <i class="icon-user" aria-hidden="true"></i>
                    <input type="email" id="bateBolaEmail" name="email" placeholder="E-mail" autocomplete="email" aria-label="E-mail" required>
                </div>
                <div class="bateBolaLogin__field">
                    <i class="icon-areadoaluno" aria-hidden="true"></i>
                    <input type="password" id="bateBolaPassword" name="senha" placeholder="Senha" autocomplete="current-password" aria-label="Senha" required>
                    <button class="bateBolaLogin__passwordToggle" type="button" aria-label="Mostrar senha"><i class="icon-ver" aria-hidden="true"></i></button>
                </div>
                <p class="bateBolaLogin__message" aria-live="polite"></p>
                <button class="bateBolaLogin__submit" type="submit">Entrar <i class="icon-go" aria-hidden="true"></i></button>
            </form>
            <a class="bateBolaLogin__forgot" href="#">Esqueci minha senha</a>
            <div class="bateBolaLogin__divider"><span>ou</span></div>
            <div class="bateBolaLogin__whatsapp">
                <p>Ainda não participa do Bate Bola MPG?</p>
                <small>Fale com a gente para saber como participar dos próximos encontros.</small>
                <a href="<?= BASE_URL ?>/cadastrobatebola"><i class="icon-user" aria-hidden="true"></i> Fazer meu cadastro</a>
            </div>
        </div>
    </div>
</div>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
<script src="<?= BASE_URL ?>/pages/batebola/batebola.js?v=<?= time() ?>"></script>
</body>
</html>
