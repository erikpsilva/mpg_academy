<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPG Academy — Previsão Financeira</title>
    <?php include ROOT . '/admin/includes/assets.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/air-datepicker@3/air-datepicker.css">
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>

    <main class="adminLayout__content">
        <div class="previsao">

            <div class="previsao__header">
                <div>
                    <h1 class="previsao__title">Previsão Financeira</h1>
                    <p class="previsao__subtitle" id="previsaoSubtitle">Carregando...</p>
                </div>
                <div class="previsao__filtros">
                    <div class="previsao__filtroRapido">
                        <button class="previsao__btnPeriodo previsao__btnPeriodo--active" data-meses="1">Este mês</button>
                        <button class="previsao__btnPeriodo" data-meses="3">3 meses</button>
                        <button class="previsao__btnPeriodo" data-meses="6">6 meses</button>
                        <button class="previsao__btnPeriodo" data-meses="12">12 meses</button>
                    </div>
                    <div class="previsao__mesWrap">
                        <label class="previsao__mesLabel">Período personalizado</label>
                        <input class="previsao__mesInput" type="text" id="mesPeriodo"
                               readonly placeholder="Selecione o intervalo de meses">
                    </div>
                </div>
            </div>

            <!-- Cards de resumo -->
            <div class="previsao__cards">
                <div class="previsao__card previsao__card--entrada">
                    <span class="previsao__cardLabel">Total de Entradas</span>
                    <span class="previsao__cardValor" id="cardEntradas">—</span>
                    <span class="previsao__cardSub" id="cardEntradasSub"></span>
                </div>
                <div class="previsao__card previsao__card--saida">
                    <span class="previsao__cardLabel">Total de Saídas</span>
                    <span class="previsao__cardValor" id="cardSaidas">—</span>
                </div>
                <div class="previsao__card previsao__card--saldo" id="cardSaldoBox">
                    <span class="previsao__cardLabel">Saldo Previsto</span>
                    <span class="previsao__cardValor" id="cardSaldo">—</span>
                    <span class="previsao__cardSub" id="cardSaldoSub"></span>
                </div>
            </div>

            <!-- Detalhes -->
            <div class="previsao__details">

                <!-- Painel Entradas -->
                <div class="previsao__panel">
                    <h2 class="previsao__panelTitle previsao__panelTitle--entrada">↑ Entradas</h2>

                    <div class="previsao__section">
                        <div class="previsao__sectionTitle">Mensalidades</div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel"><span class="previsao__dot previsao__dot--ok"></span>Pagas</span>
                            <span class="previsao__rowVal" id="mensPagas">—</span>
                        </div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel"><span class="previsao__dot previsao__dot--pend"></span>Pendentes</span>
                            <span class="previsao__rowVal" id="mensPendentes">—</span>
                        </div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel"><span class="previsao__dot previsao__dot--atr"></span>Atrasadas</span>
                            <span class="previsao__rowVal" id="mensAtrasadas">—</span>
                        </div>
                    </div>

                    <div class="previsao__section">
                        <div class="previsao__sectionTitle">Patrocínios</div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel">Ativos × período</span>
                            <span class="previsao__rowVal" id="patrocinios">—</span>
                        </div>
                    </div>

                    <div class="previsao__section">
                        <div class="previsao__sectionTitle">Outros lançamentos</div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel">Lançamentos manuais</span>
                            <span class="previsao__rowVal" id="receitasLanc">—</span>
                        </div>
                    </div>

                    <div class="previsao__total">
                        <span>Total de entradas</span>
                        <span id="totalEntradas">—</span>
                    </div>
                </div>

                <!-- Painel Saídas -->
                <div class="previsao__panel">
                    <h2 class="previsao__panelTitle previsao__panelTitle--saida">↓ Saídas</h2>

                    <div class="previsao__section">
                        <div class="previsao__sectionTitle">Aluguel das Quadras</div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel">Quadras ativas × período</span>
                            <span class="previsao__rowVal" id="aluguelQuadras">—</span>
                        </div>
                    </div>

                    <div class="previsao__section">
                        <div class="previsao__sectionTitle">Professores</div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel">Salários × período</span>
                            <span class="previsao__rowVal" id="salarios">—</span>
                        </div>
                    </div>

                    <div class="previsao__section">
                        <div class="previsao__sectionTitle">Parcelas de Dívidas</div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel"><span class="previsao__dot previsao__dot--pend"></span>Pendentes</span>
                            <span class="previsao__rowVal" id="parcelasPend">—</span>
                        </div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel"><span class="previsao__dot previsao__dot--ok"></span>Já pagas</span>
                            <span class="previsao__rowVal" id="parcelasPagas">—</span>
                        </div>
                        <div class="previsao__parcelasDetalhe" id="parcelasDetalhe"></div>
                    </div>

                    <div class="previsao__section">
                        <div class="previsao__sectionTitle">Outros lançamentos</div>
                        <div class="previsao__row">
                            <span class="previsao__rowLabel">Lançamentos manuais</span>
                            <span class="previsao__rowVal" id="despesasLanc">—</span>
                        </div>
                    </div>

                    <div class="previsao__total previsao__total--saida">
                        <span>Total de saídas</span>
                        <span id="totalSaidas">—</span>
                    </div>
                </div>

            </div><!-- /details -->

        </div><!-- /previsao -->
    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var BASE_URL       = "<?= BASE_URL ?>";
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/air-datepicker@3/air-datepicker.js"></script>
<script src="<?= ADMIN_BASE_URL ?>/pages/previsao/previsao.js?v<?= time() ?>"></script>
</body>
</html>
