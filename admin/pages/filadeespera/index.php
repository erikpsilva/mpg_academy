<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Fila de Espera</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="adminFila">

            <div class="row adminFila__pageHeader">
                <div class="col-md-12">
                    <h2>Fila de <span>Espera</span></h2>
                    <p>Alunos aguardando vaga em turmas lotadas. Promova manualmente quando uma vaga surgir.</p>
                </div>
            </div>

            <div class="adminFila__body" id="adminFilaBody">
                <div class="adminFila__loading">Carregando fila de espera...</div>
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
echo '<script src="' . ADMIN_BASE_URL . '/pages/filadeespera/filadeespera.js?v=' . $version . '"></script>';
?>

</body>
</html>
