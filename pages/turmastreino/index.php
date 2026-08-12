<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Turmas e Valores</title>

<?php include ROOT . '/includes/assets.php';?>

</head>

<body>

<?php include ROOT . '/includes/header/header.php';?>

<main class="turmasValores">
    <section class="turmasValoresHero">
        <div class="container">
            <div class="turmasValoresHero__grid">
                <div class="turmasValoresHero__content">
                    <nav class="turmasValoresBreadcrumb" aria-label="breadcrumb">
                        <a href="<?= BASE_URL ?>">Início</a>
                        <i class="icon-go" aria-hidden="true"></i>
                        <span>Turmas e Valores</span>
                    </nav>

                    <h1>Turmas e <span>Valores</span></h1>
                    <p>Escolha a turma ideal para você e venha fazer parte da MPG Academy.</p>

                    <div class="turmasValoresHero__features" aria-label="Diferenciais">
                        <span><i class="icon-planejamento" aria-hidden="true"></i> Treinos planejados por profissionais</span>
                        <span><i class="icon-estrutura" aria-hidden="true"></i> Estrutura completa e de alto nível</span>
                        <span><i class="icon-seguro" aria-hidden="true"></i> Turmas por nível e faixa etária</span>
                    </div>
                </div>

                <figure class="turmasValoresHero__image">
                    <img src="<?= BASE_URL ?>/images/turmasvalores/bannerTopo.png" alt="Treino de volei MPG Academy">
                </figure>
            </div>
        </div>
    </section>

    <section class="turmasValoresList">
        <div class="container">
            <div class="turmasValoresFilters" aria-label="Filtros de turma">
                <button class="is-active" type="button" data-nivel="todos"><i class="icon-timesativossvg" aria-hidden="true"></i> Todas as turmas</button>
                <button type="button" data-nivel="iniciante"><i class="icon-inicianteintermediario" aria-hidden="true"></i> Iniciante</button>
                <button type="button" data-nivel="intermediario"><i class="icon-intermediarioavancado" aria-hidden="true"></i> Intermediário</button>
                <button type="button" data-nivel="avancado"><i class="icon-competicao" aria-hidden="true"></i> Avançado</button>
            </div>

            <div class="turmasValoresCards" id="turmasValoresCards"></div>
        </div>
    </section>

    <section class="homeUniforms" aria-labelledby="turmasUniformsTitle">
        <div class="container">
            <div class="homeSectionTitle homeUniforms__title">
                <span class="homeEyebrow">Nossa identidade em quadra</span>
                <h2 id="turmasUniformsTitle">Uniformes MPG Academy</h2>
                <p>Conheça os modelos oficiais que representam a nossa energia dentro e fora das quadras.</p>
            </div>

            <div class="homeUniforms__groups">
                <article class="homeUniformGroup">
                    <header><span>01</span><h3>Masculino</h3></header>
                    <div class="homeUniformGroup__images">
                        <button type="button" class="homeUniformGroup__preview js-home-uniform-preview" data-image="<?= BASE_URL ?>/images/uniformes/uniformeMasculinoPadrao.jpg" data-title="Uniforme masculino padrão">
                            <img src="<?= BASE_URL ?>/images/uniformes/uniformeMasculinoPadrao.jpg" alt="Uniforme masculino padrão MPG Academy" loading="lazy">
                            <span>Padrão</span>
                        </button>
                        <button type="button" class="homeUniformGroup__preview js-home-uniform-preview" data-image="<?= BASE_URL ?>/images/uniformes/uniformeMasculinoLibero.jpg" data-title="Uniforme masculino de líbero">
                            <img src="<?= BASE_URL ?>/images/uniformes/uniformeMasculinoLibero.jpg" alt="Uniforme masculino de líbero MPG Academy" loading="lazy">
                            <span>Líbero</span>
                        </button>
                    </div>
                </article>

                <article class="homeUniformGroup">
                    <header><span>02</span><h3>Feminino</h3></header>
                    <div class="homeUniformGroup__images">
                        <button type="button" class="homeUniformGroup__preview js-home-uniform-preview" data-image="<?= BASE_URL ?>/images/uniformes/uniformeFemininoPadrao.jpg" data-title="Uniforme feminino padrão">
                            <img src="<?= BASE_URL ?>/images/uniformes/uniformeFemininoPadrao.jpg" alt="Uniforme feminino padrão MPG Academy" loading="lazy">
                            <span>Padrão</span>
                        </button>
                        <button type="button" class="homeUniformGroup__preview js-home-uniform-preview" data-image="<?= BASE_URL ?>/images/uniformes/uniformeFemininoLibero.jpg" data-title="Uniforme feminino de líbero">
                            <img src="<?= BASE_URL ?>/images/uniformes/uniformeFemininoLibero.jpg" alt="Uniforme feminino de líbero MPG Academy" loading="lazy">
                            <span>Líbero</span>
                        </button>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="turmasValoresContact">
        <div class="container">
            <div class="turmasValoresContact__inner">
                <i class="icon-whatsapp" aria-hidden="true"></i>
                <div>
                    <h2>Fale com nossa equipe e agende sua aula <span>experimental!</span></h2>
                    <p>Tire suas dúvidas e venha conhecer nossa estrutura.</p>
                </div>
                <ul>
                    <li><i class="icon-calendar" aria-hidden="true"></i> Aula experimental gratuita</li>
                    <li><i class="icon-user" aria-hidden="true"></i> Acompanhamento personalizado</li>
                    <li><i class="icon-seguro" aria-hidden="true"></i> Ambiente seguro e motivador</li>
                </ul>
                <a href="https://wa.me/5511972330097" target="_blank" rel="noopener">
                    <i class="icon-whatsapp" aria-hidden="true"></i>
                    Falar no WhatsApp
                    <i class="icon-go" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>
</main>

<div class="homeUniformModal" id="homeUniformModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="homeUniformModalTitle">
    <button type="button" class="homeUniformModal__backdrop js-home-uniform-close" aria-label="Fechar visualização"></button>
    <div class="homeUniformModal__content">
        <button type="button" class="homeUniformModal__close js-home-uniform-close" aria-label="Fechar">&times;</button>
        <h2 id="homeUniformModalTitle"></h2>
        <img id="homeUniformModalImage" src="" alt="">
    </div>
</div>

<?php include ROOT . '/includes/footer/footer.php';?>

<?php include ROOT . '/includes/scripts.php';?>

<script>var BASE_URL = "<?= BASE_URL ?>";</script>
<?php $v = time(); echo '<script src="' . BASE_URL . '/pages/turmastreino/turmastreino.js?v=' . $v . '"></script>'; ?>

</body>
</html>
