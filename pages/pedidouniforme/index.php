<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/uniformes.php';

// Módulo escondido do aluno (interruptor em config/uniformes.php) — bloqueia também o
// acesso direto pela URL, senão um link salvo ainda abriria o formulário.
if (!UNIFORMES_VISIVEL_ALUNO) {
    header('Location: ' . BASE_URL . '/areadoaluno');
    exit;
}

$pdo   = getDbConnection();
$aluno = $_SESSION['aluno'];

$turmas = uniformeTurmasDoAluno($pdo, (int) $aluno['id']);

// Um preço por produto (uniforme completo, só a camisa, regata) — todos editáveis no painel.
$produtos = uniformeProdutosDoAluno();
$valores  = uniformeValoresProdutos($pdo);
$valor    = $valores['completo'];

// Pré-seleciona o gênero do uniforme pelo cadastro do aluno (ele pode trocar no formulário).
// `sexo` não vive na sessão — e pode ser 'outro', daí o fallback.
$stSexo = $pdo->prepare("SELECT sexo FROM alunos WHERE id = ?");
$stSexo->execute([(int) $aluno['id']]);
$sexoAluno    = (string) $stSexo->fetchColumn();
$generoPadrao = in_array($sexoAluno, UNIFORME_GENEROS, true) ? $sexoAluno : 'masculino';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Pedido de Uniforme</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php'; ?>

<main class="uniformOrder">
    <div class="container">
        <a class="uniformOrder__back" href="<?= BASE_URL ?>/areadoaluno#uniformes">&#8592; Voltar para a área do aluno</a>

        <?php if (empty($turmas)): ?>

            <div class="uniformOrder__empty">
                <h1>Pedido de uniforme</h1>
                <p>Você precisa estar matriculado em uma turma ativa para pedir o uniforme, porque a numeração das camisetas é organizada por turma.</p>
                <a class="uniformOrder__submit" href="https://wa.me/5511972330097" target="_blank" rel="noopener">Falar com a equipe</a>
            </div>

        <?php else: ?>

            <header class="uniformOrder__head">
                <span>Uniforme oficial</span>
                <h1>Monte o seu uniforme</h1>
                <p>Confirme seus dados, escolha o nome, o número e o tamanho. Em seguida você vai para o pagamento.</p>
            </header>

            <form class="uniformOrder__form" id="uniformOrderForm" novalidate>

                <section class="uniformOrder__block">
                    <h2><span>1</span> Seus dados</h2>

                    <div class="uniformOrder__student">
                        <?php if (!empty($aluno['foto'])): ?>
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($aluno['foto']) ?>" alt="<?= htmlspecialchars($aluno['nome']) ?>">
                        <?php else: ?>
                            <span class="uniformOrder__avatar"><i class="icon-user" aria-hidden="true"></i></span>
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($aluno['nome']) ?></strong>
                            <small><?= htmlspecialchars($aluno['email']) ?></small>
                        </div>
                    </div>

                    <?php if (count($turmas) > 1): ?>
                        <label class="uniformOrder__field">
                            <span>Turma do pedido</span>
                            <select id="uniformTurma" name="turma_id">
                                <?php foreach ($turmas as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>A disponibilidade dos números muda conforme a turma.</small>
                        </label>
                    <?php else: ?>
                        <input type="hidden" id="uniformTurma" name="turma_id" value="<?= (int) $turmas[0]['id'] ?>">
                        <p class="uniformOrder__turmaInfo">
                            Turma: <strong><?= htmlspecialchars($turmas[0]['nome']) ?></strong>
                        </p>
                    <?php endif; ?>
                </section>

                <section class="uniformOrder__block">
                    <h2><span>2</span> O que você quer pedir</h2>

                    <div class="uniformOrder__products">
                        <?php
                        // Vitrine dos produtos à venda (config/uniformes.php). O uniforme
                        // completo não tem foto própria: quem mostra o conjunto são os
                        // modelos do passo seguinte.
                        $capaProduto = [
                            'completo' => 'images/uniformes/uniformeMasculinoPadrao.jpg',
                            'camisa'   => 'images/uniformes/socamisa.png',
                            'regata'   => 'images/uniformes/camisetaRegata.png',
                        ];
                        foreach ($produtos as $tipo => $prod):
                        ?>
                        <label class="uniformOrderProduct">
                            <input type="radio" name="produto" value="<?= $tipo ?>"
                                   data-cortes="<?= htmlspecialchars(implode(',', $prod['cortes'])) ?>"
                                   data-valor="<?= $valores[$tipo] ?>"
                                   <?= $tipo === 'completo' ? 'checked' : '' ?>>
                            <span class="uniformOrderProduct__box">
                                <img src="<?= BASE_URL ?>/<?= $capaProduto[$tipo] ?? $prod['imagem'] ?>"
                                     alt="<?= htmlspecialchars($prod['nome']) ?>">
                                <strong><?= htmlspecialchars($prod['nome']) ?></strong>
                                <small><?= htmlspecialchars($prod['descricao']) ?></small>
                                <em>R$ <?= number_format($valores[$tipo], 2, ',', '.') ?></em>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="uniformOrder__block">
                    <h2><span>3</span> Modelo e tamanho do corte</h2>

                    <div class="uniformOrder__models">
                        <?php
                        // O corte (masculino, feminino, infantil) define a grade de tamanhos e
                        // também o balde da numeração. A arte infantil é a mesma do uniforme
                        // adulto — muda a modelagem, por isso ela reaproveita a mesma foto.
                        $modelos = [
                            ['genero' => 'masculino', 'modelo' => 'padrao', 'img' => 'uniformeMasculinoPadrao.jpg', 'tag' => 'Masculino', 'nome' => 'Modelo padrão'],
                            ['genero' => 'masculino', 'modelo' => 'libero', 'img' => 'uniformeMasculinoLibero.jpg', 'tag' => 'Masculino', 'nome' => 'Modelo líbero'],
                            ['genero' => 'feminino',  'modelo' => 'padrao', 'img' => 'uniformeFemininoPadrao.jpg',  'tag' => 'Feminino',  'nome' => 'Modelo padrão'],
                            ['genero' => 'feminino',  'modelo' => 'libero', 'img' => 'uniformeFemininoLibero.jpg',  'tag' => 'Feminino',  'nome' => 'Modelo líbero'],
                            ['genero' => 'infantil',  'modelo' => 'padrao', 'img' => 'uniformeMasculinoPadrao.jpg', 'tag' => 'Infantil',  'nome' => 'Modelo padrão'],
                            ['genero' => 'infantil',  'modelo' => 'libero', 'img' => 'uniformeMasculinoLibero.jpg', 'tag' => 'Infantil',  'nome' => 'Modelo líbero'],
                        ];
                        foreach ($modelos as $i => $m):
                            $checked = ($m['genero'] === $generoPadrao && $m['modelo'] === 'padrao');
                        ?>
                        <label class="uniformOrderModel" data-genero-card="<?= $m['genero'] ?>">
                            <input type="radio" name="modelo_completo" value="<?= $m['genero'] ?>|<?= $m['modelo'] ?>"
                                   data-genero="<?= $m['genero'] ?>" data-modelo="<?= $m['modelo'] ?>"
                                   <?= $checked ? 'checked' : '' ?>>
                            <span class="uniformOrderModel__box">
                                <span class="uniformOrderModel__tag"><?= $m['tag'] ?></span>
                                <img src="<?= BASE_URL ?>/images/uniformes/<?= $m['img'] ?>" alt="Uniforme <?= strtolower($m['tag']) ?> <?= $m['nome'] ?>">
                                <span class="uniformOrderModel__name"><?= $m['nome'] ?></span>
                                <?php if ($m['genero'] === 'infantil'): ?>
                                    <span class="uniformOrderModel__hint">Mesma arte, modelagem infantil (2 a 14 anos)</span>
                                <?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <p class="uniformOrder__productNote" id="uniformProductNote"></p>
                </section>

                <section class="uniformOrder__block">
                    <h2><span>4</span> Personalização</h2>

                    <label class="uniformOrder__field">
                        <span>Nome na camiseta</span>
                        <input type="text" id="uniformNome" name="nome_camisa" maxlength="<?= UNIFORME_NOME_MAX ?>"
                               placeholder="Ex.: MARIANA" autocomplete="off" required>
                        <small>Até <?= UNIFORME_NOME_MAX ?> caracteres. Vai estampado em caixa alta.</small>
                    </label>

                    <div class="uniformOrder__field">
                        <span>Número da camiseta</span>
                        <button type="button" class="uniformOrder__numberPick" id="uniformNumberPick">
                            <em id="uniformNumberLabel">Escolher número</em>
                            <i class="icon-go" aria-hidden="true"></i>
                        </button>
                        <input type="hidden" id="uniformNumero" name="numero" value="">
                        <small>De 1 a 99, entre os que ainda estão livres na sua turma.</small>
                    </div>

                    <?php // Camisa e shorts têm grades diferentes — por isso são escolhidos separados. ?>
                    <div class="uniformOrder__field">
                        <span id="labelTamCamisa">Tamanho da camisa</span>
                        <div class="uniformOrder__sizes" id="uniformSizesCamisa"></div>
                        <input type="hidden" id="uniformTamanhoCamisa" name="tamanho_camisa" value="">
                        <small>
                            <button type="button" class="uniformOrder__measureLink" data-medidas="camisa">Ver medidas da camisa</button>
                        </small>
                    </div>

                    <?php // Some quando o produto não tem calção (só a camisa, regata). ?>
                    <div class="uniformOrder__field" id="fieldTamShorts">
                        <span id="labelTamShorts">Tamanho do shorts</span>
                        <div class="uniformOrder__sizes" id="uniformSizesShorts"></div>
                        <input type="hidden" id="uniformTamanhoShorts" name="tamanho_shorts" value="">
                        <small>
                            <button type="button" class="uniformOrder__measureLink" data-medidas="shorts">Ver medidas</button>
                        </small>
                    </div>
                </section>

                <section class="uniformOrder__block uniformOrder__block--summary">
                    <h2><span>5</span> Resumo e pagamento</h2>

                    <dl class="uniformOrder__summary">
                        <div><dt>Produto</dt><dd id="resumoProduto">—</dd></div>
                        <div><dt>Modelo</dt><dd id="resumoModelo">—</dd></div>
                        <div><dt>Nome</dt><dd id="resumoNome">—</dd></div>
                        <div><dt>Número</dt><dd id="resumoNumero">—</dd></div>
                        <div><dt id="resumoLabelCamisa">Tam. camisa</dt><dd id="resumoTamCamisa">—</dd></div>
                        <div id="resumoLinhaShorts"><dt id="resumoLabelShorts">Tam. shorts</dt><dd id="resumoTamShorts">—</dd></div>
                        <div class="uniformOrder__summaryTotal">
                            <dt>Total</dt>
                            <dd id="resumoTotal">R$ <?= number_format($valor, 2, ',', '.') ?></dd>
                        </div>
                    </dl>

                    <p class="uniformOrder__notice">
                        <i class="icon-creditcard" aria-hidden="true"></i>
                        Ao continuar você vai direto para a área de pagamento. O uniforme é cobrado na hora e
                        <strong>o pedido só é confirmado depois do pagamento aprovado</strong>. Seu número fica
                        reservado por <?= UNIFORME_RESERVA_MINUTOS ?> minutos enquanto você conclui.
                    </p>

                    <p class="uniformOrder__error" id="uniformError" role="alert"></p>

                    <button type="submit" class="uniformOrder__submit" id="uniformSubmit">
                        Ir para o pagamento — <span id="uniformSubmitValor">R$ <?= number_format($valor, 2, ',', '.') ?></span>
                    </button>
                </section>

            </form>

        <?php endif; ?>
    </div>
</main>

<!-- ── Modal de escolha do número ───────────────────────────────────────────── -->
<div class="uniformNumbers" id="uniformNumbersModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="uniformNumbersTitle">
    <button type="button" class="uniformNumbers__backdrop js-numbers-close" aria-label="Fechar"></button>
    <div class="uniformNumbers__dialog">
        <div class="uniformNumbers__head">
            <div>
                <h2 id="uniformNumbersTitle">Escolha seu número</h2>
                <p id="uniformNumbersSub">Números disponíveis na sua turma.</p>
            </div>
            <button type="button" class="uniformNumbers__close js-numbers-close" aria-label="Fechar">&times;</button>
        </div>

        <ul class="uniformNumbers__legend">
            <li><span class="uniformNumbers__chip uniformNumbers__chip--free"></span> Disponível</li>
            <li><span class="uniformNumbers__chip uniformNumbers__chip--mine"></span> Seu número</li>
            <li><span class="uniformNumbers__chip uniformNumbers__chip--taken"></span> Indisponível</li>
        </ul>

        <div class="uniformNumbers__grid" id="uniformNumbersGrid">
            <p class="uniformNumbers__loading">Carregando números...</p>
        </div>
    </div>
</div>

<!-- Modal da tabela de medidas -->
<div class="uniformMeasures" id="uniformMeasuresModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="uniformMeasuresTitle">
    <button type="button" class="uniformMeasures__backdrop js-measures-close" aria-label="Fechar tabela de medidas"></button>
    <div class="uniformMeasures__dialog">
        <div class="uniformMeasures__head">
            <div>
                <span>Uniformes MPG Academy</span>
                <h2 id="uniformMeasuresTitle">Tabela de medidas</h2>
                <p id="uniformMeasuresSub">Compare com uma peça que você já usa para escolher o tamanho.</p>
            </div>
            <button type="button" class="uniformMeasures__close js-measures-close" aria-label="Fechar">&times;</button>
        </div>

        <?php // Preenchido pelo JS com a tabela do gênero e da peça escolhidos. ?>
        <div class="uniformMeasures__body" id="uniformMeasuresBody"></div>
    </div>
</div>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>

<script>
var BASE_URL               = "<?= BASE_URL ?>";
// Grades e medidas vêm do PHP pra nunca divergirem de config/uniformes.php.
var UNIFORME_MEDIDAS       = <?= json_encode(UNIFORME_MEDIDAS, JSON_UNESCAPED_UNICODE) ?>;
var UNIFORME_AVISO_MEDIDAS = <?= json_encode(UNIFORME_AVISO_MEDIDAS, JSON_UNESCAPED_UNICODE) ?>;
var UNIFORME_MODELOS_LABEL = <?= json_encode(UNIFORME_MODELO_LABEL, JSON_UNESCAPED_UNICODE) ?>;
var UNIFORME_GENERO_LABEL  = <?= json_encode(UNIFORME_GENERO_LABEL, JSON_UNESCAPED_UNICODE) ?>;
// Catálogo e preços: quais peças cada produto tem e em que cortes ele é vendido.
var UNIFORME_PRODUTOS      = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE) ?>;
var UNIFORME_VALORES       = <?= json_encode($valores, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= BASE_URL ?>/pages/pedidouniforme/pedidouniforme.js?v=<?= time() ?>"></script>

</body>
</html>
