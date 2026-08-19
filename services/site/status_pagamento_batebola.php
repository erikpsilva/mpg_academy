<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['jogador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/batebola.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';

$pdo        = getDbConnection();
$jogadorId  = (int) $_SESSION['jogador']['id'];
$dataEvento = batebolaProximoDomingo($pdo);

$st = $pdo->prepare("
    SELECT id, status, mp_payment_id
    FROM batebola_inscricoes
    WHERE jogador_id = ? AND data_evento = ?
");
$st->execute([$jogadorId, $dataEvento]);
$insc   = $st->fetch();
$status = $insc['status'] ?? null;

// Se o banco ainda diz pendente, PERGUNTA AO MERCADO PAGO em vez de só reler o banco.
//
// Antes esta consulta só olhava a tabela, então dependia inteiramente de o webhook ter
// chegado. Quando o webhook falha — e ele falhou: o segredo configurado era o da conta
// antiga, e toda notificação da conta nova voltava 401 "Assinatura inválida" — o jogador
// pagava, o PIX era aprovado no MP, e a tela girava "aguardando confirmação" pra sempre.
// Ele saía da página achando que não tinha dado certo e ficava fora da lista.
//
// verificar_pagamento.php já fazia isso pra mensalidade e uniforme. O Bate Bola era o
// único fluxo sem a checagem.
if ($status === 'pendente' && !empty($insc['mp_payment_id'])) {
    try {
        $payment = mpConsultarPagamento(mpAccessToken($pdo), (string) $insc['mp_payment_id']);

        if ($payment && ($payment['status'] ?? '') === 'approved') {
            batebolaConfirmarInscricao($pdo, (int) $insc['id'], (string) $payment['id'], $payment);
            $status = 'pago';
        }
    } catch (Throwable $e) {
        // Instabilidade do MP não pode derrubar a tela do jogador: segue pendente e a
        // próxima batida do polling tenta de novo.
        error_log('[batebola-status] ' . $e->getMessage());
    }
}

echo json_encode(['success' => true, 'status' => $status ?: 'nenhuma', 'data_evento' => $dataEvento]);
