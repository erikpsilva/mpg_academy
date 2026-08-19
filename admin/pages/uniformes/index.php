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
                    <p>Todos os pedidos com pagamento confirmado. Avance o status conforme a produção anda.</p>
                </div>
                <div class="col-md-4">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum" id="totalGeral">—</span>
                        <span class="interessados__totalLabel">Pedidos confirmados</span>
                    </div>
                </div>
            </div>

            <div class="uniformes__stats" id="uniformesStats"></div>

            <!-- Quanto custou, separado por produto. Acompanha o filtro de status. -->
            <div class="uniformes__valores" id="uniformesValores"></div>

            <div class="uniformes__filters">
                <button class="uniformes__filter is-active" data-filtro="todos">Todos</button>
                <?php foreach (UNIFORME_STATUS_FLUXO as $s): ?>
                    <button class="uniformes__filter" data-filtro="<?= $s ?>"><?= UNIFORME_STATUS_LABEL[$s] ?></button>
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
                                    <th>Nome</th>
                                    <th>Nº</th>
                                    <th>Cor</th>
                                    <th>Tamanho</th>
                                    <th>Valor</th>
                                    <th class="uniformes__printExclude">Pago em</th>
                                    <th class="uniformes__printExclude">Status</th>
                                    <?php if ($podeEditar): ?><th class="uniformes__printExclude">Ação</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="uniformesTableBody">
                                <tr>
                                    <td colspan="<?= $podeEditar ? 11 : 10 ?>" class="interessados__loading">Carregando...</td>
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
