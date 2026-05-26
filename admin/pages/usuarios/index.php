<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if ($_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Usuários</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="usuarios">

            <div class="row usuarios__header">
                <div class="col-md-8">
                    <h2>Administrar <span>Usuários</span></h2>
                    <p>Usuários com acesso ao painel administrativo.</p>
                </div>
                <div class="col-md-4 usuarios__headerActions">
                    <a href="<?= BASE_URL ?>/admin/cadastrarusuario" class="btn btn--primary">+ Novo Usuário</a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable usuarios__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>CPF</th>
                                    <th>Nível</th>
                                    <th>Cadastrado em</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="usuariosTableBody">
                                <tr><td colspan="7" class="interessados__loading">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </section>

        <!-- Modal de edição -->
        <div class="confirmModal" id="editModal">
            <div class="confirmModal__box editModal__box">
                <h3>Editar Usuário</h3>
                <input type="hidden" id="editId">

                <div class="editModal__form">
                    <label class="editModal__label">
                        <span>Nome completo</span>
                        <input type="text" class="input editModal__input" id="editNome">
                    </label>
                    <label class="editModal__label">
                        <span>E-mail</span>
                        <input type="email" class="input editModal__input" id="editEmail">
                    </label>
                    <label class="editModal__label editModal__label--half">
                        <span>Nível de acesso</span>
                        <select class="input editModal__select" id="editNivel">
                            <option value="admin">Admin</option>
                            <option value="editor">Editor</option>
                            <option value="leitor">Leitor</option>
                        </select>
                    </label>
                    <div class="editModal__divider">
                        <span>Nova senha <em>(deixe em branco para não alterar)</em></span>
                    </div>
                    <label class="editModal__label editModal__label--half">
                        <span>Nova senha</span>
                        <input type="password" class="input editModal__input" id="editSenha" placeholder="6–20 caracteres">
                    </label>
                    <label class="editModal__label editModal__label--half">
                        <span>Confirmar senha</span>
                        <input type="password" class="input editModal__input" id="editSenhaConfirm" placeholder="Repita a senha">
                    </label>
                    <div class="editModal__error" id="editErro"></div>
                </div>

                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="editCancelar">Cancelar</button>
                    <button class="btn btn--primary" id="editSalvar">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmação de exclusão -->
        <div class="confirmModal" id="deleteModal">
            <div class="confirmModal__box">
                <h3>Excluir Usuário</h3>
                <p>Tem certeza que deseja excluir <strong id="deleteNome"></strong>?<br>Esta ação não pode ser desfeita.</p>
                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="deleteCancelar">Cancelar</button>
                    <button class="btn btn--error" id="deleteConfirmar">Sim, excluir</button>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL  = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL        = "<?= BASE_URL ?>";
    var USUARIO_ATUAL   = <?= (int) $_SESSION['usuario']['id'] ?>;
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/usuarios/usuarios.js?v=' . $version . '"></script>';
?>

</body>
</html>
