<?php
if (empty($_SESSION['jogador'])) {
    header('Location: ' . BASE_URL . '/batebola');
    exit;
}

require_once ROOT . '/config/database.php';
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM jogadores_batebola WHERE id = ?");
$stmt->execute([$_SESSION['jogador']['id']]);
$perfil = $stmt->fetch();

if (!$perfil) {
    unset($_SESSION['jogador']);
    header('Location: ' . BASE_URL . '/batebola');
    exit;
}

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>Meus Dados — Bate Bola | MPG Academy</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/includes/header/header.php'; ?>

<main class="bateBolaDados">
    <div class="container">
        <a href="<?= BASE_URL ?>/batebolainicio" class="bateBolaDados__back">&#8592; Voltar</a>

        <div class="bateBolaDadosCard">
            <h1>Meus <span>Dados</span></h1>
            <p>Atualize suas informações e sua senha de acesso ao Bate Bola.</p>

            <form id="bateBolaDadosForm" class="bateBolaSignupForm" enctype="multipart/form-data">
                <label class="bateBolaSignup__photo" for="bbDadosFoto">
                    <input type="file" name="foto" id="bbDadosFoto" accept="image/*">
                    <span class="bateBolaSignup__photoPreview">
                        <?php if (!empty($perfil['foto'])): ?>
                            <img src="<?= BASE_URL ?>/<?= e($perfil['foto']) ?>" alt="<?= e($perfil['nome']) ?>">
                        <?php else: ?>
                            <i class="icon-user" aria-hidden="true"></i>
                        <?php endif; ?>
                    </span>
                    <span class="bateBolaSignup__photoAdd" aria-hidden="true">+</span>
                </label>

                <label class="bateBolaSignupField bateBolaSignupField--full">
                    <span>Nome completo <b>*</b></span>
                    <div><i class="icon-user" aria-hidden="true"></i><input type="text" name="nome" value="<?= e($perfil['nome']) ?>" required></div>
                </label>
                <label class="bateBolaSignupField">
                    <span>Celular <b>*</b></span>
                    <div><i class="icon-celphone" aria-hidden="true"></i><input type="tel" name="celular" value="<?= e($perfil['celular']) ?>" required></div>
                </label>
                <label class="bateBolaSignupField">
                    <span>Altura (cm) <b>*</b></span>
                    <div><i class="icon-condicionamentofisico" aria-hidden="true"></i><input type="text" inputmode="numeric" name="altura_cm" value="<?= e($perfil['altura_cm']) ?>" required></div>
                </label>
                <label class="bateBolaSignupField">
                    <span>Sexo <b>*</b></span>
                    <div><i class="icon-user" aria-hidden="true"></i>
                        <select name="sexo" required>
                            <option value="masculino" <?= $perfil['sexo'] === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="feminino" <?= $perfil['sexo'] === 'feminino' ? 'selected' : '' ?>>Feminino</option>
                        </select>
                    </div>
                </label>
                <label class="bateBolaSignupField bateBolaSignupField--full">
                    <span>E-mail <b>*</b></span>
                    <div><i class="icon-mail" aria-hidden="true"></i><input type="email" name="email" value="<?= e($perfil['email']) ?>" required></div>
                </label>
                <label class="bateBolaSignupField">
                    <span>Nova senha</span>
                    <div><i class="icon-areadoaluno" aria-hidden="true"></i><input type="password" name="senha" placeholder="Preencha só se quiser trocar" autocomplete="new-password"></div>
                </label>
                <label class="bateBolaSignupField">
                    <span>Confirmar nova senha</span>
                    <div><i class="icon-check" aria-hidden="true"></i><input type="password" name="confirmar_senha" placeholder="Repita a nova senha" autocomplete="new-password"></div>
                </label>

                <button class="bateBolaSignupForm__submit" type="submit">Salvar alterações <i class="icon-go" aria-hidden="true"></i></button>
                <small class="bateBolaSignupForm__status" id="bbDadosStatus"></small>
            </form>
        </div>
    </div>
</main>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
<script>var BASE_URL = "<?= BASE_URL ?>";</script>
<script src="<?= BASE_URL ?>/pages/batebolameusdados/batebolameusdados.js?v=<?= time() ?>"></script>
</body>
</html>
