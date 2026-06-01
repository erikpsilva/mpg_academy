<?php
$isStudentArea = !empty($isStudentArea);
$homeUrl = BASE_URL;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$aluno = $_SESSION['aluno'] ?? null;

// Sincroniza foto e dados do aluno com o banco (pode ter sido atualizado pelo app)
if ($aluno && !empty($aluno['id'])) {
    if (!defined('ROOT')) define('ROOT', dirname(__FILE__, 3));
    require_once ROOT . '/config/database.php';
    $__pdo  = getDbConnection();
    $__stmt = $__pdo->prepare("SELECT foto, nome, email, celular FROM alunos WHERE id = ?");
    $__stmt->execute([$aluno['id']]);
    $__fresh = $__stmt->fetch(PDO::FETCH_ASSOC);
    if ($__fresh) {
        $_SESSION['aluno']['foto']    = $__fresh['foto'];
        $_SESSION['aluno']['nome']    = $__fresh['nome'];
        $_SESSION['aluno']['email']   = $__fresh['email'];
        $_SESSION['aluno']['celular'] = $__fresh['celular'];
        $aluno = $_SESSION['aluno'];
    }
}

$primeiroNome = $aluno ? explode(' ', $aluno['nome'])[0] : '';
?>
<header class="header<?= $isStudentArea ? ' header--studentArea' : '' ?>">
    <div class="container">
        <div class="header__inner">
            <a class="header__brand" href="<?= BASE_URL ?>" aria-label="MPG Academy">
                <img class="header__logo" src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">
            </a>

            <nav class="header__nav" aria-label="Navegacao principal">
                <a class="header__link header__link--active" href="<?= $homeUrl ?>">Home</a>
                <a class="header__link" href="<?= BASE_URL ?>/quemsomos">Quem Somos</a>
                <a class="header__link" href="<?= BASE_URL ?>/turmastreino">Turma e Valores</a>
            </nav>

            <?php if ($aluno) : ?>
                <div class="header__studentMenu">
                    <button class="header__student header__student--logged" type="button" aria-label="Menu do aluno" aria-expanded="false">
                        <span class="header__studentAvatar<?= !empty($aluno['foto']) ? ' header__studentAvatar--photo' : '' ?>">
                            <?php if (!empty($aluno['foto'])) : ?>
                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($aluno['foto']) ?>" alt="<?= htmlspecialchars($primeiroNome) ?>">
                            <?php else : ?>
                                <i class="icon-user" aria-hidden="true"></i>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars($primeiroNome) ?></span>
                        <i class="icon-go header__studentArrow" aria-hidden="true"></i>
                    </button>

                    <div class="header__studentDropdown" aria-hidden="true">
                        <a href="<?= BASE_URL ?>/meuperfil">
                            <i class="icon-user" aria-hidden="true"></i>
                            Meu Perfil
                        </a>
                        <a href="<?= BASE_URL ?>/areadoaluno">
                            <i class="icon-home" aria-hidden="true"></i>
                            Dashboard
                        </a>
                        <hr>
                        <a class="is-logout" href="<?= BASE_URL ?>/services/site/student_logout.php">
                            <i class="icon-go" aria-hidden="true"></i>
                            Sair
                        </a>
                    </div>
                </div>
            <?php else : ?>
                <button class="header__student" type="button" aria-label="Abrir login da area do aluno">
                    <i class="icon-areadoaluno" aria-hidden="true"></i>
                    <span>Area do Aluno</span>
                </button>
            <?php endif; ?>

            <button class="header__menuButton" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="mobileMenu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>

    <div class="headerMobileMenu" id="mobileMenu" aria-hidden="true">
        <div class="headerMobileMenu__top">
            <img class="headerMobileMenu__logo" src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">
            <button class="headerMobileMenu__close" type="button" aria-label="Fechar menu">Fechar</button>
        </div>

        <nav class="headerMobileMenu__nav" aria-label="Menu mobile">
            <a href="<?= $homeUrl ?>">Home</a>
            <a href="<?= BASE_URL ?>/quemsomos">Quem Somos</a>
            <a href="<?= BASE_URL ?>/turmastreino">Turma e Valores</a>
            <?php if ($aluno) : ?>
                <span class="headerMobileMenu__section">Area do Aluno</span>
                <a href="<?= BASE_URL ?>/areadoaluno">Dashboard</a>
                <a href="<?= BASE_URL ?>/meuperfil">Meu Perfil</a>
                <a href="<?= BASE_URL ?>/treinos">Agenda</a>
                <a href="<?= BASE_URL ?>/comunicados">Comunicados</a>
                <a href="<?= BASE_URL ?>/services/site/student_logout.php">Sair</a>
            <?php endif; ?>
        </nav>

        <div class="headerMobileMenu__social">
            <a href="https://www.instagram.com/mpgacademy/" target="_blank" rel="noopener">
                <i class="icon-instagram" aria-hidden="true"></i>
                <span>@mpg.academy</span>
            </a>
            <a href="https://wa.me/5511972330097" target="_blank" rel="noopener">
                <i class="icon-whatsapp" aria-hidden="true"></i>
                <span>Falar no WhatsApp</span>
            </a>
        </div>
    </div>

    <?php if (!$aluno) : ?>
    <div class="loginModal" id="loginModal" aria-hidden="true">
        <div class="loginModal__overlay" data-login-close></div>

        <div class="loginModal__dialog" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
            <button class="loginModal__close" type="button" aria-label="Fechar login" data-login-close>Fechar</button>

            <img class="loginModal__logo" src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">

            <h2 class="loginModal__title" id="loginModalTitle">Faça <span>login</span> na sua conta</h2>
            <p class="loginModal__subtitle">Bem-vindo de volta!</p>

            <form class="loginModal__form" id="siteLoginForm" action="<?= BASE_URL ?>/services/site/student_login.php" data-redirect="<?= BASE_URL ?>/areadoaluno" method="post">
                <div class="loginModal__field">
                    <span class="loginModal__fieldIcon" aria-hidden="true"><i class="icon-inicianteintermediario"></i></span>
                    <input type="email" name="email" id="loginModalEmail" placeholder="E-mail" autocomplete="email" required>
                </div>

                <div class="loginModal__field">
                    <span class="loginModal__fieldIcon" aria-hidden="true"><i class="icon-inicianteintermediario"></i></span>
                    <input type="password" name="senha" id="loginModalPassword" placeholder="Senha" autocomplete="current-password" required>
                    <button class="loginModal__passwordToggle" type="button" aria-label="Mostrar senha">
                        <i class="icon-ver" aria-hidden="true"></i>
                    </button>
                </div>

                <button class="loginModal__submit" type="submit">
                    Entrar
                    <i class="icon-go" aria-hidden="true"></i>
                </button>

                <p class="loginModal__message" aria-live="polite"></p>
            </form>

            <a class="loginModal__forgot" href="#">Esqueci minha senha</a>

            <div class="loginModal__divider"><span>ou</span></div>

            <div class="loginModal__whatsapp">
                <p>Ainda não faz parte da MPG Academy?</p>
                <small>Entre em contato conosco e participe das próximas turmas.</small>
                <a href="https://wa.me/5511972330097" target="_blank" rel="noopener">
                    <i class="icon-whatsapp" aria-hidden="true"></i>
                    Falar no WhatsApp
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</header>
