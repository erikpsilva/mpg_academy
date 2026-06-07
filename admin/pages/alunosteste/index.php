<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Alunos Teste</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
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
            </div>
            <div class="adminTesteModal__row">
                <div class="adminTesteModal__field">
                    <label>Data da aula</label>
                    <input class="input" type="date" id="testeData">
                </div>
            </div>
            <label class="adminTesteModal__menorCheck">
                <input type="checkbox" id="testeMenorCheck">
                <span>É menor de idade</span>
            </label>
            <!-- Seção responsável (aparece ao marcar menor de idade) -->
            <div class="adminTesteModal__responsavel" id="testeResponsavelSection" style="display:none;">
                <div class="adminTesteModal__responsavelLabel">👤 Dados do responsável</div>
                <div class="adminTesteModal__field">
                    <label>Data de Nascimento do aluno</label>
                    <input class="input" type="date" id="testeDataNasc">
                </div>
                <div class="adminTesteModal__field">
                    <label>Nome do responsável <span>*</span></label>
                    <input class="input" type="text" id="testeRespNome" placeholder="Nome completo do pai/mãe/responsável">
                </div>
                <div class="adminTesteModal__row">
                    <div class="adminTesteModal__field">
                        <label>E-mail do responsável</label>
                        <input class="input" type="email" id="testeRespEmail" placeholder="email@responsavel.com">
                    </div>
                    <div class="adminTesteModal__field">
                        <label>CPF do responsável</label>
                        <input class="input" type="text" id="testeRespCpf" placeholder="000.000.000-00" maxlength="14">
                    </div>
                </div>
                <div class="adminTesteModal__field">
                    <label>Celular do responsável <span title="Usado para notificações no WhatsApp">📱</span></label>
                    <input class="input" type="tel" id="testeRespCelular" placeholder="(11) 99999-9999">
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

<!-- Modal: editar aluno teste -->
<div class="adminTesteModal" id="adminTesteEditModal">
    <div class="adminTesteModal__overlay" id="adminTesteEditOverlay"></div>
    <div class="adminTesteModal__dialog">
        <div class="adminTesteModal__head">
            <div>
                <h3>Editar aluno teste</h3>
                <p>Altere os dados e salve.</p>
            </div>
            <button class="adminTesteModal__close" id="adminTesteEditClose">✕</button>
        </div>
        <form class="adminTesteModal__body" id="adminTesteEditForm" novalidate>
            <input type="hidden" id="editId">
            <div class="adminTesteModal__field">
                <label>Nome <span>*</span></label>
                <input class="input" type="text" id="editNome" placeholder="Nome completo" required>
            </div>
            <div class="adminTesteModal__row">
                <div class="adminTesteModal__field">
                    <label>E-mail</label>
                    <input class="input" type="email" id="editEmail" placeholder="email@exemplo.com">
                </div>
                <div class="adminTesteModal__field">
                    <label>Celular</label>
                    <input class="input" type="tel" id="editCelular" placeholder="(11) 99999-9999">
                </div>
            </div>
            <div class="adminTesteModal__row">
                <div class="adminTesteModal__field adminTesteModal__field--turma">
                    <label>Turma <span>*</span></label>
                    <select class="input" id="editTurma" required>
                        <option value="">Carregando...</option>
                    </select>
                </div>
            </div>
            <div class="adminTesteModal__row">
                <div class="adminTesteModal__field">
                    <label>Data da aula</label>
                    <input class="input" type="date" id="editData">
                </div>
            </div>
            <label class="adminTesteModal__menorCheck">
                <input type="checkbox" id="editMenorCheck">
                <span>É menor de idade</span>
            </label>
            <div class="adminTesteModal__responsavel" id="editResponsavelSection" style="display:none;">
                <div class="adminTesteModal__responsavelLabel">👤 Dados do responsável</div>
                <div class="adminTesteModal__field">
                    <label>Data de Nascimento do aluno</label>
                    <input class="input" type="date" id="editDataNasc">
                </div>
                <div class="adminTesteModal__field">
                    <label>Nome do responsável</label>
                    <input class="input" type="text" id="editRespNome" placeholder="Nome completo do pai/mãe/responsável">
                </div>
                <div class="adminTesteModal__row">
                    <div class="adminTesteModal__field">
                        <label>E-mail do responsável</label>
                        <input class="input" type="email" id="editRespEmail" placeholder="email@responsavel.com">
                    </div>
                    <div class="adminTesteModal__field">
                        <label>CPF do responsável</label>
                        <input class="input" type="text" id="editRespCpf" placeholder="000.000.000-00" maxlength="14">
                    </div>
                </div>
                <div class="adminTesteModal__field">
                    <label>Celular do responsável <span title="Usado para notificações no WhatsApp">📱</span></label>
                    <input class="input" type="tel" id="editRespCelular" placeholder="(11) 99999-9999">
                </div>
            </div>
            <div class="adminTesteModal__foot">
                <button type="button" class="adminTesteModal__cancel" id="adminTesteEditCancelBtn">Cancelar</button>
                <button type="submit" class="btn btn--primary" id="adminTesteEditSubmitBtn">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: assinar pela escola -->
<div class="adminTesteModal" id="adminTesteAssinarModal">
    <div class="adminTesteModal__overlay" id="adminTesteAssinarOverlay"></div>
    <div class="adminTesteModal__dialog adminTesteModal__dialog--sm">
        <div class="adminTesteModal__head">
            <div>
                <h3>Assinar como Escola</h3>
                <p>Selecione o responsável que assina pela MPG Academy.</p>
            </div>
            <button class="adminTesteModal__close" id="adminTesteAssinarClose">✕</button>
        </div>
        <div class="adminTesteModal__body">
            <input type="hidden" id="assinarAulaId">
            <div class="adminTesteModal__field">
                <label>Responsável pela escola <span>*</span></label>
                <select class="input" id="assinarAdminSelect">
                    <option value="">Carregando admins...</option>
                </select>
            </div>
            <div class="adminTesteModal__assPreview" id="assinarPreview"></div>
            <div class="adminTesteModal__foot">
                <button type="button" class="adminTesteModal__cancel" id="adminTesteAssinarCancelBtn">Cancelar</button>
                <button type="button" class="btn btn--primary" id="adminTesteAssinarSubmitBtn">✍ Confirmar assinatura</button>
            </div>
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
echo '<script src="' . ADMIN_BASE_URL . '/pages/alunosteste/alunosteste.js?v=' . $version . '"></script>';
?>

</body>
</html>
