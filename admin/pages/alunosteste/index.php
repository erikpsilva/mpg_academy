<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Alunos Teste</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="adminTeste">

            <div class="row adminTeste__pageHeader">
                <div class="col-md-8">
                    <h2>Alunos <span>Teste</span></h2>
                    <p>Aulas experimentais agendadas e fila de espera para teste. A soma de alunos + testes agendados não ultrapassa o limite da turma.</p>
                </div>
                <div class="col-md-4 adminTeste__pageHeader__actions">
                    <button class="btn btn--primary" id="btnNovoTeste">+ Novo aluno teste</button>
                </div>
            </div>

            <div class="adminTeste__body" id="adminTesteBody">
                <div class="adminTeste__loading">Carregando...</div>
            </div>

        </section>

    </main>
</div>

<!-- Modal: cadastrar aluno teste -->
<div class="adminTesteModal" id="adminTesteModal">
    <div class="adminTesteModal__overlay" id="adminTesteModalOverlay"></div>
    <div class="adminTesteModal__dialog">
        <div class="adminTesteModal__head">
            <div>
                <h3>Novo aluno teste</h3>
                <p>Preencha os dados e escolha a turma.</p>
            </div>
            <button class="adminTesteModal__close" id="adminTesteModalClose">✕</button>
        </div>
        <form class="adminTesteModal__body" id="adminTesteForm" novalidate>
            <div class="adminTesteModal__field">
                <label>Nome <span>*</span></label>
                <input class="input" type="text" id="testeNome" placeholder="Nome completo" required>
            </div>
            <div class="adminTesteModal__row">
                <div class="adminTesteModal__field">
                    <label>E-mail</label>
                    <input class="input" type="email" id="testeEmail" placeholder="email@exemplo.com">
                </div>
                <div class="adminTesteModal__field">
                    <label>Celular</label>
                    <input class="input" type="tel" id="testeCelular" placeholder="(11) 99999-9999">
                </div>
            </div>
            <div class="adminTesteModal__row">
                <div class="adminTesteModal__field adminTesteModal__field--turma">
                    <label>Turma <span>*</span></label>
                    <select class="input" id="testeTurma" required>
                        <option value="">Carregando turmas...</option>
                    </select>
                </div>
                <div class="adminTesteModal__field">
                    <label>Data da aula</label>
                    <input class="input" type="date" id="testeData">
                </div>
            </div>
            <div class="adminTesteModal__aviso" id="testeAviso"></div>
            <div class="adminTesteModal__foot">
                <button type="button" class="adminTesteModal__cancel" id="adminTesteCancelBtn">Cancelar</button>
                <button type="submit" class="btn btn--primary" id="adminTesteSubmitBtn">Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL       = "<?= BASE_URL ?>";
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/alunosteste/alunosteste.js?v=' . $version . '"></script>';
?>

</body>
</html>
