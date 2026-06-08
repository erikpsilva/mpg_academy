<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
// Somente admin/editor
if (($_SESSION['usuario']['nivel_acesso'] ?? '') === 'professor') {
    header('Location: ' . ADMIN_BASE_URL . '/area-professor'); exit;
}

require_once ROOT . '/config/database.php';
$pdo    = getDbConnection();
$profId = (int) ($_GET['prof_id'] ?? 0);

if (!$profId) {
    header('Location: ' . ADMIN_BASE_URL . '/professores'); exit;
}

$stProf = $pdo->prepare("SELECT id, nome, sobrenome, email FROM professores WHERE id = ?");
$stProf->execute([$profId]);
$prof = $stProf->fetch(PDO::FETCH_ASSOC);
if (!$prof) {
    header('Location: ' . ADMIN_BASE_URL . '/professores'); exit;
}

require_once ROOT . '/admin/pages/frequencia-professor/frequencia_helpers.php';
[, $porMes, $stats] = buildFrequencia($pdo, $profId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy — Frequência de <?= htmlspecialchars($prof['nome']) ?></title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
<?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
<main class="adminLayout__content">

<div class="areaProfessor__welcome">
    <div>
        <h1 class="areaProfessor__title">Frequência — <span><?= htmlspecialchars($prof['nome'] . ' ' . $prof['sobrenome']) ?></span></h1>
        <p class="areaProfessor__sub"><?= htmlspecialchars($prof['email']) ?> &mdash; <?= date('Y') ?></p>
    </div>
    <a href="<?= ADMIN_BASE_URL ?>/professores" class="btn btn--gray">← Voltar</a>
</div>

<?php renderFrequenciaView($porMes, $stats); ?>

</main>
</div>
<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";</script>
</body>
</html>
