<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$comunicados = $pdo->query("
    SELECT * FROM comunicados
    WHERE publicado = 1
    ORDER BY destaque DESC, criado_em DESC
")->fetchAll();

$cores = ['is-blue', 'is-green', 'is-purple', 'is-orange'];
$corTag = function(string $tag) use ($cores): string {
    return $tag ? $cores[abs(crc32($tag)) % count($cores)] : 'is-blue';
};
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
                <?php if (empty($comunicados)): ?>
                <p style="color:#888;text-align:center;padding:40px 0;">Nenhum comunicado disponível no momento.</p>
                <?php endif; ?>

                <?php foreach ($comunicados as $c):
                    $imgSrc  = $c['imagem'] ? BASE_URL . '/' . htmlspecialchars($c['imagem']) : BASE_URL . '/images/areadoaluno/imgNoticiaExemplo.png';
                    $imgAlt  = htmlspecialchars($c['titulo']);
                    $dataFmt = date('d/m/Y', strtotime($c['criado_em']));
                    $tag     = $c['tag'] ?: '';
                    $cor     = $corTag($tag);
                    // Resumo: texto sem tags, primeiros 160 chars
                    $resumo  = mb_substr(strip_tags($c['conteudo'] ?? ''), 0, 160);
                    if (mb_strlen(strip_tags($c['conteudo'] ?? '')) > 160) $resumo .= '…';
                ?>
                <article class="studentAnnouncementCard<?= $c['destaque'] ? ' is-featured' : '' ?>">
                    <img src="<?= $imgSrc ?>" alt="<?= $imgAlt ?>">
                    <div>
                        <?php if ($c['destaque']): ?>
                        <strong><i class="icon-check"></i> Destaque</strong>
                        <?php endif; ?>
                        <h2><?= htmlspecialchars($c['titulo']) ?></h2>
                        <p><?= htmlspecialchars($resumo) ?></p>
                        <footer>
                            <span><i class="icon-calendar"></i> <?= $dataFmt ?></span>
                            <?php if ($tag): ?>
                            <b class="<?= $cor ?>"><?= htmlspecialchars($tag) ?></b>
                            <?php endif; ?>
                            <button type="button" class="studentAnnouncementOpen"
                                    data-title="<?= htmlspecialchars($c['titulo']) ?>"
                                    data-date="<?= $dataFmt ?>"
                                    data-tag="<?= htmlspecialchars($tag) ?>"
                                    data-image="<?= $imgSrc ?>"
                                    data-html="<?= htmlspecialchars($c['conteudo'] ?? '') ?>">
                                Ver comunicado completo <i class="icon-go"></i>
                            </button>
                        </footer>
                    </div>
                </article>
                <?php endforeach; ?>
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
                <div class="studentAnnouncementModal__content"></div>
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

