<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPG Academy — Área do Professor</title>
    <?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>

    <main class="adminLayout__content">
        <div class="areaProfessor">

            <!-- Boas-vindas -->
            <div class="areaProfessor__welcome" id="apWelcome">
                <div>
                    <h1 class="areaProfessor__title">Olá, <span id="apNome">—</span></h1>
                    <p class="areaProfessor__sub">Aqui você acompanha suas turmas, ganhos e dados pessoais.</p>
                </div>
                <span class="areaProfessor__badge">Professor</span>
            </div>

            <!-- Cards de resumo -->
            <div class="areaProfessor__cards">
                <div class="areaProfessor__card areaProfessor__card--ganhos">
                    <span class="areaProfessor__cardLabel">Ganhos este mês (até hoje)</span>
                    <span class="areaProfessor__cardValor" id="apGanhosHoje">—</span>
                    <span class="areaProfessor__cardSub">aulas realizadas</span>
                </div>
                <div class="areaProfessor__card areaProfessor__card--proj">
                    <span class="areaProfessor__cardLabel">Projeção (aulas restantes)</span>
                    <span class="areaProfessor__cardValor" id="apProjecao">—</span>
                    <span class="areaProfessor__cardSub" id="apProjecaoSub">até o pagamento</span>
                </div>
                <div class="areaProfessor__card areaProfessor__card--total">
                    <span class="areaProfessor__cardLabel">Total esperado no pagamento</span>
                    <span class="areaProfessor__cardValor" id="apTotal">—</span>
                    <span class="areaProfessor__cardSub" id="apDiaPgto">—</span>
                    <span class="areaProfessor__cardNote" id="apBonusNote" style="display:none"></span>
                </div>
            </div>

            <!-- Turmas -->
            <h2 class="areaProfessor__sectionTitle">Minhas Turmas</h2>
            <div class="areaProfessor__turmas" id="apTurmas">
                <p class="areaProfessor__loading">Carregando...</p>
            </div>

            <!-- Dados pessoais + alterar senha -->
            <h2 class="areaProfessor__sectionTitle areaProfessor__sectionTitle--spaced">Meus Dados</h2>
            <div class="areaProfessor__perfil">

                <div class="areaProfessor__perfilDados" id="apDados">
                    <div class="areaProfessor__dado"><span>E-mail</span><strong id="apEmail">—</strong></div>
                    <div class="areaProfessor__dado"><span>Celular</span><strong id="apCelular">—</strong></div>
                    <div class="areaProfessor__dado"><span>Valor aula 1h30</span><strong id="apValor90">—</strong></div>
                    <div class="areaProfessor__dado"><span>Valor aula 2h00</span><strong id="apValor120">—</strong></div>
                    <div class="areaProfessor__dado"><span>Dia de pagamento</span><strong id="apDiaPgtoInfo">—</strong></div>
                    <div class="areaProfessor__dado" id="apBonusRow" style="display:none"><span id="apBonusTituloLabel">Adicional mensal</span><strong id="apBonusValorInfo">—</strong></div>
                </div>

                <div class="areaProfessor__senhaBox">
                    <h3 class="areaProfessor__senhaTitle">Alterar Senha</h3>
                    <form id="formSenha" class="areaProfessor__senhaForm">
                        <input type="password" name="senha_atual"     class="input" placeholder="Senha atual" required>
                        <input type="password" name="senha_nova"      class="input" placeholder="Nova senha (mín. 6 caracteres)" required>
                        <input type="password" name="senha_confirmar" class="input" placeholder="Confirmar nova senha" required>
                        <button type="submit" class="btn btn--primary" id="btnSenha">Alterar senha</button>
                        <p class="areaProfessor__senhaMsg" id="senhaMsg"></p>
                    </form>
                </div>

            </div><!-- /perfil -->

        </div><!-- /areaProfessor -->
    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var BASE_URL       = "<?= BASE_URL ?>";
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
</script>
<script src="<?= ADMIN_BASE_URL ?>/pages/area-professor/area-professor.js?v<?= time() ?>"></script>
</body>
</html>
