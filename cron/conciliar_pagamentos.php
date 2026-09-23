<?php
/**
 * CRON — Conciliação com o Mercado Pago (Bate Bola, uniformes e mensalidades).
 * KingHost: a cada 10 minutos → https://www.mpgacademy.com.br/cron/conciliar_pagamentos.php
 *
 * Confirma no sistema os pagamentos que o MP aprovou mas cujo aviso (webhook) não chegou —
 * ex.: PIX pago com a tela de pagamento já fechada. A lógica vive em
 * config/conciliacao_mp.php. Seguro rodar quantas vezes for: só confirma o que o MP
 * responde como aprovado, e nunca confirma o mesmo item duas vezes.
 */

define('CRON_RUN', true);

// Recusa quem abrir a URL sem o header da KingHost. Ver cron/_auth.php.
require_once dirname(__FILE__) . '/_auth.php';
require_once dirname(__FILE__, 2) . '/config/app.php';
require_once dirname(__FILE__, 2) . '/config/database.php';
require_once dirname(__FILE__, 2) . '/config/conciliacao_mp.php';

$pdo = getDbConnection();

// forçar = true: o cron já tem o próprio intervalo. 40s de orçamento — a KingHost corta em 60s.
$res = mpConciliarPendentes($pdo, ['batebola', 'uniforme', 'mensalidade'], true, 40);

$linha = sprintf('[%s] conciliação MP — bate bola: %d, uniformes: %d, mensalidades: %d',
    date('Y-m-d H:i:s'), count($res['batebola']), count($res['uniforme']), count($res['mensalidade']));

if (PHP_SAPI === 'cli') {
    echo $linha . PHP_EOL;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $linha;
}
