<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/uniformes.php';

$podeEditar = in_array($_SESSION['usuario']['nivel_acesso'] ?? '', ['admin', 'editor'], true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Uniformes</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="uniformes">

            <div class="row uniformes__header">
                <div class="col-md-8">
                    <h2>Pedidos de <span>Uniforme</span></h2>
                    <p>Pedidos com pagamento confirmado, separados por etapa. A impressão sai com o que estiver na aba aberta — em <strong>A pedir</strong> você imprime só o que falta mandar pra confecção.</p>
                </div>
                <div class="col-md-4">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum" id="totalGeral">—</span>
                        <span class="interessados__totalLabel">Pedidos confirmados</span>
                    </div>
                </div>
            </div>

            <nav class="uniformHubNav" aria-label="Navegação de uniformes">
                <a class="uniformHubNav__link is-active" href="<?= ADMIN_BASE_URL ?>/uniformes">
                    <span class="uniformHubNav__icon">01</span>
                    <span><strong>Pedidos</strong><small>Acompanhar produção</small></span>
                </a>
                <a class="uniformHubNav__link" href="<?= ADMIN_BASE_URL ?>/pediruniforme">
                    <span class="uniformHubNav__icon">02</span>
                    <span><strong>Novo pedido</strong><small>Aluno ou equipe</small></span>
                </a>
                <a class="uniformHubNav__link" href="<?= ADMIN_BASE_URL ?>/pagamentos-uniformes">
                    <span class="uniformHubNav__icon">03</span>
                    <span><strong>Pagamentos</strong><small>Valores recebidos</small></span>
                </a>
            </nav>

            <div class="uniformes__stats" id="uniformesStats"></div>

            <!-- Quanto custou, separado por produto. Acompanha o filtro de status. -->
            <div class="uniformes__valores" id="uniformesValores"></div>

            <?php
            // Abas: o que ainda dá trabalho de um lado, o que já saiu do outro. Os grupos
            // vivem em config/uniformes.php pra tela e regra nunca divergirem.
            ?>
            <div class="uniformes__tabs" role="tablist">
                <?php foreach (UNIFORME_STATUS_GRUPOS as $chave => $grupo): ?>
                <?php // A primeira aba é a que abre: "A pedir", que é o trabalho pendente. ?>
                <button class="uniformes__tab<?= $chave === array_key_first(UNIFORME_STATUS_GRUPOS) ? ' is-active' : '' ?>"
                        data-grupo="<?= $chave ?>"
                        data-status="<?= implode(',', $grupo['status']) ?>"
                        data-vazio="<?= htmlspecialchars($grupo['vazio']) ?>"
                        title="<?= htmlspecialchars($grupo['resumo']) ?>">
                    <?= htmlspecialchars($grupo['rotulo']) ?>
                    <span class="uniformes__tabCount" data-contador="<?= $chave ?>">—</span>
                </button>
                <?php endforeach; ?>
                <button class="uniformes__tab" data-grupo="todos" data-status="<?= implode(',', UNIFORME_STATUS_FLUXO) ?>"
                        data-vazio="Nenhum pedido confirmado ainda."
                        title="Todos os pedidos, em qualquer etapa.">
                    Todos
                    <span class="uniformes__tabCount" data-contador="todos">—</span>
                </button>
            </div>

            <div class="uniformes__filters">
                <button class="uniformes__filter is-active" data-filtro="todos">Todos</button>
                <?php foreach (UNIFORME_STATUS_FLUXO as $s): ?>
                    <button class="uniformes__filter" data-filtro="<?= $s ?>" data-grupo="<?= uniformeGrupoDoStatus($s) ?>"><?= UNIFORME_STATUS_LABEL[$s] ?></button>
                <?php endforeach; ?>
                <?php if ($podeEditar): ?>
                <button class="btn btn--gray btn--sm uniformes__enviarTodos" id="btnEnviarTodos" type="button">
                    &rarr; Enviar todos para confecção
                </button>
                <?php endif; ?>
                <button class="btn btn--primary btn--sm uniformes__imprimir" id="btnImprimir" type="button">
                    Imprimir lista (PDF)
                </button>
            </div>
            <!-- Só aparece no papel: a tela já tem esses dados no topo. -->
            <div class="uniformes__printHead">
                <h1>MPG Academy — Pedido de uniformes</h1>
                <p>
                    Emitido em <?= date('d/m/Y') ?> &middot;
                    Filtro: <span id="printFiltro">Todos</span> &middot;
                    <span id="printTotal"></span>
                </p>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable uniformes__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th class="uniformes__printExclude">Aluno</th>
                                    <th class="uniformes__printExclude">Turma</th>
                                    <th class="uniformes__printExclude">Uniforme</th>
                                    <?php // Sai no papel: é o que diz à confecção o que costurar. ?>
                                    <th>Produto</th>
                                    <th>Nome</th>
                                    <th>Nº</th>
                                    <th>Cor</th>
                                    <th>Tamanho</th>
                                    <?php // Valor é controle interno: não vai pro papel que a confecção recebe. ?>
                                    <th class="uniformes__printExclude">Valor</th>
                                    <th class="uniformes__printExclude">Pago em</th>
                                    <th class="uniformes__printExclude">Status</th>
                                    <?php if ($podeEditar): ?><th class="uniformes__printExclude">Ação</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="uniformesTableBody">
                                <tr>
                                    <td colspan="<?= $podeEditar ? 12 : 11 ?>" class="interessados__loading">Carregando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </section>

        <!-- Modal de correção do pedido -->
        <div class="confirmModal" id="editarModal">
            <div class="confirmModal__box confirmModal__box--wide">
                <h3>Corrigir pedido</h3>
                <p id="editarModalInfo"></p>

                <div class="uniformes__editAviso" id="editarModalAviso" style="display:none;"></div>

                <form id="editarForm" class="uniformes__editForm">
                    <input type="hidden" id="editarPedidoId">

                    <label class="uniformes__editField uniformes__editField--wide">
                        <span>Nome na camisa</span>
                        <input type="text" id="editarNome" autocomplete="off" required>
                        <small id="editarNomeHint"></small>
                    </label>

                    <label class="uniformes__editField">
                        <span>Número</span>
                        <input type="number" id="editarNumero" min="1" max="99" required>
                        <small id="editarNumeroHint"></small>
                    </label>

                    <label class="uniformes__editField">
                        <span id="editarCamisaLabel">Tamanho da camisa</span>
                        <select id="editarTamanhoCamisa" required></select>
                    </label>

                    <label class="uniformes__editField">
                        <span id="editarShortsLabel">Tamanho do calção</span>
                        <select id="editarTamanhoShorts" required></select>
                    </label>

                    <p class="uniformes__editErro" id="editarErro" style="display:none;"></p>
                </form>

                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="editarModalFechar" type="button">Cancelar</button>
                    <button class="btn btn--primary" id="editarModalSalvar" type="button">Salvar correção</button>
                </div>
            </div>
        </div>

        <!-- Confirmação do envio em massa -->
        <div class="confirmModal" id="enviarTodosModal">
            <div class="confirmModal__box">
                <h3>Enviar todos para confecção</h3>
                <p id="enviarTodosInfo"></p>
                <p class="uniformes__editErro" id="enviarTodosErro" style="display:none;"></p>
                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="enviarTodosCancelar" type="button">Cancelar</button>
                    <button class="btn btn--primary" id="enviarTodosConfirmar" type="button">Sim, enviar</button>
                </div>
            </div>
        </div>

        <!-- Confirmação da exclusão -->
        <div class="confirmModal" id="excluirModal">
            <div class="confirmModal__box">
                <h3>Excluir pedido</h3>
                <p>Confira o pedido antes de excluir:</p>

                <div class="uniformes__excluirResumo" id="excluirModalInfo"></div>

                <div class="uniformes__editAviso" id="excluirAviso" style="display:none;"></div>

                <p class="uniformes__excluirNota">
                    O pedido sai da lista e da tela de pagamentos, e o número volta a ficar livre
                    na turma. O registro continua no banco, então dá pra recuperar se for engano.
                </p>

                <p class="uniformes__editErro" id="excluirErro" style="display:none;"></p>

                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="excluirCancelar" type="button">Cancelar</button>
                    <button class="btn btn--error" id="excluirConfirmar" type="button">Sim, excluir</button>
                </div>
            </div>
        </div>

        <!-- Modal de mudança de status -->
        <div class="confirmModal" id="statusModal">
            <div class="confirmModal__box">
                <h3>Mudar status do pedido</h3>
                <p id="statusModalInfo"></p>
                <div class="uniformes__statusOptions" id="statusModalOptions"></div>
                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="statusModalFechar">Fechar</button>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL       = "<?= BASE_URL ?>";
    var PODE_EDITAR    = <?= $podeEditar ? 'true' : 'false' ?>;
</script>

<?php echo '<script src="' . ADMIN_BASE_URL . '/pages/uniformes/uniformes.js?v=' . time() . '"></script>'; ?>

</body>
</html>
