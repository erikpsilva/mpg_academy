<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Erros de Pagamento</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="errosPagamento">

            <div class="row errosPagamento__header">
                <div class="col-md-8">
                    <h2>Erros de <span>Pagamento</span></h2>
                    <p>Toda vez que um aluno tenta pagar e não passa, o motivo fica registrado aqui — com o que orientar ele a fazer.</p>
                </div>
                <div class="col-md-4">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum" id="totalPendentes">—</span>
                        <span class="interessados__totalLabel">Em aberto</span>
                    </div>
                </div>
            </div>

            <div class="errosPagamento__filters">
                <button class="errosPagamento__filter is-active" data-filtro="pendentes">Em aberto</button>
                <button class="errosPagamento__filter" data-filtro="todos">Todos (inclui resolvidos)</button>
            </div>

            <div id="errosLista" class="errosPagamento__lista">
                <p class="errosPagamento__vazio">Carregando...</p>
            </div>

        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL       = "<?= BASE_URL ?>";
</script>

<?php echo '<script src="' . ADMIN_BASE_URL . '/pages/erros-pagamento/erros-pagamento.js?v=' . time() . '"></script>'; ?>

</body>
</html>
