<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html>
<head>
<title>MPG Academy - Admin - Bate Bola</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="jogadores">

            <div class="row jogadores__header">
                <div class="col-md-8">
                    <h2>Bate Bola — <span>Jogadores</span></h2>
                    <p>Jogadores cadastrados no Bate Bola.</p>
                </div>
                <div class="col-md-4">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum" id="totalGeral">—</span>
                        <span class="interessados__totalLabel">Total de jogadores</span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable jogadores__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Foto</th>
                                    <th>Nome</th>
                                    <th>Altura</th>
                                    <th>Nível</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="jogadoresTableBody">
                                <tr>
                                    <td colspan="6" class="interessados__loading">Carregando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </section>

        <!-- Modal editar jogador -->
        <div class="confirmModal" id="editarModal">
            <div class="confirmModal__box cobrancaModal__box">
                <h3>Editar Jogador</h3>
                <div class="jogadores__fotoWrap">
                    <span class="jogadores__fotoPreview" id="editarFotoPreview"><i class="icon-user" aria-hidden="true"></i></span>
                </div>
                <div class="jogadores__dados">
                    <div><span>Nome</span><strong id="editarNome"></strong></div>
                    <div><span>E-mail</span><strong id="editarEmail"></strong></div>
                    <div><span>Celular</span><strong id="editarCelular"></strong></div>
                    <div><span>Altura</span><strong id="editarAltura"></strong></div>
                </div>

                <div class="cobrancaModal__form">
                    <label class="cobrancaModal__label">
                        <span>Nova senha</span>
                        <input type="text" id="editarSenhaNova" class="input cobrancaModal__input"
                               placeholder="Mínimo 6 caracteres" maxlength="100" autocomplete="off">
                    </label>
                </div>
                <div id="editarSenhaMsg" class="cobrancaModal__msg"></div>

                <div class="confirmModal__actions cobrancaModal__actions">
                    <button class="btn btn--gray" id="editarFechar">Fechar</button>
                    <?php if (($_SESSION['usuario']['nivel_acesso'] ?? '') === 'admin'): ?>
                    <button class="btn btn--error" id="editarExcluirBtn">Excluir Jogador</button>
                    <button class="btn btn--primary" id="editarSalvarSenha">Salvar nova senha</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modal de confirmação de exclusão -->
        <div class="confirmModal" id="confirmModal">
            <div class="confirmModal__box">
                <h3>Excluir Jogador</h3>
                <p>Tem certeza que deseja excluir <strong id="confirmNome"></strong>?<br>Esta ação não pode ser desfeita.</p>
                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="confirmCancelar">Cancelar</button>
                    <button class="btn btn--error" id="confirmExcluir">Sim, excluir</button>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include ROOT . '/includes/lightbox.php'; ?>
<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL       = "<?= BASE_URL ?>";
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/batebola/batebola.js?v=' . $version . '"></script>';
?>

</body>
</html>
