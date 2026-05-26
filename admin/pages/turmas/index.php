<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Turmas</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="adminTurmas">

            <div class="row adminTurmas__pageHeader">
                <div class="col-md-8">
                    <h2>Turmas</h2>
                    <p>Visualize todas as turmas, vagas disponíveis e ocupação atual.</p>
                </div>
                <div class="col-md-4 adminTurmas__pageHeader__actions">
                    <a href="<?= BASE_URL ?>/admin/quadras" class="btn btn--gray">Ver Quadras</a>
                </div>
            </div>

            <div class="adminTurmas__filters">
                <button class="btn btn--filter is-active" data-filter="todos">Todas</button>
                <button class="btn btn--filter" data-filter="ativa">Ativas</button>
                <button class="btn btn--filter" data-filter="inativa">Inativas</button>
                <button class="btn btn--filter" data-filter="iniciante">Iniciante</button>
                <button class="btn btn--filter" data-filter="intermediario">Intermediário</button>
                <button class="btn btn--filter" data-filter="avancado">Avançado</button>
            </div>

            <div class="adminTurmas__grid" id="adminTurmasGrid">
                <div class="adminTurmas__loading">Carregando turmas...</div>
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

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/turmas/turmas.js?v=' . $version . '"></script>';
?>

</body>
</html>
