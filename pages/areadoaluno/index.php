<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

// Usa a sessão já sincronizada pelo header (inclui foto mais recente)
$aluno = $_SESSION['aluno'];
$primeiroNome = explode(' ', $aluno['nome'])[0];

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/uniformes.php';
$pdo = getDbConnection();

// Pedidos de uniforme do aluno (só os pagos — os demais são reservas que expiram)
$meusUniformes = [];
try {
    $stUni = $pdo->prepare("
        SELECT p.id, p.genero, p.modelo, p.nome_camisa, p.numero, p.tamanho_camisa, p.tamanho_shorts, p.valor,
               p.status_pedido, p.pago_em
        FROM pedidos_uniforme p
        WHERE p.aluno_id = ? AND p.status_pagamento = 'pago'
        ORDER BY p.pago_em DESC
    ");
    $stUni->execute([(int) $aluno['id']]);
    $meusUniformes = $stUni->fetchAll();
} catch (PDOException $e) {
    // Tabela ainda não migrada — a área do aluno segue funcionando sem a seção.
}

// ── Situação das mensalidades (dados reais) ──────────────────────────────────
// Prioriza a fatura em aberto mais antiga: se houver atraso, é ela que precisa aparecer.
require_once ROOT . '/config/mercadopago.php'; // mpCalcularMultaJuros()

$stAberta = $pdo->prepare("
    SELECT m.id, m.referencia, m.tipo, m.descricao, m.valor, m.vencimento, m.status,
           COALESCE(t.nome, '') AS turma_nome
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.aluno_id = ? AND m.status <> 'pago'
    ORDER BY m.vencimento ASC
    LIMIT 1
");
$stAberta->execute([(int) $aluno['id']]);
$faturaAberta = $stAberta->fetch();

$stAtrasadas = $pdo->prepare("SELECT COUNT(*) FROM mensalidades WHERE aluno_id = ? AND status = 'atrasado'");
$stAtrasadas->execute([(int) $aluno['id']]);
$qtdAtrasadas = (int) $stAtrasadas->fetchColumn();

$stPaga = $pdo->prepare("
    SELECT m.referencia, m.valor, m.data_pagamento, COALESCE(t.nome, '') AS turma_nome
    FROM mensalidades m
    LEFT JOIN turmas t ON t.id = m.turma_id
    WHERE m.aluno_id = ? AND m.status = 'pago'
    ORDER BY m.data_pagamento DESC, m.id DESC
    LIMIT 1
");
$stPaga->execute([(int) $aluno['id']]);
$ultimaPaga = $stPaga->fetch();

// Turma ativa = "plano" mostrado no card.
$stPlano = $pdo->prepare("
    SELECT t.nome FROM turma_alunos ta
    JOIN turmas t ON t.id = ta.turma_id
    WHERE ta.aluno_id = ? AND ta.status = 'ativo' AND t.status = 'ativa'
    ORDER BY t.nome LIMIT 1
");
$stPlano->execute([(int) $aluno['id']]);
$planoNome = $stPlano->fetchColumn() ?: null;

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
                <?php if (UNIFORMES_VISIVEL_ALUNO): ?>
                <a href="#uniformes"><i class="icon-check"></i> Uniformes</a>
                <?php endif; ?>
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

            <?php if (UNIFORMES_VISIVEL_ALUNO): // interruptor em config/uniformes.php ?>
            <section class="studentUniforms" id="uniformes">
                <div class="studentUniforms__head">
                    <div>
                        <span>Uniformes oficiais</span>
                        <h2>Escolha o seu uniforme MPG Academy</h2>
                    </div>
                </div>

                <div class="studentUniforms__info">
                    <div class="studentUniforms__customization">
                        <strong>Seu uniforme, do seu jeito</strong>
                        <p>Você pode escolher o nome e um número de <b>1 a 99</b> para personalizar sua camiseta.</p>
                        <ul>
                            <li>O número escolhido precisa estar disponível na sua turma.</li>
                            <li>A disponibilidade é separada por turma e por uniforme masculino ou feminino.</li>
                            <li>Por isso, alunos de turmas diferentes podem usar o mesmo número.</li>
                            <li>Na mesma turma, uma aluna e um aluno também podem escolher números iguais.</li>
                        </ul>
                    </div>
                    <div class="studentUniforms__price">
                        <span>Conjunto completo</span>
                        <strong>R$ 115,00</strong>
                        <small>Camisa + shorts + meião</small>
                    </div>
                </div>

                <p class="studentUniforms__previewHint">Clique em qualquer modelo para visualizar a imagem ampliada.</p>

                <div class="studentUniforms__grid">
                    <article class="studentUniformCard">
                        <div class="studentUniformCard__category">Masculino</div>
                        <button type="button" class="studentUniformCard__image js-uniform-preview" data-image="<?= BASE_URL ?>/images/uniformes/uniformeMasculinoPadrao.jpg" data-title="Uniforme masculino padrão">
                            <img src="<?= BASE_URL ?>/images/uniformes/uniformeMasculinoPadrao.jpg" alt="Uniforme masculino padrão">
                            <span>Ver ampliado</span>
                        </button>
                        <h3>Modelo padrão</h3>
                    </article>

                    <article class="studentUniformCard">
                        <div class="studentUniformCard__category">Masculino</div>
                        <button type="button" class="studentUniformCard__image js-uniform-preview" data-image="<?= BASE_URL ?>/images/uniformes/uniformeMasculinoLibero.jpg" data-title="Uniforme masculino de líbero">
                            <img src="<?= BASE_URL ?>/images/uniformes/uniformeMasculinoLibero.jpg" alt="Uniforme masculino de líbero">
                            <span>Ver ampliado</span>
                        </button>
                        <h3>Modelo líbero</h3>
                    </article>

                    <article class="studentUniformCard">
                        <div class="studentUniformCard__category">Feminino</div>
                        <button type="button" class="studentUniformCard__image js-uniform-preview" data-image="<?= BASE_URL ?>/images/uniformes/uniformeFemininoPadrao.jpg" data-title="Uniforme feminino padrão">
                            <img src="<?= BASE_URL ?>/images/uniformes/uniformeFemininoPadrao.jpg" alt="Uniforme feminino padrão">
                            <span>Ver ampliado</span>
                        </button>
                        <h3>Modelo padrão</h3>
                    </article>

                    <article class="studentUniformCard">
                        <div class="studentUniformCard__category">Feminino</div>
                        <button type="button" class="studentUniformCard__image js-uniform-preview" data-image="<?= BASE_URL ?>/images/uniformes/uniformeFemininoLibero.jpg" data-title="Uniforme feminino de líbero">
                            <img src="<?= BASE_URL ?>/images/uniformes/uniformeFemininoLibero.jpg" alt="Uniforme feminino de líbero">
                            <span>Ver ampliado</span>
                        </button>
                        <h3>Modelo líbero</h3>
                    </article>
                </div>

                <div class="studentUniformSizes">
                    <div class="studentUniformSizes__head">
                        <span>Encontre o tamanho ideal</span>
                        <h3>Tabelas de medidas</h3>
                        <p>Camisa e shorts têm grades diferentes — e as medidas mudam entre o modelo masculino e o feminino. Confira a sua antes de pedir.</p>
                    </div>

                    <?php
                    // Vem de config/uniformes.php via includes/uniforme_medidas.php — as mesmas
                    // tabelas que o formulário de pedido mostra, sem risco de divergirem.
                    include ROOT . '/includes/uniforme_medidas.php';
                    ?>
                </div>

                <a class="studentUniforms__order" href="<?= BASE_URL ?>/pedidouniforme">Fazer pedido do uniforme</a>
            </section>
            <?php endif; // fim UNIFORMES_VISIVEL_ALUNO ?>

            <?php if (UNIFORMES_VISIVEL_ALUNO && !empty($meusUniformes)): ?>
            <section class="studentMyUniforms" id="meus-uniformes">
                <div class="studentMyUniforms__head">
                    <span>Acompanhe seu pedido</span>
                    <h2>Meus pedidos de uniforme</h2>
                    <p>Veja o que você pediu e em que etapa da produção ele está.</p>
                </div>

                <?php foreach ($meusUniformes as $p):
                    $indice      = array_search($p['status_pedido'], UNIFORME_STATUS_FLUXO, true);
                    $indice      = $indice === false ? 0 : $indice;
                    $generoLabel = $p['genero'] === 'feminino' ? 'Feminino' : 'Masculino';
                    $modeloLabel = UNIFORME_MODELO_LABEL[$p['modelo']] ?? $p['modelo'];
                    $imgNome     = 'uniforme' . ucfirst($p['genero']) . ($p['modelo'] === 'libero' ? 'Libero' : 'Padrao') . '.jpg';
                ?>
                <article class="studentUniformOrder">
                    <div class="studentUniformOrder__top">
                        <img src="<?= BASE_URL ?>/images/uniformes/<?= $imgNome ?>" alt="Uniforme <?= strtolower($generoLabel) ?> <?= htmlspecialchars($modeloLabel) ?>">

                        <div class="studentUniformOrder__info">
                            <h3><?= $generoLabel ?> — <?= htmlspecialchars($modeloLabel) ?></h3>
                            <dl>
                                <div><dt>Nome</dt><dd><?= htmlspecialchars($p['nome_camisa']) ?></dd></div>
                                <div><dt>Número</dt><dd>#<?= (int) $p['numero'] ?></dd></div>
                                <div><dt>Tam. camisa</dt><dd><?= htmlspecialchars($p['tamanho_camisa']) ?></dd></div>
                                <div><dt>Tam. <?= htmlspecialchars(mb_strtolower(explode(' ', uniformeLabelPeca($p['genero'], 'shorts'))[0])) ?></dt><dd><?= htmlspecialchars($p['tamanho_shorts']) ?></dd></div>
                                <div><dt>Valor pago</dt><dd>R$ <?= number_format((float) $p['valor'], 2, ',', '.') ?></dd></div>
                                <?php if (!empty($p['pago_em'])): ?>
                                <div><dt>Pedido em</dt><dd><?= (new DateTime($p['pago_em']))->format('d/m/Y') ?></dd></div>
                                <?php endif; ?>
                            </dl>
                        </div>

                        <span class="studentUniformOrder__badge studentUniformOrder__badge--<?= $p['status_pedido'] ?>">
                            <?= UNIFORME_STATUS_LABEL[$p['status_pedido']] ?? $p['status_pedido'] ?>
                        </span>
                    </div>

                    <ol class="studentUniformOrder__steps">
                        <?php foreach (UNIFORME_STATUS_FLUXO as $i => $s): ?>
                        <li class="<?= $i < $indice ? 'is-done' : ($i === $indice ? 'is-current' : '') ?>">
                            <span></span>
                            <small><?= UNIFORME_STATUS_LABEL[$s] ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </article>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

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

                    <?php
                    // Três situações possíveis, nesta ordem de prioridade:
                    // atraso > fatura a vencer > tudo pago.
                    $emAtraso = $faturaAberta && $faturaAberta['status'] === 'atrasado';
                    $totalAbrt = 0.0;

                    if ($faturaAberta) {
                        $totalAbrt = (float) $faturaAberta['valor'];
                        if ($emAtraso) {
                            // Mesma regra de multa/juros usada na cobrança.
                            $totalAbrt = mpCalcularMultaJuros((float) $faturaAberta['valor'], $faturaAberta['vencimento'])['total'];
                        }
                    }

                    $modificador = $emAtraso ? ' studentPayment--atrasado'
                                             : ($faturaAberta ? ' studentPayment--pendente' : ' studentPayment--ok');
                    ?>
                    <div class="studentPayment<?= $modificador ?>">
                        <div class="studentPayment__status">
                            <i class="<?= $emAtraso ? 'icon-close' : 'icon-check' ?>"></i>
                            <div>
                                <?php if ($emAtraso): ?>
                                    <h3>Mensalidade em atraso</h3>
                                    <p>
                                        Venceu em <?= (new DateTime($faturaAberta['vencimento']))->format('d/m/Y') ?>.
                                        <?php if ($qtdAtrasadas > 1): ?>
                                            Você tem <?= $qtdAtrasadas ?> faturas em atraso.
                                        <?php else: ?>
                                            Regularize para continuar treinando.
                                        <?php endif; ?>
                                    </p>
                                <?php elseif ($faturaAberta): ?>
                                    <h3>Você tem uma fatura em aberto</h3>
                                    <p>Vence em <?= (new DateTime($faturaAberta['vencimento']))->format('d/m/Y') ?>.</p>
                                <?php elseif ($ultimaPaga): ?>
                                    <h3>Tudo em dia!</h3>
                                    <p>
                                        Último pagamento
                                        <?php if (!empty($ultimaPaga['data_pagamento'])): ?>
                                            em <?= (new DateTime($ultimaPaga['data_pagamento']))->format('d/m/Y') ?>.
                                        <?php else: ?>
                                            registrado.
                                        <?php endif; ?>
                                        Nenhuma cobrança em aberto.
                                    </p>
                                <?php else: ?>
                                    <h3>Nenhuma cobrança por aqui</h3>
                                    <p>Você ainda não tem mensalidades registradas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <dl>
                            <div>
                                <dt>Plano</dt>
                                <dd><?= $planoNome ? htmlspecialchars($planoNome) : '—' ?></dd>
                            </div>
                            <div>
                                <dt><?= $emAtraso ? 'Venceu em' : 'Proxima cobranca' ?></dt>
                                <dd>
                                    <?= $faturaAberta
                                        ? (new DateTime($faturaAberta['vencimento']))->format('d/m/Y')
                                        : '—' ?>
                                </dd>
                            </div>
                            <div>
                                <dt><?= $emAtraso ? 'Total com juros' : 'Valor' ?></dt>
                                <dd>
                                    <?= $faturaAberta
                                        ? 'R$ ' . number_format($totalAbrt, 2, ',', '.')
                                        : '—' ?>
                                </dd>
                            </div>
                        </dl>
                        <a href="<?= BASE_URL ?>/mensalidades">
                            <?= $faturaAberta ? 'Pagar agora' : 'Ver extrato completo' ?>
                        </a>
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

<div class="studentUniformModal" id="studentUniformModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="studentUniformModalTitle">
    <button type="button" class="studentUniformModal__backdrop js-uniform-close" aria-label="Fechar visualização"></button>
    <div class="studentUniformModal__content">
        <button type="button" class="studentUniformModal__close js-uniform-close" aria-label="Fechar">&times;</button>
        <h2 id="studentUniformModalTitle"></h2>
        <img id="studentUniformModalImage" src="" alt="">
    </div>
</div>

<?php include ROOT . '/includes/scripts.php';?>

<script>
(function () {
    var modal = document.getElementById('studentUniformModal');
    var image = document.getElementById('studentUniformModalImage');
    var title = document.getElementById('studentUniformModalTitle');
    if (!modal || !image || !title) return;

    document.querySelectorAll('.js-uniform-preview').forEach(function (button) {
        button.addEventListener('click', function () {
            image.src = button.getAttribute('data-image');
            image.alt = button.getAttribute('data-title');
            title.textContent = button.getAttribute('data-title');
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    });

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-uniform-close').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
}());
</script>

</body>
</html>
