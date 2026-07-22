<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPG Academy — Caixa</title>
    <?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>

    <main class="adminLayout__content">
        <div class="caixa">

            <div class="caixa__header">
                <div>
                    <h1 class="caixa__title">Caixa</h1>
                    <p class="caixa__subtitle">Saldo acumulado e dívidas em aberto da MPG Academy.</p>
                </div>
            </div>

            <!-- Cards de resumo -->
            <div class="caixa__cards">
                <div class="caixa__card caixa__card--entrada">
                    <span class="caixa__cardLabel">Total de Entradas</span>
                    <span class="caixa__cardValor" id="cardEntradas">—</span>
                </div>
                <div class="caixa__card caixa__card--saida">
                    <span class="caixa__cardLabel">Total de Saídas</span>
                    <span class="caixa__cardValor" id="cardSaidas">—</span>
                </div>
                <div class="caixa__card caixa__card--saldo" id="cardSaldoBox">
                    <span class="caixa__cardLabel">Saldo em Caixa</span>
                    <span class="caixa__cardValor" id="cardSaldo">—</span>
                </div>
                <div class="caixa__card caixa__card--divida">
                    <span class="caixa__cardLabel">Dívida em Aberto</span>
                    <span class="caixa__cardValor" id="cardDivida">—</span>
                    <a class="caixa__cardLink" href="<?= ADMIN_BASE_URL ?>/financeiro?aba=dividas">Ver detalhes →</a>
                </div>
                <div class="caixa__card caixa__card--divida">
                    <span class="caixa__cardLabel">Professores a Pagar</span>
                    <span class="caixa__cardValor" id="cardProfessores">—</span>
                    <a class="caixa__cardLink" href="<?= ADMIN_BASE_URL ?>/professores">Ver professores →</a>
                </div>
            </div>

        </div><!-- /caixa -->
    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var BASE_URL       = "<?= BASE_URL ?>";
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
</script>
<script src="<?= ADMIN_BASE_URL ?>/pages/caixa/caixa.js?v<?= time() ?>"></script>
</body>
</html>
