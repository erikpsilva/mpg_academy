<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Todos Agendamentos Experimentais</title>
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
                    <h2>Todos Agendamentos <span>Experimentais</span></h2>
                    <p>Consulta geral de quem já fez a aula experimental e de quem teve o teste cancelado. Os agendamentos em aberto ficam em <a href="<?= BASE_URL ?>/admin/alunosteste">Agendar Aula Experimental</a>.</p>
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

<!-- Modal: reagendar aula experimental cancelada -->
<div class="adminTodosModal" id="modalReagendar">
    <div class="adminTodosModal__overlay" id="modalReagendarOverlay"></div>
    <div class="adminTodosModal__dialog">
        <div class="adminTodosModal__head">
            <div>
                <h3>Reagendar aula experimental</h3>
                <p id="modalReagendarAluno">—</p>
            </div>
            <button class="adminTodosModal__close" id="modalReagendarClose">✕</button>
        </div>
        <div class="adminTodosModal__body">
            <label class="adminTodosModal__label">Turma</label>
            <select class="input" id="modalReagendarTurma">
                <option value="">Carregando turmas...</option>
            </select>

            <label class="adminTodosModal__label">Nova data do teste</label>
            <input type="date" class="input" id="modalReagendarData">

            <div class="adminTodosModal__aviso" style="display:block">
                Ao confirmar, o aluno (e o responsável, se for menor de idade) recebe automaticamente uma mensagem no WhatsApp avisando que a aula foi reagendada.
            </div>
        </div>
        <div class="adminTodosModal__foot">
            <button class="btn btn--outline" id="modalReagendarCancelar">Cancelar</button>
            <button class="btn btn--primary" id="modalReagendarConfirmar">Reagendar e avisar no WhatsApp</button>
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
