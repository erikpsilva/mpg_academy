<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Comunicados</title>

<?php include ROOT . '/includes/assets.php';?>

</head>

<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php';?>

<main class="studentArea studentAnnouncements">
    <div class="studentArea__layout">
        <aside class="studentAreaSidebar">
            <nav class="studentAreaSidebar__nav" aria-label="Menu do aluno">
                <a href="<?= BASE_URL ?>/areadoaluno"><i class="icon-home"></i> Dashboard</a>

                <strong>Geral</strong>
                <a href="<?= BASE_URL ?>/meuperfil"><i class="icon-user"></i> Meu Perfil</a>
                <a href="<?= BASE_URL ?>/mensalidades"><i class="icon-creditcard"></i> Mensalidades</a>
                <a href="<?= BASE_URL ?>/treinos"><i class="icon-calendar"></i> Agenda</a>
                <a href="<?= BASE_URL ?>/comunicados" class="is-active"><i class="icon-megaphone"></i> Comunicados</a>

                <strong>Extras</strong>
                <a href="#indique"><i class="icon-comunidade"></i> Indique um amigo</a>
            </nav>

            <div class="studentAreaSidebar__help">
                <h3>Precisa de ajuda?</h3>
                <p>Fale com nossa equipe pelo WhatsApp.</p>
                <a href="https://wa.me/5511972330097" target="_blank" rel="noopener">
                    <i class="icon-whatsapp"></i>
                    Falar no WhatsApp
                </a>
            </div>
        </aside>

        <section class="studentAreaContent">
            <nav class="studentMonthlyBreadcrumb" aria-label="breadcrumb">
                <a href="<?= BASE_URL ?>/areadoaluno">Dashboard</a>
                <i class="icon-go" aria-hidden="true"></i>
                <span>Comunicados</span>
            </nav>

            <section class="studentAnnouncementsHero">
                <span><i class="icon-megaphone" aria-hidden="true"></i></span>
                <div>
                    <h1>Comunicados</h1>
                    <p>Fique por dentro das novidades, avisos e informacoes importantes da MPG Academy.</p>
                </div>
            </section>

            <div class="studentAnnouncementsTools">
                <label>
                    <input type="search" placeholder="Buscar comunicado...">
                    <i class="icon-search" aria-hidden="true"></i>
                </label>
                <select aria-label="Ordenar comunicados">
                    <option>Mais recentes</option>
                    <option>Mais antigos</option>
                    <option>Destaques</option>
                </select>
            </div>

            <section class="studentAnnouncementsList">
                <article class="studentAnnouncementCard is-featured">
                    <img src="<?= BASE_URL ?>/images/areadoaluno/imgNoticiaExemplo.png" alt="Avaliacoes fisicas MPG Academy">
                    <div>
                        <strong><i class="icon-check"></i> Destaque</strong>
                        <h2>Avaliacoes fisicas do 2º semestre</h2>
                        <p>As avaliacoes fisicas do 2º semestre ja tem data marcada! Fiquem atentos ao cronograma da sua turma e nao faltem.</p>
                        <footer>
                            <span><i class="icon-calendar"></i> 22/05/2024</span>
                            <b class="is-purple">Avaliacoes</b>
                            <button type="button" class="studentAnnouncementOpen" data-title="Avaliacoes fisicas do 2º semestre" data-date="22/05/2024" data-tag="Avaliacoes" data-image="<?= BASE_URL ?>/images/areadoaluno/imgNoticiaExemplo.png" data-text="As avaliacoes fisicas do 2º semestre ja tem data marcada. Todos os alunos devem acompanhar o cronograma da sua turma e comparecer no horario combinado. A avaliacao ajuda nossa equipe a acompanhar evolucao, condicionamento e pontos de melhoria para os proximos treinos.">Ver comunicado completo <i class="icon-go"></i></button>
                        </footer>
                    </div>
                </article>

                <article class="studentAnnouncementCard">
                    <img src="<?= BASE_URL ?>/images/home/imgMaisQueUmaEscola.png" alt="Quadra MPG Academy">
                    <div>
                        <h2>Recesso de Ferias - Julho</h2>
                        <p>Informamos que nao havera treinos entre os dias 15/07 e 21/07 devido ao recesso de ferias. Retornaremos normalmente no dia 22/07.</p>
                        <footer>
                            <span><i class="icon-calendar"></i> 20/05/2024</span>
                            <b class="is-blue">Avisos</b>
                            <button type="button" class="studentAnnouncementOpen" data-title="Recesso de Ferias - Julho" data-date="20/05/2024" data-tag="Avisos" data-image="<?= BASE_URL ?>/images/home/imgMaisQueUmaEscola.png" data-text="Informamos que nao havera treinos entre os dias 15/07 e 21/07 devido ao recesso de ferias. As atividades retornam normalmente no dia 22/07, seguindo os mesmos horarios e turmas.">Ver comunicado completo <i class="icon-go"></i></button>
                        </footer>
                    </div>
                </article>

                <article class="studentAnnouncementCard">
                    <img src="<?= BASE_URL ?>/images/home/imgProntoPraFazerParteDaMpgAcademy.png" alt="Uniforme MPG Academy">
                    <div>
                        <h2>Novo uniforme MPG Academy</h2>
                        <p>Ja estao disponiveis os novos uniformes oficiais da MPG Academy! Procure seu treinador para mais informacoes sobre valores e tamanhos.</p>
                        <footer>
                            <span><i class="icon-calendar"></i> 18/05/2024</span>
                            <b class="is-green">Uniformes</b>
                            <button type="button" class="studentAnnouncementOpen" data-title="Novo uniforme MPG Academy" data-date="18/05/2024" data-tag="Uniformes" data-image="<?= BASE_URL ?>/images/home/imgProntoPraFazerParteDaMpgAcademy.png" data-text="Ja estao disponiveis os novos uniformes oficiais da MPG Academy. Procure seu treinador para consultar valores, tamanhos disponiveis e prazos de entrega.">Ver comunicado completo <i class="icon-go"></i></button>
                        </footer>
                    </div>
                </article>

                <article class="studentAnnouncementCard">
                    <img src="<?= BASE_URL ?>/images/home/imgTopoBanner.png" alt="Copa MPG Academy">
                    <div>
                        <h2>Copa MPG Academy 2024</h2>
                        <p>Vem ai a Copa MPG Academy! O maior torneio interno do ano comeca dia 10/06. Monte seu time e participe!</p>
                        <footer>
                            <span><i class="icon-calendar"></i> 15/05/2024</span>
                            <b class="is-orange">Competicoes</b>
                            <button type="button" class="studentAnnouncementOpen" data-title="Copa MPG Academy 2024" data-date="15/05/2024" data-tag="Evento interno" data-image="<?= BASE_URL ?>/images/home/imgTopoBanner.png" data-text="Vem ai a Copa MPG Academy 2024. Nosso evento interno comeca dia 10/06. Monte seu time, fale com a equipe tecnica e participe dessa experiencia com a comunidade MPG.">Ver comunicado completo <i class="icon-go"></i></button>
                        </footer>
                    </div>
                </article>

                <article class="studentAnnouncementCard">
                    <img src="<?= BASE_URL ?>/images/areadoaluno/imgTopoBanner.png" alt="Calendario de treino MPG Academy">
                    <div>
                        <h2>Alteracao no horario de treino</h2>
                        <p>A partir de 03/06, os treinos das tercas e quintas-feiras serao realizados das 19h as 20h30.</p>
                        <footer>
                            <span><i class="icon-calendar"></i> 10/05/2024</span>
                            <b class="is-blue">Horarios</b>
                            <button type="button" class="studentAnnouncementOpen" data-title="Alteracao no horario de treino" data-date="10/05/2024" data-tag="Horarios" data-image="<?= BASE_URL ?>/images/areadoaluno/imgTopoBanner.png" data-text="A partir de 03/06, os treinos das tercas e quintas-feiras serao realizados das 19h as 20h30. Organize sua chegada com antecedencia para evitar atrasos.">Ver comunicado completo <i class="icon-go"></i></button>
                        </footer>
                    </div>
                </article>
            </section>

            <nav class="studentAnnouncementsPagination" aria-label="Paginacao de comunicados">
                <a href="#" aria-label="Pagina anterior"><i class="icon-prev"></i></a>
                <a class="is-active" href="#">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <a href="#" aria-label="Proxima pagina"><i class="icon-next"></i></a>
            </nav>
        </section>
    </div>

    <div class="studentAnnouncementModal" aria-hidden="true">
        <div class="studentAnnouncementModal__overlay" data-announcement-close></div>
        <article class="studentAnnouncementModal__dialog" role="dialog" aria-modal="true" aria-labelledby="announcementModalTitle">
            <button class="studentAnnouncementModal__close" type="button" data-announcement-close aria-label="Fechar comunicado">Fechar</button>
            <img class="studentAnnouncementModal__image" src="" alt="">
            <div class="studentAnnouncementModal__body">
                <span class="studentAnnouncementModal__tag"></span>
                <h2 id="announcementModalTitle"></h2>
                <time></time>
                <p></p>
            </div>
        </article>
    </div>

    <footer class="studentAreaFooter">
        <div>
            <img src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">
            <p>
                <a href="https://www.instagram.com/mpgacademy/" target="_blank" rel="noopener"><i class="icon-instagram"></i></a>
                <a href="https://wa.me/5511972330097" target="_blank" rel="noopener"><i class="icon-whatsapp"></i></a>
            </p>
        </div>
        <nav>
            <strong>Navegacao</strong>
            <a href="<?= BASE_URL ?>/areadoaluno">Inicio</a>
            <a href="#sobre">Sobre</a>
            <a href="#vagas">Vagas</a>
            <a href="#planos">Planos</a>
        </nav>
        <nav>
            <strong>Aluno</strong>
            <a href="<?= BASE_URL ?>/areadoaluno">Dashboard</a>
            <a href="<?= BASE_URL ?>/meuperfil">Meu Perfil</a>
            <a href="<?= BASE_URL ?>/mensalidades">Mensalidades</a>
            <a href="<?= BASE_URL ?>/comunicados">Comunicados</a>
        </nav>
        <nav>
            <strong>Legal</strong>
            <a href="#">Politica de Privacidade</a>
            <a href="#">Termos de Uso</a>
        </nav>
        <address id="contato">
            <strong>Fale conosco</strong>
            <a href="tel:+5511972330097"><i class="icon-phonecall"></i> (11) 97233-0097</a>
            <a href="mailto:contato@mpgacademy.com.br"><i class="icon-mail"></i> contato@mpgacademy.com.br</a>
            <span><i class="icon-zonanorte"></i> Zona Norte - Sao Paulo / SP</span>
        </address>
        <small>&copy; 2024 MPG Academy. Todos os direitos reservados.</small>
    </footer>
</main>

<?php include ROOT . '/includes/scripts.php';?>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/comunicados/comunicados.js?' . $version . '"></script>';
?>

</body>
</html>

