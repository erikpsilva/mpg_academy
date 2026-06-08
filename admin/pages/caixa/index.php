<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPG Academy — Caixa</title>
    <?php include ROOT . '/admin/includes/assets.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/air-datepicker@3/air-datepicker.css">
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
                    <p class="caixa__subtitle" id="caixaSubtitle">Carregando...</p>
                </div>
                <div class="caixa__filtro">
                    <div class="caixa__mesWrap">
                        <label class="caixa__mesLabel">Mês</label>
                        <input class="caixa__mesInput" type="text" id="mesCaixa"
                               readonly placeholder="Selecione o mês">
                    </div>
                    <span class="caixa__badge" id="caixaBadge"></span>
                </div>
            </div>

            <!-- Cards de resumo -->
            <div class="caixa__cards">
                <div class="caixa__card caixa__card--entrada">
                    <span class="caixa__cardLabel">Entradas Reais</span>
                    <span class="caixa__cardValor" id="cardEntradas">—</span>
                    <span class="caixa__cardSub" id="cardEntradasSub"></span>
                </div>
                <div class="caixa__card caixa__card--saida">
                    <span class="caixa__cardLabel">Saídas Reais</span>
                    <span class="caixa__cardValor" id="cardSaidas">—</span>
                </div>
                <div class="caixa__card caixa__card--saldo" id="cardSaldoBox">
                    <span class="caixa__cardLabel">Saldo do Mês</span>
                    <span class="caixa__cardValor" id="cardSaldo">—</span>
                    <span class="caixa__cardSub" id="cardSaldoSub"></span>
                </div>
            </div>

            <!-- Detalhes -->
            <div class="caixa__details">

                <!-- Painel Entradas -->
                <div class="caixa__panel">
                    <h2 class="caixa__panelTitle caixa__panelTitle--entrada">↑ Entradas confirmadas</h2>

                    <div class="caixa__section">
                        <div class="caixa__sectionTitle">Mensalidades pagas</div>
                        <div class="caixa__row">
                            <span class="caixa__rowLabel">Total recebido</span>
                            <span class="caixa__rowVal" id="mensPagas">—</span>
                        </div>
                        <div class="caixa__row">
                            <span class="caixa__rowLabel">Quantidade</span>
                            <span class="caixa__rowVal" id="mensQtd">—</span>
                        </div>
                    </div>

                    <div class="caixa__section">
                        <div class="caixa__sectionTitle">Lançamentos de receita</div>
                        <div class="caixa__row">
                            <span class="caixa__rowLabel">Lançamentos manuais</span>
                            <span class="caixa__rowVal" id="receitasLanc">—</span>
                        </div>
                    </div>

                    <div class="caixa__total">
                        <span>Total de entradas</span>
                        <span id="totalEntradas">—</span>
                    </div>
                </div>

                <!-- Painel Saídas -->
                <div class="caixa__panel">
                    <h2 class="caixa__panelTitle caixa__panelTitle--saida">↓ Saídas confirmadas</h2>

                    <div class="caixa__section">
                        <div class="caixa__sectionTitle">Parcelas de dívidas pagas</div>
                        <div class="caixa__row">
                            <span class="caixa__rowLabel">Total pago</span>
                            <span class="caixa__rowVal" id="parcelasTotal">—</span>
                        </div>
                        <div class="caixa__parcelasDetalhe" id="parcelasDetalhe"></div>
                    </div>

                    <div class="caixa__section">
                        <div class="caixa__sectionTitle">Lançamentos de despesa</div>
                        <div class="caixa__row">
                            <span class="caixa__rowLabel">Lançamentos manuais</span>
                            <span class="caixa__rowVal" id="despesasLanc">—</span>
                        </div>
                    </div>

                    <div class="caixa__total caixa__total--saida">
                        <span>Total de saídas</span>
                        <span id="totalSaidas">—</span>
                    </div>
                </div>

            </div><!-- /details -->

        </div><!-- /caixa -->
    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var BASE_URL       = "<?= BASE_URL ?>";
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/air-datepicker@3/air-datepicker.js"></script>
<script src="<?= ADMIN_BASE_URL ?>/pages/caixa/caixa.js?v<?= time() ?>"></script>
</body>
</html>
