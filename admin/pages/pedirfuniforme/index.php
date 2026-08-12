<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (!in_array($nivel, ['admin', 'editor'], true)) {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/uniformes.php';

$pdo   = getDbConnection();
$valor = uniformeValor($pdo);
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
                    <p>Faça o pedido pelo aluno que teve dificuldade de usar o formulário sozinho. O pagamento entra direto como <strong>já pago</strong> — cobre por fora (link externo, PIX manual etc.) antes de registrar aqui.</p>
                </div>
            </div>

            <form class="pedirUniforme__form" id="pedirUniformeForm" novalidate>

                <div class="pedirUniforme__block">
                    <h3><span>1</span> Aluno</h3>

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

                <div class="pedirUniforme__block" id="modeloBlock" style="display:none;">
                    <h3><span>2</span> Modelo do uniforme</h3>

                    <div class="pedirUniforme__models">
                        <?php
                        $modelos = [
                            ['genero' => 'masculino', 'modelo' => 'padrao', 'img' => 'uniformeMasculinoPadrao.jpg', 'tag' => 'Masculino', 'nome' => 'Modelo padrão'],
                            ['genero' => 'masculino', 'modelo' => 'libero', 'img' => 'uniformeMasculinoLibero.jpg', 'tag' => 'Masculino', 'nome' => 'Modelo líbero'],
                            ['genero' => 'feminino',  'modelo' => 'padrao', 'img' => 'uniformeFemininoPadrao.jpg',  'tag' => 'Feminino',  'nome' => 'Modelo padrão'],
                            ['genero' => 'feminino',  'modelo' => 'libero', 'img' => 'uniformeFemininoLibero.jpg',  'tag' => 'Feminino',  'nome' => 'Modelo líbero'],
                        ];
                        foreach ($modelos as $m):
                        ?>
                        <label class="pedirUniformeModel">
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

                <div class="pedirUniforme__block" id="detalhesBlock" style="display:none;">
                    <h3><span>3</span> Personalização</h3>

                    <label class="pedirUniforme__field">
                        <span>Nome na camiseta</span>
                        <input type="text" id="nomeCamisa" name="nome_camisa" maxlength="<?= UNIFORME_NOME_MAX ?>"
                               class="input" placeholder="Ex.: MARIANA" autocomplete="off">
                        <small>Até <?= UNIFORME_NOME_MAX ?> caracteres. Vai estampado em caixa alta.</small>
                    </label>

                    <div class="pedirUniforme__field">
                        <span>Número da camiseta</span>
                        <button type="button" class="pedirUniforme__numberPick" id="numberPick">
                            <em id="numberLabel">Escolher número</em>
                        </button>
                        <input type="hidden" id="numeroInput" name="numero" value="">
                        <small>De 1 a 99, entre os que ainda estão livres na turma/gênero escolhidos.</small>
                    </div>

                    <div class="pedirUniforme__field">
                        <span id="labelTamCamisa">Tamanho da camisa</span>
                        <div class="pedirUniforme__sizes" id="sizesBoxCamisa"></div>
                        <input type="hidden" id="tamanhoCamisaInput" name="tamanho_camisa" value="">
                    </div>

                    <div class="pedirUniforme__field">
                        <span id="labelTamShorts">Tamanho do shorts</span>
                        <div class="pedirUniforme__sizes" id="sizesBoxShorts"></div>
                        <input type="hidden" id="tamanhoShortsInput" name="tamanho_shorts" value="">
                        <small>Em dúvida? Confira a <button type="button" class="pedirUniforme__measureLink" id="adminMeasuresOpen">tabela de medidas</button>.</small>
                    </div>
                </div>

                <div class="pedirUniforme__block pedirUniforme__block--summary" id="resumoBlock" style="display:none;">
                    <h3><span>4</span> Confirmar pedido</h3>

                    <div class="pedirUniforme__notice">
                        Esse pedido nasce com pagamento já <strong>confirmado</strong> (valor R$ <?= number_format($valor, 2, ',', '.') ?>), sem passar pela cobrança do sistema — use apenas quando o pagamento já foi coletado por fora.
                    </div>

                    <p class="pedirUniforme__error" id="pedirUniformeError" role="alert"></p>

                    <button type="submit" class="btn btn--primary" id="pedirUniformeSubmit">Registrar pedido como pago</button>
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
                        <p>As quatro grades do fabricante — camisa e shorts, masculino e feminino.</p>
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
                    <a class="btn btn--gray" href="<?= BASE_URL ?>/admin/pedirfuniforme">Fazer outro pedido</a>
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
</script>

<?php echo '<script src="' . ADMIN_BASE_URL . '/pages/pedirfuniforme/pedirfuniforme.js?v=' . time() . '"></script>'; ?>

</body>
</html>
