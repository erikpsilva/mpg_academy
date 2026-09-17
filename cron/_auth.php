<?php

/**
 * Trava de acesso dos crons.
 *
 * A KingHost dispara os crons por HTTP — ela abre a URL do script e manda o header
 * `X-Cron-Auth` com o token do painel. Como estes arquivos ficam dentro da pasta pública,
 * sem esta checagem qualquer pessoa abriria o endereço e dispararia um envio em massa de
 * WhatsApp ou uma rodada de cobrança no cartão.
 *
 * Linha de comando passa direto, sem token: é assim que rodamos manualmente por SSH e é
 * como o cron funcionava no servidor antigo.
 *
 * O token fica em `configuracoes` (chave `cron_auth_token`) e não no código, porque o
 * repositório é público. Trocar o token no painel da KingHost exige atualizar essa linha
 * do banco — e nada além disso.
 */

if (PHP_SAPI === 'cli') return;

require_once dirname(__FILE__, 2) . '/config/database.php';

$__cronEsperado = (string) (getDbConnection()
    ->query("SELECT valor FROM configuracoes WHERE chave = 'cron_auth_token'")
    ->fetchColumn() ?: '');

$__cronRecebido = $_SERVER['HTTP_X_CRON_AUTH'] ?? '';

// hash_equals compara em tempo constante: uma comparação comum vazaria o token aos poucos,
// porque o tempo de resposta muda conforme quantos caracteres iniciais batem.
if ($__cronEsperado === '' || !hash_equals($__cronEsperado, $__cronRecebido)) {
    http_response_code(404);
    exit;
}
