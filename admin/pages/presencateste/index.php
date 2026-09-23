<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
// Admin: todas as turmas. A página em si vive em admin/includes/presenca_teste_lista.php,
// compartilhada com a área do professor (admin/pages/prof-presenca).
$presencaTurmasPermitidas = null;
$presencaRota             = 'presencateste';
include ROOT . '/admin/includes/presenca_teste_lista.php';
