<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Escola de Volei Adulto</title>

<?php include ROOT . '/includes/assets.php';?>

</head>

<body>

<?php include ROOT . '/includes/header/header.php';?>

<main class="home">
    <section class="homeHero" id="home">
        <div class="container">
            <div class="homeHero__content">
                <h1 class="homeHero__title">
                    <span>Volei adulto.</span>
                    Evolução todos os dias.
                </h1>

                <p class="homeHero__text">
                    Treinos para adultos 18+ de todos os níveis: do primeiro contato
                    com o volei até quem já joga e quer evoluir com consistência.
                </p>

                <div class="homeHero__actions">
                    <a class="homeButton homeButton--primary" href="<?= BASE_URL ?>/turmastreino">
                        Ver turmas
                        <i class="icon-go" aria-hidden="true"></i>
                    </a>
                    <a class="homeButton homeButton--outline" href="https://wa.me/5511972330097" target="_blank" rel="noopener">
                        <i class="icon-whatsapp" aria-hidden="true"></i>
                        Falar no WhatsApp
                    </a>
                </div>

                <ul class="homeHighlights" aria-label="Destaques MPG Academy">
                    <li><i class="icon-adultos18" aria-hidden="true"></i> Adultos 18+</li>
                    <li><i class="icon-evolucaotecnica" aria-hidden="true"></i> Evolução técnica</li>
                    <li><i class="icon-inicianteintermediario" aria-hidden="true"></i> Do iniciante ao avançado</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="homeBenefits" aria-label="Benefícios">
        <div class="container">
            <div class="homeCards">
                <article class="homeCard">
                    <i class="icon-treinosfocados" aria-hidden="true"></i>
                    <h3>Treinos Focados</h3>
                    <p>Metodologia que desenvolve técnica, tática, físico e mental.</p>
                </article>

                <article class="homeCard">
                    <i class="icon-comunidade" aria-hidden="true"></i>
                    <h3>Comunidade</h3>
                    <p>Ambiente saudável, leve e acolhedor para evoluir no seu ritmo.</p>
                </article>

                <article class="homeCard">
                    <i class="icon-inicianteintermediario" aria-hidden="true"></i>
                    <h3>Todos os Níveis</h3>
                    <p>Turmas para iniciantes, intermediários e avançados.</p>
                </article>

                <article class="homeCard">
                    <i class="icon-flexibilidade" aria-hidden="true"></i>
                    <h3>Flexibilidade</h3>
                    <p>Horários estratégicos para se encaixar na sua rotina.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="homePlansBanner" aria-label="Turmas e valores">
        <div class="container">
            <a class="homePlansBanner__box" href="<?= BASE_URL ?>/turmastreino">
                <img src="<?= BASE_URL ?>/images/turmasvalores/bannerTopo.png" alt="Turmas de volei MPG Academy">
                <div>
                    <span class="homeEyebrow">Turmas e Valores</span>
                    <h2>Encontre a turma ideal para você.</h2>
                    <p>Veja horários, níveis, valores e promoções disponíveis para começar seus treinos na MPG Academy.</p>
                </div>
                <strong>
                    Ver turmas e valores
                    <i class="icon-go" aria-hidden="true"></i>
                </strong>
            </a>
        </div>
    </section>

    <section class="homeAbout" id="sobre">
        <div class="container">
            <div class="homeAbout__grid">
                <div class="homeAbout__content">
                    <span class="homeEyebrow">Sobre Nós</span>
                    <h2>Mais que uma escola, <span>uma experiência.</span></h2>
                    <p>A MPG Academy nasceu da paixão pelo volei e do desejo de criar um ambiente onde adultos possam aprender, evoluir e construir amizades através do esporte.</p>
                    <p>Aqui você encontra estrutura, metodologia e um time de profissionais dedicados para acompanhar o seu nível, seja você iniciante, intermediário ou avançado.</p>
                    <a class="homeButton homeButton--small homeButton--outline" href="#treinos">
                        Conhecer nossa história
                        <i class="icon-go" aria-hidden="true"></i>
                    </a>
                </div>

                <figure class="homeAbout__image">
                    <img src="<?= BASE_URL ?>/images/home/imgMaisQueUmaEscola.png" alt="Equipe MPG Academy reunida em quadra">
                </figure>
            </div>
        </div>
    </section>

    <!--
    <section class="homeNumbers" aria-label="Números da MPG Academy">
        <div class="container">
            <div class="homeNumbers__bar">
                <article>
                    <i class="icon-alunos" aria-hidden="true"></i>
                    <strong>+200</strong>
                    <span>Alunos</span>
                </article>
                <article>
                    <i class="icon-timesativossvg" aria-hidden="true"></i>
                    <strong>15+</strong>
                    <span>Turmas ativas</span>
                </article>
                <article>
                    <i class="icon-diasdetreino" aria-hidden="true"></i>
                    <strong>6</strong>
                    <span>Dias de treino</span>
                </article>
                <article>
                    <i class="icon-inicianteintermediario" aria-hidden="true"></i>
                    <strong>3</strong>
                    <span>Níveis de treino</span>
                </article>
                <article>
                    <i class="icon-zonanorte" aria-hidden="true"></i>
                    <strong>Zona Norte</strong>
                    <span>São Paulo - SP</span>
                </article>
            </div>
        </div>
    </section>
    -->

    <section class="homeTraining" id="treinos">
        <div class="container">
            <div class="homeSectionTitle">
                <span class="homeEyebrow">Nossos Treinos</span>
                <h2>Do iniciante ao avançado</h2>
            </div>

            <div class="homeCards homeCards--training">
                <article class="homeCard">
                    <i class="icon-inicianteintermediario" aria-hidden="true"></i>
                    <h3>Iniciante / Intermediário</h3>
                    <p>Para quem quer aprender, se desenvolver e ganhar confiança.</p>
                    <a href="#contato">Saiba mais <i class="icon-go" aria-hidden="true"></i></a>
                </article>

                <article class="homeCard">
                    <i class="icon-intermediarioavancado" aria-hidden="true"></i>
                    <h3>Intermediário / Avançado</h3>
                    <p>Treinos intensos para elevar seu nível técnico e tático.</p>
                    <a href="#contato">Saiba mais <i class="icon-go" aria-hidden="true"></i></a>
                </article>

                <article class="homeCard">
                    <i class="icon-comunidade" aria-hidden="true"></i>
                    <h3>Turmas por Nível</h3>
                    <p>Treinos organizados para você evoluir com pessoas no mesmo momento de jogo.</p>
                    <a href="#contato">Saiba mais <i class="icon-go" aria-hidden="true"></i></a>
                </article>

                <article class="homeCard">
                    <i class="icon-condicionamentofisico" aria-hidden="true"></i>
                    <h3>Condicionamento Físico</h3>
                    <p>Treinos físicos específicos para melhorar sua performance.</p>
                    <a href="#contato">Saiba mais <i class="icon-go" aria-hidden="true"></i></a>
                </article>
            </div>

            <aside class="homeCta" id="planos">
                <img src="<?= BASE_URL ?>/images/home/imgProntoPraFazerParteDaMpgAcademy.png" alt="Bola de volei MPG Academy">
                <div>
                    <h2>Pronto para fazer parte <span>da MPG Academy?</span></h2>
                    <p>Venha treinar, evoluir e viver o volei com a gente!</p>
                </div>
                <div class="homeCta__actions">
                    <a class="homeButton homeButton--primary" href="https://wa.me/5511972330097" target="_blank" rel="noopener">
                        <i class="icon-whatsapp" aria-hidden="true"></i>
                        Falar no WhatsApp
                    </a>
                    <a href="#treinos">Ver planos e horários <i class="icon-go" aria-hidden="true"></i></a>
                </div>
            </aside>
        </div>
    </section>
</main>

<?php include ROOT . '/includes/footer/footer.php';?>
<?php include ROOT . '/includes/scripts.php';?>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/inicio/home.js?' . $version . '"></script>';
?>

</body>
</html>
