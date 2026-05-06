<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Escola de Vôlei</title>

<?php include ROOT . '/includes/assets.php';?>

</head>

<body>

<main class="home">
    <section class="homeHero">
        <div class="container">
            <header class="homeHeader">
                <a href="<?= BASE_URL ?>" class="homeBrand" aria-label="MPG Academy">
                    <img class="homeBrand__logo" src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy - Escola de Vôlei">
                </a>

                <div class="homeHeader__status">
                    <span>Zona Norte - São Paulo</span>
                    <i class="homeHeader__dot" aria-hidden="true"></i>
                    <span>Lançamento em breve</span>
                    <a class="homeHeader__instagram" href="https://www.instagram.com/mpgacademy/" target="_blank" rel="noopener" aria-label="Acessar Instagram da MPG Academy">
                        <img class="homeHeader__instagramIcon" src="<?= BASE_URL ?>/images/instagram.svg" alt="">
                        <span class="homeHeader__instagramText">@mpgacademy</span>
                    </a>
                </div>
            </header>

            <div class="row align-items-center homeHero__grid">
                <div class="col-lg-7 col-md-12">
                    <div class="homeHero__content">
                        <span class="homeTag">Matrículas em breve</span>

                        <h1 class="homeHero__title">
                            A nova escola de vôlei da <span>Zona Norte</span> está chegando.
                        </h1>

                        <p class="homeHero__text">
                            A MPG Academy nasce com a proposta de criar uma experiência completa para quem ama vôlei.
                            Vamos organizar turmas, horários fixos de treino, professores qualificados e uma comunidade
                            apaixonada pelo esporte.
                        </p>

                        <div class="homeHero__actions">
                            <a href="#cadastro" class="homeButton homeButton--primary">Quero participar</a>
                            <a href="#sobre" class="homeButton homeButton--outline">Conheça o projeto</a>
                        </div>

                        <div class="row homeFeatures" id="sobre">
                            <div class="col-md-4 col-sm-12">
                                <article class="homeFeature">
                                    <span class="homeFeature__icon">01</span>
                                    <h3 class="homeFeature__title">Treinos</h3>
                                    <p class="homeFeature__text">Turmas organizadas por nível, faixa etária e evolução dos alunos.</p>
                                </article>
                            </div>

                            <div class="col-md-4 col-sm-12">
                                <article class="homeFeature">
                                    <span class="homeFeature__icon">02</span>
                                    <h3 class="homeFeature__title">Professores</h3>
                                    <p class="homeFeature__text">Equipe preparada para acompanhar sua evolução dentro de quadra.</p>
                                </article>
                            </div>

                            <div class="col-md-4 col-sm-12">
                                <article class="homeFeature">
                                    <span class="homeFeature__icon">03</span>
                                    <h3 class="homeFeature__title">Zona Norte</h3>
                                    <p class="homeFeature__text">Quadras parceiras, horários fixos e treinos recorrentes em São Paulo.</p>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-md-12">
                    <aside class="homeVisual" aria-label="MPG Academy em breve">
                        <div class="homeVisual__court">
                            <div class="homeVisual__circle" aria-hidden="true"></div>
                            <div class="homeVisual__content">
                                <span class="homeVisual__ball" aria-hidden="true"></span>
                                <strong class="homeVisual__title">MPG</strong>
                                <span class="homeVisual__subtitle">Academy</span>
                                <i class="homeVisual__line" aria-hidden="true"></i>
                                <p class="homeVisual__text">Em breve novas turmas, treinos e experiências para quem quer evoluir no vôlei.</p>
                            </div>
                        </div>

                        <div class="row homeStats">
                            <div class="col-4">
                                <div class="homeStat">
                                    <strong class="homeStat__number">+100</strong>
                                    <span class="homeStat__label">Interessados</span>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="homeStat">
                                    <strong class="homeStat__number">ZN</strong>
                                    <span class="homeStat__label">São Paulo</span>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="homeStat">
                                    <strong class="homeStat__number">2026</strong>
                                    <span class="homeStat__label">Lançamento</span>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </section>

    <section class="homeSignup" id="cadastro">
        <div class="container">
            <div class="homeSignup__box">
                <div class="row align-items-center">
                    <div class="col-lg-5 col-md-12">
                        <div class="homeSignup__content">
                            <span class="homeTag">Faça parte do início da MPG</span>

                            <h2 class="homeSignup__title">Entre na lista de interesse.</h2>

                            <p class="homeSignup__text">
                                Preencha seus dados para receber novidades sobre abertura das turmas, horários disponíveis,
                                quadras parceiras e início oficial das inscrições.
                            </p>

                            <ul class="homeCheckList">
                                <li class="homeCheckList__item">Treinos organizados por nível</li>
                                <li class="homeCheckList__item">Professores qualificados</li>
                                <li class="homeCheckList__item">Quadras na Zona Norte de SP</li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-12">
                        <form class="homeForm" id="homeLeadForm" action="<?= BASE_URL ?>/services/site/register_interest.php" method="post">
                            <div class="row">
                                <div class="col-md-12">
                                    <label class="homeForm__field" for="nome">
                                        <span class="homeForm__label">Nome completo</span>
                                        <input class="homeForm__input" id="nome" name="nome" type="text" placeholder="Digite seu nome" required>
                                    </label>
                                </div>

                                <div class="col-md-6">
                                    <label class="homeForm__field" for="email">
                                        <span class="homeForm__label">E-mail</span>
                                        <input class="homeForm__input" id="email" name="email" type="email" placeholder="Digite seu e-mail" required>
                                    </label>
                                </div>

                                <div class="col-md-6">
                                    <label class="homeForm__field" for="celular">
                                        <span class="homeForm__label">Celular / WhatsApp</span>
                                        <input class="homeForm__input" id="celular" name="celular" type="tel" placeholder="(11) 99999-9999" required>
                                    </label>
                                </div>

                                <div class="col-md-12">
                                    <button class="homeForm__button" type="submit">Quero fazer parte</button>
                                    <small class="homeForm__note">
                                        Seus dados serão utilizados apenas para contato sobre a abertura da MPG Academy.
                                    </small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="homeFooter">
        <div class="container">
            <div class="homeFooter__grid">
                <p class="homeFooter__copy">© 2026 MPG Academy - Escola de Vôlei</p>
                <p class="homeFooter__links">
                    São Paulo - Zona Norte |
                    <a href="https://www.instagram.com/mpgacademy/" target="_blank" rel="noopener">Instagram - @mpgacademy</a> |
                    <a href="https://wa.me/55119972330097" target="_blank" rel="noopener">WhatsApp - 11 997233-0097</a>
                </p>
            </div>
        </div>
    </footer>
</main>

<?php include ROOT . '/includes/scripts.php';?>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/inicio/home.js?' . $version . '"></script>';
?>

</body>
</html>
