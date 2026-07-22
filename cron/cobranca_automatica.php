<?php
/**
 * CRON — Cobrança automática de mensalidades via cartão salvo
 * Configurar no cPanel: duas vezes por dia (recomendado 07:00 e 15:00)
 * Comando: php /home/SEU_USUARIO/public_html/mpg_academy/cron/cobranca_automatica.php
 *
 * Cobra alunos com auto_pagamento=1 e cartão salvo (mp_customer_id/mp_card_id),
 * para mensalidades pendentes/atrasadas já vencidas (vencimento <= hoje).
 * Roda todo dia (não só perto do vencimento no dia 10) para também tentar novamente quem está
 * atrasado, até o pagamento ser confirmado. Nunca cobra a mesma mensalidade com SUCESSO duas
 * vezes no mesmo dia — mas uma tentativa que FALHOU continua elegível pra nova tentativa no
 * mesmo dia (por isso vale rodar mais de uma vez, ex.: manhã e tarde), ver
 * mpExecutarCobrancaAutomatica() em config/mercadopago.php.
 *
 * A lógica em si vive em mpExecutarCobrancaAutomatica() (config/mercadopago.php), que também
 * é chamada por um fallback em admin/includes/auth_check.php — se esse cron do cPanel não
 * estiver configurado ou falhar num dia, o próximo admin que logar no painel já dispara a
 * mesma cobrança, então isso nunca depende só do agendamento externo.
 */

define('CRON_RUN', true);
require_once dirname(__FILE__, 2) . '/config/app.php';
require_once dirname(__FILE__, 2) . '/config/database.php';
require_once dirname(__FILE__, 2) . '/config/mercadopago.php';

$pdo = getDbConnection();
$res = mpExecutarCobrancaAutomatica($pdo);

echo "Cobrança automática — sucesso: {$res['sucesso']}, falha: {$res['falha']}\n";
foreach ($res['detalhes_falha'] as $d) {
    echo "  FALHA — {$d['aluno']}: {$d['motivo']}\n";
}
