<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html>
<head>
<title>MPG Academy - Admin - Interessados</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="interessados">

            <div class="row interessados__header">
                <div class="col-md-8">
                    <h2>Consultar <span>Interessados</span></h2>
                    <p>Pessoas que demonstraram interesse em participar do MPG Academy.</p>
                </div>
                <div class="col-md-4">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum" id="totalGeral">—</span>
                        <span class="interessados__totalLabel">Total de interessados</span>
                    </div>
                </div>
            </div>

            <div class="row interessados__filtros">
                <div class="col-md-6">
                    <div class="interessados__searchWrap">
                        <input
                            class="input interessados__search"
                            type="text"
                            id="buscaInteressados"
                            placeholder="Buscar por nome, e-mail ou celular..."
                        />
                    </div>
                </div>
                <div class="col-md-6 interessados__filtrosRight">
                    <span class="interessados__count" id="resultCount"></span>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable interessados__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome completo</th>
                                    <th>E-mail</th>
                                    <th>Celular</th>
                                    <th>Cadastrado em</th>
                                </tr>
                            </thead>
                            <tbody id="interessadosTableBody">
                                <tr>
                                    <td colspan="5" class="interessados__loading">Carregando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row interessados__paginacao" id="paginacaoWrap">
                <div class="col-md-12">
                    <div class="interessados__paginacaoInner" id="paginacaoControles"></div>
                </div>
            </div>

        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/interessados/interessados.js?v' . $version . '"></script>';
?>

</body>
</html>
