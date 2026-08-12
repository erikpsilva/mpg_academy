<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Pagamentos de Uniformes</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="pagUniformes">

            <div class="row pagUniformes__header">
                <div class="col-md-7">
                    <h2>Pagamentos de <span>Uniformes</span></h2>
                    <p>
                        O dinheiro de uniforme fica só aqui — de propósito. Ele não entra no Controle
                        Financeiro pra não se misturar com a receita de mensalidades.
                    </p>
                </div>
                <div class="col-md-5">
                    <label class="pagUniformes__mesLabel" for="filtroMes">Competência</label>
                    <select id="filtroMes" class="pagUniformes__mes">
                        <option value="">Todos os meses</option>
                    </select>
                </div>
            </div>

            <div class="pagUniformes__totais">
                <div class="pagUniformes__card">
                    <span class="pagUniformes__cardNum" id="totalQtd">—</span>
                    <span class="pagUniformes__cardLabel">Uniformes pagos</span>
                </div>
                <div class="pagUniformes__card pagUniformes__card--bruto">
                    <span class="pagUniformes__cardNum" id="totalBruto">—</span>
                    <span class="pagUniformes__cardLabel">Total recebido</span>
                </div>
                <div class="pagUniformes__card pagUniformes__card--taxa">
                    <span class="pagUniformes__cardNum" id="totalTaxa">—</span>
                    <span class="pagUniformes__cardLabel">Taxas Mercado Pago</span>
                </div>
                <div class="pagUniformes__card pagUniformes__card--liquido">
                    <span class="pagUniformes__cardNum" id="totalLiquido">—</span>
                    <span class="pagUniformes__cardLabel">Líquido</span>
                </div>
            </div>

            <div id="pagUniformesLista" class="pagUniformes__lista">
                <p class="pagUniformes__vazio">Carregando...</p>
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

<?php echo '<script src="' . ADMIN_BASE_URL . '/pages/pagamentos-uniformes/pagamentos-uniformes.js?v=' . time() . '"></script>'; ?>
</body>
</html>
