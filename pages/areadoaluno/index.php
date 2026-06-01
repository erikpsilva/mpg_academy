<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

// Usa a sessão já sincronizada pelo header (inclui foto mais recente)
$aluno = $_SESSION['aluno'];
$primeiroNome = explode(' ', $aluno['nome'])[0];

require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

// 3 últimos comunicados publicados
$stCom = $pdo->query("
    SELECT titulo, conteudo, imagem, criado_em
    FROM comunicados
    WHERE publicado = 1
    ORDER BY criado_em DESC
    LIMIT 3
");
$ultimosComunicados = $stCom->fetchAll();

function tempoRelativo(string $data): string {
    $diff = time() - strtotime($data);
    if ($diff < 3600)  return 'Ha ' . max(1, (int)($diff / 60)) . ' min';
    if ($diff < 86400) return 'Ha ' . (int)($diff / 3600) . ' hora' . ((int)($diff / 3600) > 1 ? 's' : '');
    return 'Ha ' . (int)($diff / 86400) . ' dia' . ((int)($diff / 86400) > 1 ? 's' : '');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Area do Aluno</title>

<?php include ROOT . '/includes/assets.php';?>

</head>

<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php';?>

<main class="studentArea">
    <div class="studentArea__layout">
        <aside class="studentAreaSidebar">
            <nav class="studentAreaSidebar__nav" aria-label="Menu do aluno">
                <a href="#" class="is-active"><i class="icon-home"></i> Dashboard</a>

                <strong>Geral</strong>
                <a href="<?= BASE_URL ?>/meuperfil"><i class="icon-user"></i> Meu Perfil</a>
                <a href="<?= BASE_URL ?>/mensalidades"><i class="icon-creditcard"></i> Mensalidades</a>
                <a href="<?= BASE_URL ?>/treinos"><i class="icon-calendar"></i> Agenda</a>
                <a href="<?= BASE_URL ?>/comunicados"><i class="icon-megaphone"></i> Comunicados</a>

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

            <a class="studentAreaSidebar__logout" href="<?= BASE_URL ?>/services/site/student_logout.php">
                <i class="icon-go"></i> Sair
            </a>
        </aside>

        <section class="studentAreaContent">
            <section class="studentWelcome">
                <div class="studentWelcome__text">
                    <p>Bem-vindo de volta,</p>
                    <h1><?= htmlspecialchars($aluno['nome']) ?></h1>
                    <small>Fique por dentro de tudo que acontece na sua jornada na MPG Academy.</small>
                </div>
            </section>

            <div class="studentQuickLinks">
                <a href="<?= BASE_URL ?>/treinos">
                    <i class="icon-calendar"></i>
                    <span><strong>Agenda</strong> Ver proximos treinos</span>
                </a>
                <a href="<?= BASE_URL ?>/mensalidades">
                    <i class="icon-creditcard"></i>
                    <span><strong>Mensalidades</strong> Ver pagamentos</span>
                </a>
                <a href="#comunicados">
                    <i class="icon-megaphone"></i>
                    <span><strong>Comunicados</strong> Ver ultimas noticias</span>
                </a>
            </div>

            <div class="studentAreaGrid">
                <section class="studentPanel" id="agenda">
                    <div class="studentPanel__head">
                        <h2>Proximos Treinos</h2>
                        <a href="<?= BASE_URL ?>/treinos">Ver agenda completa <i class="icon-go"></i></a>
                    </div>

                    <div class="studentTrainingList">
                        <article>
                            <time><strong>22</strong> Mai</time>
                            <div>
                                <h3>Treino Tecnico</h3>
                                <p><i class="icon-calendar"></i> 19:30 - 21:30</p>
                                <p><i class="icon-zonanorte"></i> Ginasio MPG Academy</p>
                            </div>
                            <span>Quinta</span>
                        </article>
                        <article>
                            <time><strong>24</strong> Mai</time>
                            <div>
                                <h3>Treino Fisico</h3>
                                <p><i class="icon-calendar"></i> 08:00 - 10:00</p>
                                <p><i class="icon-zonanorte"></i> Ginasio MPG Academy</p>
                            </div>
                            <span>Sabado</span>
                        </article>
                        <article>
                            <time><strong>27</strong> Mai</time>
                            <div>
                                <h3>Treino Tecnico</h3>
                                <p><i class="icon-calendar"></i> 19:30 - 21:30</p>
                                <p><i class="icon-zonanorte"></i> Ginasio MPG Academy</p>
                            </div>
                            <span>Terca</span>
                        </article>
                    </div>

                    <a class="studentPanel__button" href="<?= BASE_URL ?>/treinos">Ver todos os treinos</a>
                </section>

                <section class="studentPanel" id="comunicados">
                    <div class="studentPanel__head">
                        <h2>Comunicados Recentes</h2>
                        <a href="<?= BASE_URL ?>/comunicados">Ver todos <i class="icon-go"></i></a>
                    </div>

                    <div class="studentNewsList">
                        <?php if (empty($ultimosComunicados)): ?>
                        <p style="color:#666;font-size:14px;padding:16px 0;">Nenhum comunicado no momento.</p>
                        <?php else: ?>
                        <?php foreach ($ultimosComunicados as $c):
                            $imgSrc = $c['imagem']
                                ? BASE_URL . '/' . htmlspecialchars($c['imagem'])
                                : BASE_URL . '/images/areadoaluno/imgNoticiaExemplo.png';
                            $resumo = mb_substr(strip_tags($c['conteudo'] ?? ''), 0, 80);
                            if (mb_strlen(strip_tags($c['conteudo'] ?? '')) > 80) $resumo .= '…';
                        ?>
                        <article>
                            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($c['titulo']) ?>">
                            <div>
                                <h3><?= htmlspecialchars($c['titulo']) ?> <span><?= tempoRelativo($c['criado_em']) ?></span></h3>
                                <p><?= htmlspecialchars($resumo) ?></p>
                            </div>
                        </article>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="studentPanel" id="mensalidades">
                    <div class="studentPanel__head">
                        <h2>Situacao das Mensalidades</h2>
                        <a href="<?= BASE_URL ?>/mensalidades">Ver todas <i class="icon-go"></i></a>
                    </div>

                    <div class="studentPayment">
                        <div class="studentPayment__status">
                            <i class="icon-check"></i>
                            <div>
                                <h3>Tudo em dia!</h3>
                                <p>Sua proxima cobranca sera no dia 10/06/2024</p>
                            </div>
                        </div>
                        <dl>
                            <div><dt>Plano</dt><dd>Mensal Adulto</dd></div>
                            <div><dt>Proxima cobranca</dt><dd>10/06/2024</dd></div>
                            <div><dt>Valor</dt><dd>R$ 200,00</dd></div>
                        </dl>
                        <a href="<?= BASE_URL ?>/mensalidades">Ver extrato completo</a>
                    </div>
                </section>

                <section class="studentPanel" id="perfil">
                    <div class="studentPanel__head">
                        <h2>Meu Perfil</h2>
                        <a href="<?= BASE_URL ?>/meuperfil">Ver perfil completo <i class="icon-go"></i></a>
                    </div>

                    <div class="studentProfile">
                        <?php if (!empty($aluno['foto'])) : ?>
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($aluno['foto']) ?>" alt="<?= htmlspecialchars($primeiroNome) ?>">
                        <?php else : ?>
                            <span><i class="icon-user"></i></span>
                        <?php endif; ?>
                        <dl>
                            <div><dt>Nome</dt><dd><?= htmlspecialchars($aluno['nome']) ?></dd></div>
                            <div><dt>E-mail</dt><dd><?= htmlspecialchars($aluno['email']) ?></dd></div>
                        </dl>
                    </div>
                </section>
            </div>

            <section class="studentCalendarCta">
                <span><i class="icon-calendar"></i></span>
                <div>
                    <h2>Adicione ao calendario</h2>
                    <p>Sincronize os treinos e eventos da MPG Academy com seu calendario.</p>
                </div>
                <a href="#">Adicionar ao calendario <i class="icon-go"></i></a>
            </section>
        </section>
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
            <a href="<?= BASE_URL ?>">Site</a>
            <a href="<?= BASE_URL ?>/meuperfil">Meu Perfil</a>
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

</body>
</html>
