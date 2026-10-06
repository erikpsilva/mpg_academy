<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (!in_array($nivel, ['admin', 'editor'], true)) {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/uniformes.php';

$pdo      = getDbConnection();
$produtos = uniformeProdutosDoAluno();          // completo, só a camisa, regata
$valores  = uniformeValoresProdutos($pdo);
$valor    = $valores['completo'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Pedir Uniforme</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="pedirUniforme">

            <div class="row pedirUniforme__header">
                <div class="col-md-12">
                    <h2>Pedir <span>Uniforme</span></h2>
                    <p>Registre um pedido para aluno, professor ou equipe MPG. O pagamento pode ficar pendente e ser confirmado depois na lista de pedidos.</p>
                </div>
            </div>

            <nav class="uniformHubNav" aria-label="Navegação de uniformes">
                <a class="uniformHubNav__link" href="<?= ADMIN_BASE_URL ?>/uniformes">
                    <span class="uniformHubNav__icon">01</span>
                    <span><strong>Pedidos</strong><small>Acompanhar produção</small></span>
                </a>
                <a class="uniformHubNav__link is-active" href="<?= ADMIN_BASE_URL ?>/pediruniforme">
                    <span class="uniformHubNav__icon">02</span>
                    <span><strong>Novo pedido</strong><small>Aluno ou equipe</small></span>
                </a>
                <a class="uniformHubNav__link" href="<?= ADMIN_BASE_URL ?>/pagamentos-uniformes">
                    <span class="uniformHubNav__icon">03</span>
                    <span><strong>Pagamentos</strong><small>Valores recebidos</small></span>
                </a>
            </nav>

            <form class="pedirUniforme__form" id="pedirUniformeForm" novalidate>

                <div class="pedirUniforme__block">
                    <h3><span>1</span> Para quem é o pedido</h3>

                    <div class="pedirUniforme__destino" id="destinoBox">
                        <button type="button" class="pedirUniforme__destinoOpt is-active" data-destino="aluno">
                            <strong>Aluno</strong>
                            <small>Uniforme completo, cobrado</small>
                        </button>
                        <button type="button" class="pedirUniforme__destinoOpt" data-destino="equipe">
                            <strong>Professor ou equipe MPG</strong>
                            <small>Interno, sem cobrança</small>
                        </button>
                    </div>
                </div>

                <!-- Professor / equipe MPG -->
                <div class="pedirUniforme__block" id="equipeBlock" style="display:none;">
                    <h3><span>2</span> Pessoa e tipo de uniforme</h3>

                    <div class="pedirUniforme__field">
                        <span>Pessoa</span>
                        <select id="equipePessoa">
                            <option value="">Carregando...</option>
                        </select>
                        <small>Professores e usuários do painel administrativo.</small>
                    </div>

                    <div class="pedirUniforme__field">
                        <span>Tipo de uniforme</span>
                        <?php
                        // Mesmo catálogo do aluno (uniforme completo, só a camisa, regata) mais
                        // a camisa da equipe técnica, que só existe aqui. Sai de
                        // UNIFORME_PRODUTOS pra tela não ficar pra trás quando entrar produto novo.
                        ?>
                        <select id="equipeTipo">
                            <?php foreach (UNIFORME_PRODUTOS as $tipo => $prod): ?>
                            <option value="<?= $tipo ?>"
                                    data-pecas="<?= implode(',', $prod['pecas']) ?>"
                                    data-cortes="<?= implode(',', $prod['cortes']) ?>"
                                    data-corte-unico="<?= !empty($prod['corte_unico']) ? '1' : '0' ?>">
                                <?= htmlspecialchars(UNIFORME_TIPO_LABEL[$tipo] ?? $prod['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="equipeTipoHint">Camisa + calção, com nome e número.</small>
                    </div>

                    <div class="pedirUniforme__field" id="equipeCargoField" style="display:none;">
                        <span>Texto da camisa</span>
                        <select id="equipeCargo">
                            <option value="equipe_tecnica">Equipe Técnica</option>
                            <option value="tecnico">Técnico</option>
                        </select>
                        <small id="equipeCargoPreview">Vai estampado como: <strong>Equipe Técnica — NOME</strong></small>
                    </div>

                    <div class="pedirUniforme__equipeFoto" id="equipeFoto" style="display:none;">
                        <img src="<?= BASE_URL ?>/<?= UNIFORME_EQUIPE_IMAGEM ?>" alt="Camisa da equipe técnica" data-lightbox>
                        <small>Camisa da equipe técnica — clique para ampliar.</small>
                    </div>
                </div>

                <div class="pedirUniforme__block" id="alunoBlock">
                    <h3><span>2</span> Aluno</h3>

                    <div class="pedirUniforme__search">
                        <input type="text" id="buscaAluno" class="input" placeholder="Buscar aluno por nome ou e-mail..." autocomplete="off">
                        <div class="pedirUniforme__searchResults" id="buscaAlunoResults"></div>
                    </div>

                    <div class="pedirUniforme__alunoSelecionado" id="alunoSelecionadoBox" style="display:none;">
                        <div>
                            <strong id="alunoSelecionadoNome"></strong>
                            <small id="alunoSelecionadoEmail"></small>
                        </div>
                        <button type="button" class="btn btn--gray btn--sm" id="alunoTrocarBtn">Trocar</button>
                    </div>
                    <input type="hidden" id="alunoId" name="aluno_id" value="">

                    <div class="pedirUniforme__field" id="turmaField" style="display:none;">
                        <span>Turma</span>
                        <select id="turmaSelect" name="turma_id"></select>
                        <small>A disponibilidade dos números muda conforme a turma.</small>
                    </div>
                </div>

                <!--
                    Equipe técnica é UM produto só (a camisa do print acima), então aqui não
                    tem modelo a escolher — só o corte, que muda a grade de tamanho. Mostrar
                    os 4 modelos de uniforme completo junto misturaria duas coisas diferentes.
                -->
                <div class="pedirUniforme__block" id="corteBlock" style="display:none;">
                    <h3><span>3</span> Corte da camisa</h3>

                    <div class="pedirUniforme__destino">
                        <button type="button" class="pedirUniforme__destinoOpt is-active" data-corte="masculino">
                            <strong>Masculina</strong>
                            <small>Grade PP ao XG3</small>
                        </button>
                        <button type="button" class="pedirUniforme__destinoOpt" data-corte="feminino">
                            <strong>Feminina</strong>
                            <small>Grade PP ao XG</small>
                        </button>
                    </div>
                </div>

                <div class="pedirUniforme__block" id="modeloBlock">
                    <h3><span>3</span> Produto e modelo</h3>

                    <?php
                    // O produto define o preço, as peças e os cortes disponíveis. Em cartão
                    // com foto, e não num <select>: é a primeira escolha da tela e some num
                    // campo pequeno no meio do formulário.
                    $capaProduto = [
                        'completo' => 'images/uniformes/uniformeMasculinoPadrao.jpg',
                        'camisa'   => 'images/uniformes/socamisa.png',
                        'regata'   => 'images/uniformes/camisetaRegata.png',
                    ];
                    ?>
                    <div class="pedirUniforme__field" id="produtoField">
                        <span>Produto</span>

                        <div class="pedirUniforme__produtos">
                            <?php foreach ($produtos as $tipo => $prod): ?>
                            <label class="pedirUniformeProduto">
                                <input type="radio" name="produto" value="<?= $tipo ?>" <?= $tipo === 'completo' ? 'checked' : '' ?>>
                                <span class="pedirUniformeProduto__box">
                                    <img src="<?= BASE_URL ?>/<?= $capaProduto[$tipo] ?? $prod['imagem'] ?>"
                                         alt="<?= htmlspecialchars($prod['nome']) ?>">
                                    <strong><?= htmlspecialchars($prod['nome']) ?></strong>
                                    <em>R$ <?= number_format($valores[$tipo], 2, ',', '.') ?></em>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <small id="produtoHint"></small>
                    </div>

                    <?php
                    // A regata não tem corte: é peça única, unissex. Aqui ela troca os
                    // cartões de modelo por este aviso — o corte gravado no pedido vem do
                    // cadastro do aluno e serve só pro balde da numeração.
                    ?>
                    <div class="pedirUniforme__unico" id="produtoUnicoAviso" hidden>
                        <img src="<?= BASE_URL ?>/images/uniformes/camisetaRegata.png" alt="Camiseta regata">
                        <div>
                            <strong>Peça única, unissex</strong>
                            <p>A regata não tem versão masculina, feminina ou infantil — é um modelo só, na arte preta. Escolha o nome, o número e o tamanho (PP ao XG3).</p>
                        </div>
                    </div>

                    <div class="pedirUniforme__models" id="modelosBox">
                        <?php
                        $modelos = [
                            ['genero' => 'masculino', 'modelo' => 'padrao', 'img' => 'uniformeMasculinoPadrao.jpg', 'tag' => 'Masculino', 'nome' => 'Modelo padrão'],
                            ['genero' => 'masculino', 'modelo' => 'libero', 'img' => 'uniformeMasculinoLibero.jpg', 'tag' => 'Masculino', 'nome' => 'Modelo líbero'],
                            ['genero' => 'feminino',  'modelo' => 'padrao', 'img' => 'uniformeFemininoPadrao.jpg',  'tag' => 'Feminino',  'nome' => 'Modelo padrão'],
                            ['genero' => 'feminino',  'modelo' => 'libero', 'img' => 'uniformeFemininoLibero.jpg',  'tag' => 'Feminino',  'nome' => 'Modelo líbero'],
                            // Infantil usa a arte masculina: a estampa é a mesma, muda a modelagem.
                            ['genero' => 'infantil',  'modelo' => 'padrao', 'img' => 'uniformeMasculinoPadrao.jpg', 'tag' => 'Infantil',  'nome' => 'Modelo padrão'],
                            ['genero' => 'infantil',  'modelo' => 'libero', 'img' => 'uniformeMasculinoLibero.jpg', 'tag' => 'Infantil',  'nome' => 'Modelo líbero'],
                        ];
                        foreach ($modelos as $m):
                        ?>
                        <label class="pedirUniformeModel" data-genero-card="<?= $m['genero'] ?>">
                            <input type="radio" name="modelo_completo" value="<?= $m['genero'] ?>|<?= $m['modelo'] ?>"
                                   data-genero="<?= $m['genero'] ?>" data-modelo="<?= $m['modelo'] ?>">
                            <span class="pedirUniformeModel__box">
                                <span class="pedirUniformeModel__tag"><?= $m['tag'] ?></span>
                                <img src="<?= BASE_URL ?>/images/uniformes/<?= $m['img'] ?>" alt="Uniforme <?= strtolower($m['tag']) ?> <?= $m['nome'] ?>">
                                <span class="pedirUniformeModel__name"><?= $m['nome'] ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pedirUniforme__block" id="detalhesBlock">
                    <h3><span>4</span> Personalização</h3>

                    <label class="pedirUniforme__field">
                        <span>Nome na camiseta</span>
                        <input type="text" id="nomeCamisa" name="nome_camisa" maxlength="<?= UNIFORME_NOME_MAX ?>"
                               class="input" placeholder="Ex.: MARIANA" autocomplete="off">
                        <small>Até <?= UNIFORME_NOME_MAX ?> caracteres. Vai estampado em caixa alta.</small>
                    </label>

                    <div class="pedirUniforme__field" data-so-completo id="fieldNumero">
                        <span>Número da camiseta</span>
                        <button type="button" class="pedirUniforme__numberPick" id="numberPick">
                            <em id="numberLabel">Escolher número</em>
                        </button>
                        <input type="hidden" id="numeroInput" name="numero" value="">
                        <small id="numeroHintAluno">De 1 a 99, entre os que ainda estão livres na turma/gênero escolhidos.</small>

                        <!--
                            O modal de números busca a disponibilidade pela TURMA do aluno.
                            Professor e equipe MPG não estão em turma nenhuma, então aqui o
                            número é digitado, com a lista de ocupados da equipe como apoio.
                        -->
                        <input type="number" id="numeroEquipe" class="input" min="<?= UNIFORME_NUMERO_MIN ?>"
                               max="<?= UNIFORME_NUMERO_MAX ?>" placeholder="Ex.: 10" style="display:none;">
                        <small id="numeroHintEquipe" style="display:none;"></small>
                    </div>

                    <div class="pedirUniforme__field">
                        <span id="labelTamCamisa">Tamanho da camisa</span>
                        <div class="pedirUniforme__sizes" id="sizesBoxCamisa"></div>
                        <input type="hidden" id="tamanhoCamisaInput" name="tamanho_camisa" value="">
                    </div>

                    <div class="pedirUniforme__field" data-so-completo id="fieldTamShorts">
                        <span id="labelTamShorts">Tamanho do shorts</span>
                        <div class="pedirUniforme__sizes" id="sizesBoxShorts"></div>
                        <input type="hidden" id="tamanhoShortsInput" name="tamanho_shorts" value="">
                        <small>Em dúvida? Confira a <button type="button" class="pedirUniforme__measureLink" id="adminMeasuresOpen">tabela de medidas</button>.</small>
                    </div>
                </div>

                <div class="pedirUniforme__block pedirUniforme__block--summary" id="resumoBlock">
                    <h3><span>5</span> Confirmar pedido</h3>

                    <?php
                    // O pedido não nasce mais pago: quem marca é o admin, e só quando o
                    // dinheiro entrou. "Pago" aqui entra direto em Pagamentos Uniformes, que
                    // é a tela do que caiu em caixa — marcar antes da hora inflava o relatório.
                    ?>
                    <div class="pedirUniforme__field">
                        <span>Pagamento</span>
                        <div class="pedirUniforme__pagamento">
                            <label class="pedirUniformePag">
                                <input type="radio" name="pagamento" value="0" checked>
                                <span class="pedirUniformePag__box">
                                    <strong>Ainda não pago</strong>
                                    <small>Entra na fila de produção aguardando o pagamento. Dá pra marcar como pago depois, na lista de pedidos.</small>
                                </span>
                            </label>
                            <label class="pedirUniformePag">
                                <input type="radio" name="pagamento" value="1">
                                <span class="pedirUniformePag__box">
                                    <strong>Já pago</strong>
                                    <small>Use quando o dinheiro já foi recebido por fora (PIX, dinheiro, link externo).</small>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="pedirUniforme__notice">
                        Valor do pedido: <strong id="resumoValorProduto">R$ <?= number_format($valor, 2, ',', '.') ?></strong>.
                        A cobrança não passa pelo sistema — o pagamento é combinado por fora e marcado aqui.
                    </div>

                    <p class="pedirUniforme__error" id="pedirUniformeError" role="alert"></p>

                    <button type="submit" class="btn btn--primary pedirUniforme__submit" id="pedirUniformeSubmit">Registrar pedido</button>
                </div>

            </form>

        </section>

        <!-- Modal de escolha do número -->
        <div class="uniformNumbers" id="uniformNumbersModal" aria-hidden="true" role="dialog" aria-modal="true">
            <button type="button" class="uniformNumbers__backdrop js-numbers-close" aria-label="Fechar"></button>
            <div class="uniformNumbers__dialog">
                <div class="uniformNumbers__head">
                    <div>
                        <h2>Escolha o número</h2>
                        <p id="uniformNumbersSub">Números disponíveis na turma do aluno.</p>
                    </div>
                    <button type="button" class="uniformNumbers__close js-numbers-close" aria-label="Fechar">&times;</button>
                </div>

                <ul class="uniformNumbers__legend">
                    <li><span class="uniformNumbers__chip uniformNumbers__chip--free"></span> Disponível</li>
                    <li><span class="uniformNumbers__chip uniformNumbers__chip--mine"></span> Já é do aluno</li>
                    <li><span class="uniformNumbers__chip uniformNumbers__chip--taken"></span> Indisponível</li>
                </ul>

                <div class="uniformNumbers__grid" id="uniformNumbersGrid">
                    <p class="uniformNumbers__loading">Carregando números...</p>
                </div>
            </div>
        </div>

        <!-- Modal da tabela de medidas -->
        <div class="adminUniformMeasures" id="adminMeasuresModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="adminMeasuresTitle">
            <button type="button" class="adminUniformMeasures__backdrop js-admin-measures-close" aria-label="Fechar tabela de medidas"></button>
            <div class="adminUniformMeasures__dialog">
                <div class="adminUniformMeasures__head">
                    <div>
                        <span>Uniformes MPG Academy</span>
                        <h2 id="adminMeasuresTitle">Tabela de medidas</h2>
                        <p>Todas as grades do fabricante — camisa, calção e bermuda em masculino, feminino e infantil, mais a regata.</p>
                    </div>
                    <button type="button" class="adminUniformMeasures__close js-admin-measures-close" aria-label="Fechar">&times;</button>
                </div>

                <?php
                // Mesmo componente da área do aluno: as medidas nunca divergem entre as telas.
                include ROOT . '/includes/uniforme_medidas.php';
                ?>
            </div>
        </div>

        <!-- Modal de sucesso -->
        <div class="confirmModal" id="sucessoModal">
            <div class="confirmModal__box">
                <h3>Pedido registrado!</h3>
                <p id="sucessoModalInfo"></p>
                <div class="confirmModal__actions">
                    <a class="btn btn--gray" href="<?= BASE_URL ?>/admin/pediruniforme">Fazer outro pedido</a>
                    <a class="btn btn--primary" href="<?= BASE_URL ?>/admin/uniformes">Ver na lista</a>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL    = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL          = "<?= BASE_URL ?>";
    var UNIFORME_MEDIDAS = <?= json_encode(UNIFORME_MEDIDAS, JSON_UNESCAPED_UNICODE) ?>;
    var UNIFORME_GENERO_LABEL = <?= json_encode(UNIFORME_GENERO_LABEL, JSON_UNESCAPED_UNICODE) ?>;
    var UNIFORME_PRODUTOS     = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE) ?>;
    var UNIFORME_VALORES      = <?= json_encode($valores, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php echo '<script src="' . ADMIN_BASE_URL . '/pages/pediruniforme/pediruniforme.js?v=' . time() . '"></script>'; ?>

<script>
/**
 * Pedido de uniforme para professor / equipe MPG.
 *
 * Convive com o fluxo de aluno (pediruniforme.js) sem mexer nele: o seletor do passo 1
 * troca qual dos dois está visível. O de aluno tem turma, número por turma e cobrança; o de
 * equipe não tem nada disso — a academia banca, e a camisa da equipe técnica não tem número.
 */
(function () {
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

    var blocoAluno  = document.getElementById('alunoBlock');
    var blocoEquipe = document.getElementById('equipeBlock');
    var blocoModelo = document.getElementById('modeloBlock');
    var form        = document.getElementById('pedirUniformeForm');
    if (!blocoEquipe || !form) return;

    var selPessoa = document.getElementById('equipePessoa');
    var selTipo   = document.getElementById('equipeTipo');
    var selCargo  = document.getElementById('equipeCargo');
    var campoCargo = document.getElementById('equipeCargoField');
    var fotoEquipe = document.getElementById('equipeFoto');
    var preview    = document.getElementById('equipeCargoPreview');

    var blocoCorte    = document.getElementById('corteBlock');
    var blocoDetalhes = document.getElementById('detalhesBlock');
    var blocoResumo   = document.getElementById('resumoBlock');
    var avisoPago     = blocoResumo ? blocoResumo.querySelector('.pedirUniforme__notice') : null;
    var avisoPagoHtml = avisoPago ? avisoPago.innerHTML : '';

    var destino = 'aluno';
    var dados   = null;
    var corte   = 'masculino'; // gênero da camisa da equipe técnica

    function escapar(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : t;
        return d.innerHTML;
    }

    // ── Passo 1: alternar entre aluno e equipe ────────────────────────────────
    document.querySelectorAll('[data-destino]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            destino = btn.getAttribute('data-destino');

            document.querySelectorAll('[data-destino]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });

            var ehEquipe = destino === 'equipe';
            blocoAluno.style.display  = ehEquipe ? 'none' : '';
            blocoEquipe.style.display = ehEquipe ? '' : 'none';

            document.body.setAttribute('data-destino-uniforme', destino);

            // O modal de números é do fluxo de aluno (busca pela turma); a equipe digita.
            document.getElementById('numberPick').style.display      = ehEquipe ? 'none' : '';
            document.getElementById('numeroHintAluno').style.display = ehEquipe ? 'none' : '';
            document.getElementById('numeroEquipe').style.display    = ehEquipe ? '' : 'none';
            document.getElementById('numeroHintEquipe').style.display = ehEquipe ? '' : 'none';

            if (ehEquipe) {
                aplicarTipo();
                if (!dados) carregarEquipe();
            } else {
                // Volta pro fluxo de aluno: modelo é dos 4 cards, corte não existe, e o
                // aviso volta a ser o de "pagamento já coletado por fora".
                blocoCorte.style.display    = 'none';
                blocoModelo.style.display   = '';
                blocoDetalhes.style.display = '';
                blocoResumo.style.display   = '';
                if (avisoPago) avisoPago.innerHTML = avisoPagoHtml;
            }
        });
    });

    /**
     * Corte em uso no fluxo de equipe.
     *
     * Vem do passo "Corte da camisa" quando o produto não tem modelos (camisa da equipe
     * técnica), do modelo escolhido quando tem, e é fixo na regata — que é peça única e só
     * precisa de um corte pra saber em que balde de numeração o número entra.
     */
    function generoEquipe() {
        var ficha = fichaTipo();

        if (ficha.corteUnico) return 'masculino';
        if (ficha.tipo === 'equipe_tecnica') return corte;

        var m = form.querySelector('[name="modelo_completo"]:checked');
        return m ? m.getAttribute('data-genero') : '';
    }

    /** Mostra quais números já estão tomados no balde da equipe. */
    function carregarOcupados() {
        var hint   = document.getElementById('numeroHintEquipe');
        var pessoa = selPessoa.value.split(':');
        var genero = generoEquipe();

        if (pessoa.length !== 2 || !genero) {
            hint.textContent = 'Escolha a pessoa e o modelo pra ver os números livres.';
            return;
        }

        var url = ADMIN_BASE_URL + '/services/get_equipe_uniforme.php'
                + '?genero=' + encodeURIComponent(genero)
                + '&pessoa_tipo=' + encodeURIComponent(pessoa[0])
                + '&pessoa_id=' + encodeURIComponent(pessoa[1]);

        hint.textContent = 'Verificando números...';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success || !d.ocupados) {
                    hint.textContent = 'De 1 a 99. O número é validado ao salvar.';
                    return;
                }
                hint.textContent = d.ocupados.length
                    ? 'Já em uso na equipe: ' + d.ocupados.join(', ')
                    : 'Nenhum número em uso na equipe ainda.';
            })
            .catch(function () {
                hint.textContent = 'De 1 a 99. O número é validado ao salvar.';
            });
    }

    /**
     * Ficha do produto escolhido, lida do próprio <option> (que o PHP preencheu a partir de
     * UNIFORME_PRODUTOS). Evita repetir aqui a lista de peças e cortes de cada produto.
     */
    function fichaTipo() {
        var opt = selTipo.options[selTipo.selectedIndex];
        var ler = function (attr) { return (opt.getAttribute(attr) || '').split(','); };

        return {
            tipo:       selTipo.value,
            pecas:      ler('data-pecas'),
            cortes:     ler('data-cortes'),
            corteUnico: opt.getAttribute('data-corte-unico') === '1'
        };
    }

    function tipoTemPeca(peca) {
        return fichaTipo().pecas.indexOf(peca) !== -1;
    }

    /** Peça de cima do produto: regata tem grade própria, o resto usa a da camisa. */
    function pecaDeCimaEquipe() {
        return tipoTemPeca('regata') ? 'regata' : 'camisa';
    }

    /** Mostra os passos que fazem sentido pro produto escolhido. */
    function aplicarTipo() {
        var ficha     = fichaTipo();
        var ehTecnica = ficha.tipo === 'equipe_tecnica';

        campoCargo.style.display  = ehTecnica ? '' : 'none';
        fotoEquipe.style.display  = ehTecnica ? '' : 'none';

        // Equipe técnica escolhe só o corte da camisa; uniforme completo e camisa avulsa
        // escolhem entre os modelos (corte × cor); a regata não escolhe nada — mas o bloco
        // continua na tela, porque é dentro dele que mora o aviso de peça única.
        blocoCorte.style.display  = ehTecnica ? '' : 'none';
        blocoModelo.style.display = ehTecnica ? 'none' : '';

        var cartoes    = document.getElementById('modelosBox');
        var avisoUnico = document.getElementById('produtoUnicoAviso');

        if (cartoes)    cartoes.hidden    = ficha.corteUnico;
        if (avisoUnico) avisoUnico.hidden = !ficha.corteUnico;

        // Número existe em tudo, menos na camisa da equipe técnica (lá vai o cargo).
        // Calção só quando o produto tem calção.
        var campoNumero = document.getElementById('fieldNumero');
        var campoShorts = document.getElementById('fieldTamShorts');
        if (campoNumero) campoNumero.style.display = ehTecnica ? 'none' : '';
        if (campoShorts) campoShorts.style.display = tipoTemPeca('shorts') ? '' : 'none';

        var hint = document.getElementById('equipeTipoHint');
        if (hint) {
            hint.textContent = ehTecnica
                ? 'Só a camisa, com o cargo no lugar do número.'
                : (ficha.corteUnico
                    ? 'Peça única, unissex — sem versão masculina ou feminina.'
                    : (tipoTemPeca('shorts')
                        ? 'Camisa + calção, com nome e número.'
                        : 'A camisa do uniforme sozinha, com nome e número.'));
        }

        // Personalização e confirmação são sempre necessárias. No fluxo de aluno quem libera
        // esses passos é o pediruniforme.js (só depois de escolher aluno e turma); no de
        // equipe não há essa dependência, então liberamos aqui — sem isso o botão de
        // finalizar simplesmente não aparecia.
        blocoDetalhes.style.display = '';
        blocoResumo.style.display   = '';

        // O aviso de "pagamento já confirmado" é do fluxo de aluno. Pedido de equipe é
        // interno: dizer que o valor foi coletado por fora seria mentira.
        if (avisoPago) {
            avisoPago.innerHTML = 'Pedido <strong>interno</strong> da equipe MPG — sem cobrança. '
                                + 'Entra direto na fila de produção.';
        }

        document.body.setAttribute('data-uniforme-tipo', selTipo.value);

        // Corte fixo (equipe técnica e regata) já monta a grade; nos demais quem monta é o
        // modelo escolhido.
        if (ehTecnica || ficha.corteUnico) {
            aplicarTamanhos(ficha.corteUnico ? 'masculino' : corte);
        } else {
            var m = form.querySelector('[name="modelo_completo"]:checked');
            if (m) aplicarTamanhos(m.getAttribute('data-genero'));
        }

        atualizarPreview();
        if (!ehTecnica) carregarOcupados();
    }

    /**
     * Monta os botões de tamanho de uma peça.
     *
     * No fluxo de aluno isso é feito pelo pediruniforme.js, a partir da turma escolhida.
     * O fluxo de equipe não passa por turma nenhuma, então as caixas ficavam vazias — o
     * admin via "Tamanho da camisa" sem nenhuma opção pra clicar.
     */
    function montarTamanhos(idCaixa, idHidden, lista) {
        var caixa  = document.getElementById(idCaixa);
        var hidden = document.getElementById(idHidden);
        if (!caixa || !hidden || !lista) return;

        hidden.value = '';
        caixa.innerHTML = lista.map(function (t) {
            return '<button type="button" class="pedirUniforme__size" data-tam="' + t + '">' + t + '</button>';
        }).join('');

        caixa.querySelectorAll('[data-tam]').forEach(function (b) {
            b.addEventListener('click', function () {
                caixa.querySelectorAll('[data-tam]').forEach(function (o) { o.classList.remove('is-active'); });
                b.classList.add('is-active');
                hidden.value = b.getAttribute('data-tam');
            });
        });
    }

    /** Grade de tamanhos do gênero em uso — corte (equipe técnica) ou modelo (completo). */
    function aplicarTamanhos(genero) {
        if (!dados || !dados.tamanhos[genero]) return;

        var peca = pecaDeCimaEquipe();
        montarTamanhos('sizesBoxCamisa', 'tamanhoCamisaInput', dados.tamanhos[genero][peca]);

        var labelCam = document.getElementById('labelTamCamisa');
        if (labelCam) {
            labelCam.textContent = peca === 'regata'
                ? 'Tamanho da regata'
                : 'Tamanho da camisa ' + (genero === 'feminino' ? 'feminina' : 'masculina');
        }

        // Calção só existe no uniforme completo — nos demais o campo nem aparece.
        if (tipoTemPeca('shorts')) {
            montarTamanhos('sizesBoxShorts', 'tamanhoShortsInput', dados.tamanhos[genero].shorts);

            var labelSh = document.getElementById('labelTamShorts');
            if (labelSh) {
                labelSh.textContent = genero === 'feminino' ? 'Tamanho da bermuda' : 'Tamanho do calção';
            }
        }
    }

    function aplicarCorte() {
        aplicarTamanhos(corte);
    }

    document.querySelectorAll('[data-corte]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            corte = btn.getAttribute('data-corte');
            document.querySelectorAll('[data-corte]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            aplicarCorte();
        });
    });

    // Nos produtos com modelo (completo e camisa avulsa) é ele que define corte e grade.
    form.querySelectorAll('[name="modelo_completo"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var ficha = fichaTipo();
            if (destino !== 'equipe' || ficha.tipo === 'equipe_tecnica' || ficha.corteUnico) return;

            aplicarTamanhos(radio.getAttribute('data-genero'));
            carregarOcupados();
        });
    });

    function carregarEquipe() {
        fetch(ADMIN_BASE_URL + '/services/get_equipe_uniforme.php', { credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (t) {
                    try { return JSON.parse(t); }
                    catch (e) {
                        console.error('Resposta não-JSON em get_equipe_uniforme:', t.slice(0, 500));
                        return { success: false, message: 'Erro ' + r.status + ' ao carregar a equipe.' };
                    }
                });
            })
            .then(function (d) {
                if (!d.success) {
                    selPessoa.innerHTML = '<option value="">' + escapar(d.message || 'Erro') + '</option>';
                    return;
                }
                dados = d;

                // Deixa explícito quem é professor e quem é da equipe MPG — os dois vêm de
                // tabelas diferentes e podem ter o mesmo id.
                selPessoa.innerHTML = '<option value="">Escolha a pessoa…</option>' +
                    d.pessoas.map(function (p) {
                        return '<option value="' + p.tipo + ':' + p.id + '" data-nome="' + escapar(p.nome) + '">'
                             + escapar(p.rotulo + ' · ' + p.nome) + '</option>';
                    }).join('');

                // A grade de tamanhos só existe depois desta resposta — sem isso o passo do
                // corte apareceria com a lista de tamanhos vazia.
                aplicarTipo();
            })
            .catch(function () {
                selPessoa.innerHTML = '<option value="">Erro de conexão</option>';
            });
    }

    function atualizarPreview() {
        var campo = document.getElementById('nomeCamisa');
        var txt   = selCargo.options[selCargo.selectedIndex].textContent;
        // O nome vem do campo de personalização (que o admin pode encurtar pra caber na
        // camisa), não do cadastro — era o que deixava o preview com um traço solto.
        var nome  = (campo && campo.value.trim()) ? campo.value.trim().toUpperCase() : '…';

        preview.innerHTML = 'Vai estampado como: <strong>' + escapar(txt + ' — ' + nome) + '</strong>';
    }

    selTipo.addEventListener('change', aplicarTipo);
    selCargo.addEventListener('change', atualizarPreview);

    // Ao escolher a pessoa, já sugere o primeiro nome dela na camisa — é o que quase sempre
    // vai bordado, e economiza digitação.
    selPessoa.addEventListener('change', function () {
        var opt   = selPessoa.options[selPessoa.selectedIndex];
        var nome  = opt ? (opt.getAttribute('data-nome') || '') : '';
        var campo = document.getElementById('nomeCamisa');

        if (campo && !campo.value.trim() && nome) {
            campo.value = nome.split(' ')[0].toUpperCase();
        }
        atualizarPreview();
        if (selTipo.value !== 'equipe_tecnica') carregarOcupados();
    });

    var campoNome = document.getElementById('nomeCamisa');
    if (campoNome) campoNome.addEventListener('input', atualizarPreview);

    // ── Envio ─────────────────────────────────────────────────────────────────
    // Intercepta na fase de captura pra decidir antes do handler do fluxo de aluno.
    form.addEventListener('submit', function (e) {
        if (destino !== 'equipe') return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var erro = document.getElementById('pedirUniformeError');
        var btn  = document.getElementById('pedirUniformeSubmit');

        function falhar(msg) {
            erro.textContent = msg;
            erro.style.display = '';
            btn.disabled = false;
            btn.textContent = 'Registrar pedido';
        }

        var pessoa = selPessoa.value.split(':');
        if (pessoa.length !== 2) return falhar('Escolha a pessoa do pedido.');

        var ficha     = fichaTipo();
        var ehTecnica = ficha.tipo === 'equipe_tecnica';

        // O corte vem do passo "Corte da camisa", do modelo escolhido, ou é fixo na peça
        // única — generoEquipe() resolve os três casos.
        var genero = generoEquipe();
        var modelo = 'padrao';

        if (!ehTecnica && !ficha.corteUnico) {
            var modeloSel = form.querySelector('[name="modelo_completo"]:checked');
            if (!modeloSel) return falhar('Escolha o modelo do uniforme.');
            modelo = modeloSel.getAttribute('data-modelo');
        }

        if (!genero) return falhar('Escolha o corte do uniforme.');

        if (!document.getElementById('nomeCamisa').value.trim()) {
            return falhar('Informe o nome que vai na camisa.');
        }

        if (!document.getElementById('tamanhoCamisaInput').value) {
            return falhar('Escolha o tamanho ' + (ficha.corteUnico ? 'da regata.' : 'da camisa.'));
        }

        if (!ehTecnica && !document.getElementById('numeroEquipe').value) {
            return falhar('Informe o número da camiseta.');
        }

        if (tipoTemPeca('shorts') && !document.getElementById('tamanhoShortsInput').value) {
            return falhar(genero === 'feminino' ? 'Escolha o tamanho da bermuda.' : 'Escolha o tamanho do calção.');
        }

        var body = new URLSearchParams({
            pessoa_tipo:    pessoa[0],
            pessoa_id:      pessoa[1],
            tipo_uniforme:  selTipo.value,
            genero:         genero,
            modelo:         modelo,
            nome_camisa:    document.getElementById('nomeCamisa').value,
            tamanho_camisa: document.getElementById('tamanhoCamisaInput').value,
            // O número vem do campo da equipe, não do modal do fluxo de aluno.
            numero:         ehTecnica ? '' : document.getElementById('numeroEquipe').value,
            tamanho_shorts: tipoTemPeca('shorts') ? document.getElementById('tamanhoShortsInput').value : '',
            equipe_cargo:   ehTecnica ? selCargo.value : '',
            // Mesma escolha do fluxo de aluno: o pedido da equipe também não nasce pago.
            pago:           (document.querySelector('input[name="pagamento"]:checked') || { value: '0' }).value
        });

        erro.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Registrando...';

        fetch(ADMIN_BASE_URL + '/services/criar_pedido_uniforme_equipe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function (r) {
            return r.text().then(function (t) {
                try { return JSON.parse(t); }
                catch (err) {
                    console.error('Resposta não-JSON ao criar pedido de equipe:', t.slice(0, 500));
                    return { success: false, message: 'O servidor respondeu com erro ' + r.status + '.' };
                }
            });
        })
        .then(function (d) {
            if (!d.success) return falhar(d.message || 'Não foi possível registrar.');

            document.getElementById('sucessoModalInfo').innerHTML =
                escapar(d.message) + '<br><small>' + escapar(d.texto_camisa) + '</small>';
            document.getElementById('sucessoModal').classList.add('confirmModal--open');
            btn.disabled = false;
            btn.textContent = 'Registrar pedido';
        })
        .catch(function () { falhar('Erro de conexão.'); });
    }, true);
}());
</script>

<?php include ROOT . '/includes/lightbox.php'; ?>

</body>
</html>
