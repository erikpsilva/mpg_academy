<?php

/**
 * Rota antiga, escrita errada: /admin/pedirfuniforme.
 *
 * A tela virou /admin/pediruniforme. Este arquivo existe só pra quem tem o endereço velho
 * salvo nos favoritos ou no histórico do navegador não bater em erro 404. Pode ser apagado
 * quando ninguém mais usar o link antigo.
 */

require_once ROOT . '/config/app.php';

header('Location: ' . BASE_URL . '/admin/pediruniforme', true, 301);
exit;
