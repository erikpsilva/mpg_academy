<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Todos Alunos Teste</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="adminTodosAlunos">

            <div class="row adminTodosAlunos__pageHeader">
                <div class="col-md-12">
                    <h2>Todos Alunos <span>Teste</span></h2>
                    <p>Consulta geral de todos os alunos teste — os que ainda vão fazer a aula experimental e os que já fizeram. A gestão de agendamentos fica em <a href="<?= BASE_URL ?>/admin/alunosteste">Alunos Teste</a>.</p>
                </div>
            </div>

            <div id="adminTodosBody">
                <div class="adminTodosAlunos__loading">Carregando...</div>
            </div>

        </section>

    </main>
</div>

<!-- Modal: selecionar turma para fila de espera -->
<div class="adminTodosModal" id="modalFila">
    <div class="adminTodosModal__overlay" id="modalFilaOverlay"></div>
    <div class="adminTodosModal__dialog">
        <div class="adminTodosModal__head">
            <div>
                <h3>Colocar na fila de espera</h3>
                <p id="modalFilaAluno">—</p>
            </div>
            <button class="adminTodosModal__close" id="modalFilaClose">✕</button>
        </div>
        <div class="adminTodosModal__body">
            <label class="adminTodosModal__label">Selecione a turma</label>
            <select class="input" id="modalFilaTurma">
                <option value="">Carregando turmas...</option>
            </select>
            <div class="adminTodosModal__aviso" id="modalFilaAviso"></div>
        </div>
        <div class="adminTodosModal__foot">
            <button class="btn btn--outline" id="modalFilaCancelar">Cancelar</button>
            <button class="btn btn--primary" id="modalFilaConfirmar">Confirmar</button>
        </div>
    </div>
</div>

<!-- Modal: confirmar em qual turma a aula teste foi realizada -->
<div class="adminTodosModal" id="modalRealizar">
    <div class="adminTodosModal__overlay" id="modalRealizarOverlay"></div>
    <div class="adminTodosModal__dialog">
        <div class="adminTodosModal__head">
            <div>
                <h3>Confirmar realização da aula teste</h3>
                <p id="modalRealizarAluno">—</p>
            </div>
            <button class="adminTodosModal__close" id="modalRealizarClose">✕</button>
        </div>
        <div class="adminTodosModal__body">
            <label class="adminTodosModal__label">Em qual turma o aluno fez a aula teste?</label>
            <select class="input" id="modalRealizarTurma">
                <option value="">Carregando turmas...</option>
            </select>
        </div>
        <div class="adminTodosModal__foot">
            <button class="btn btn--outline" id="modalRealizarCancelar">Cancelar</button>
            <button class="btn btn--primary" id="modalRealizarConfirmar">Confirmar realização</button>
        </div>
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
echo '<script src="' . ADMIN_BASE_URL . '/pages/todosalunos-teste/todosalunos-teste.js?v=' . $version . '"></script>';
?>

</body>
</html>
