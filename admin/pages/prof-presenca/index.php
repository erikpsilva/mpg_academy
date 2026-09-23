<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'professor') {
    header('Location: ' . BASE_URL . '/admin/presencateste');
    exit;
}

// Professor: só as turmas vinculadas a ele. A mesma regra é conferida de novo no servidor
// ao marcar presença (admin/services/marcar_presenca_teste.php) — esconder na tela não basta.
require_once ROOT . '/config/database.php';
$stTurmas = getDbConnection()->prepare("SELECT DISTINCT turma_id FROM professor_turmas WHERE professor_id = ?");
$stTurmas->execute([(int) $_SESSION['usuario']['professor_id']]);

$presencaTurmasPermitidas = array_map('intval', $stTurmas->fetchAll(PDO::FETCH_COLUMN));
$presencaRota             = 'prof-presenca';
include ROOT . '/admin/includes/presenca_teste_lista.php';
