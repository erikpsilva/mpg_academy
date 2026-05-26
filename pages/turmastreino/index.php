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

<?php include ROOT . '/includes/footer/footer.php';?>

<?php include ROOT . '/includes/scripts.php';?>

<script>var BASE_URL = "<?= BASE_URL ?>";</script>
<?php $v = time(); echo '<script src="' . BASE_URL . '/pages/turmastreino/turmastreino.js?v=' . $v . '"></script>'; ?>

</body>
</html>
