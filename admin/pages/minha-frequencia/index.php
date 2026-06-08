<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'professor') {
    header('Location: ' . ADMIN_BASE_URL . '/inicio'); exit;
}

require_once ROOT . '/config/database.php';
$pdo    = getDbConnection();
$profId = (int) $_SESSION['usuario']['professor_id'];

require_once ROOT . '/admin/pages/frequencia-professor/frequencia_helpers.php';
[$aulas, $porMes, $stats] = buildFrequencia($pdo, $profId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy — Minha Frequência</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
<?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
<main class="adminLayout__content">

<div class="areaProfessor__welcome">
    <div>
        <h1 class="areaProfessor__title">Minha <span>Frequência</span></h1>
        <p class="areaProfessor__sub">Histórico de presença e faltas — <?= date('Y') ?></p>
    </div>
    <span class="areaProfessor__badge">Professor</span>
</div>

<?php renderFrequenciaView($porMes, $stats); ?>

</main>
</div>
<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";</script>
</body>
</html>
