<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Testes Cancelados</title>
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
                    <h2>Testes <span>Cancelados</span></h2>
                    <p>Aulas experimentais que foram canceladas (agendamentos e filas de espera cancelados antes de acontecer).</p>
                </div>
            </div>

            <div id="adminCanceladosBody">
                <div class="adminTodosAlunos__loading">Carregando...</div>
            </div>

        </section>

    </main>
</div>

<!-- Modal: reagendar teste cancelado -->
<div class="adminTodosModal" id="modalReagendar">
    <div class="adminTodosModal__overlay" id="modalReagendarOverlay"></div>
    <div class="adminTodosModal__dialog">
        <div class="adminTodosModal__head">
            <div>
                <h3>Reagendar aula teste</h3>
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
echo '<script src="' . ADMIN_BASE_URL . '/pages/testescancelados/testescancelados.js?v=' . $version . '"></script>';
?>

</body>
</html>
