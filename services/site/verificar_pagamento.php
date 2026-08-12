<?php

/**
 * Job de verificação pós-pagamento.
 *
 * Roda logo depois de toda tentativa de pagamento (e em polling, no caso do PIX) pra
 * perguntar ao Mercado Pago qual é a situação real da cobrança e conciliar no banco.
 *
 * Existe porque confirmar o pagamento não pode depender de um único canal:
 *  - a resposta síncrona do checkout pode se perder (aba fechada, rede caindo, timeout);
 *  - o webhook pode não chegar ou ser rejeitado.
 * Aqui o próprio navegador do aluno puxa a confirmação, fechando esses dois buracos.
 *
 * Nunca confia no que o front manda: reconsulta o pagamento na API do MP com o nosso
 * access token e só marca como pago o que o MP responder como `approved`.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';
require_once dirname(__FILE__, 3) . '/config/batebola.php';

/** Resposta padrão — sempre JSON, mesmo em falha, pra nunca travar o front. */
function responder(array $dados): void
{
    echo json_encode($dados);
    exit;
}

try {
    $contexto = trim($_REQUEST['contexto'] ?? 'mensalidade');
    $refId    = (int) ($_REQUEST['referencia_id'] ?? 0);

    if ($refId <= 0 || !in_array($contexto, ['mensalidade', 'uniforme', 'batebola'], true)) {
        http_response_code(400);
        responder(['success' => false, 'message' => 'Dados insuficientes.']);
    }

    $pdo = getDbConnection();

    // ── Autorização + estado atual, por contexto ────────────────────────────────
    $mpPaymentId = null;
    $jaPago      = false;

    if ($contexto === 'batebola') {
        if (empty($_SESSION['jogador'])) {
            http_response_code(403);
            responder(['success' => false, 'message' => 'Acesso não autorizado.']);
        }

        $st = $pdo->prepare("SELECT mp_payment_id, status FROM batebola_inscricoes WHERE id = ? AND jogador_id = ?");
        $st->execute([$refId, (int) $_SESSION['jogador']['id']]);
        $row = $st->fetch();

        if (!$row) {
            http_response_code(404);
            responder(['success' => false, 'message' => 'Inscrição não encontrada.']);
        }

        $mpPaymentId = $row['mp_payment_id'];
        $jaPago      = $row['status'] === 'pago';

    } else {
        if (empty($_SESSION['aluno'])) {
            http_response_code(403);
            responder(['success' => false, 'message' => 'Acesso não autorizado.']);
        }
        $alunoId = (int) $_SESSION['aluno']['id'];

        if ($contexto === 'mensalidade') {
            $st = $pdo->prepare("SELECT mp_payment_id, status FROM mensalidades WHERE id = ? AND aluno_id = ?");
            $st->execute([$refId, $alunoId]);
            $row = $st->fetch();

            if (!$row) {
                http_response_code(404);
                responder(['success' => false, 'message' => 'Cobrança não encontrada.']);
            }

            $mpPaymentId = $row['mp_payment_id'];
            $jaPago      = $row['status'] === 'pago';

        } else { // uniforme
            $st = $pdo->prepare("SELECT mp_payment_id, status_pagamento FROM pedidos_uniforme WHERE id = ? AND aluno_id = ?");
            $st->execute([$refId, $alunoId]);
            $row = $st->fetch();

            if (!$row) {
                http_response_code(404);
                responder(['success' => false, 'message' => 'Pedido não encontrado.']);
            }

            $mpPaymentId = $row['mp_payment_id'];
            $jaPago      = $row['status_pagamento'] === 'pago';
        }
    }

    // Já conciliado — nada a fazer, resposta rápida (é o caso comum no polling).
    if ($jaPago) {
        responder(['success' => true, 'pago' => true, 'status' => 'approved', 'ja_estava' => true]);
    }

    $accessToken = mpAccessToken($pdo);
    $payment     = null;

    // 1) Caminho normal: já sabemos o id do pagamento.
    if (!empty($mpPaymentId)) {
        $payment = mpConsultarPagamento($accessToken, (string) $mpPaymentId);
    }

    // 2) Fallback: a resposta do checkout se perdeu antes de salvarmos o id. Procura pelo
    //    external_reference que gravamos na criação — pega sempre a tentativa mais recente.
    if (!$payment) {
        $externalRef = $contexto . '-' . $refId;
        $busca = mpRequest($accessToken, 'GET',
            '/v1/payments/search?external_reference=' . urlencode($externalRef) . '&sort=date_created&criteria=desc&limit=1');
        $payment = $busca['body']['results'][0] ?? null;
    }

    if (!$payment) {
        responder([
            'success' => true,
            'pago'    => false,
            'status'  => 'nao_encontrado',
            'message' => 'Nenhuma cobrança localizada no Mercado Pago para esse item.',
        ]);
    }

    $status   = $payment['status'] ?? '';
    $detalhe  = $payment['status_detail'] ?? null;
    $pagId    = (string) ($payment['id'] ?? $mpPaymentId);

    if ($status === 'approved') {
        // Concilia. As três funções são idempotentes: se o webhook já tiver confirmado
        // no meio tempo, elas simplesmente não fazem nada.
        if ($contexto === 'mensalidade') {
            mpMarcarMensalidadePaga($pdo, $refId, $pagId, $payment);
        } elseif ($contexto === 'uniforme') {
            uniformeConfirmarPedido($pdo, $refId, $pagId, $payment);
        } else {
            batebolaConfirmarInscricao($pdo, $refId, $pagId, $payment);
        }

        responder(['success' => true, 'pago' => true, 'status' => 'approved']);
    }

    // ── Recusa que só apareceu DEPOIS da resposta do checkout ───────────────────
    //
    // O MP às vezes responde `in_process` na hora e rejeita segundos depois (antifraude).
    // Nesse cenário criar_pagamento.php já respondeu "em análise" e nunca soube da recusa:
    // o aluno ficava esperando uma confirmação que não vinha, e o erro não aparecia em
    // admin/erros-pagamento. Como aqui já reconsultamos o pagamento no MP, é o lugar certo
    // pra fechar esse buraco. mpRegistrarErroPagamento() ignora repetição do mesmo
    // mp_payment_id, então o polling pode chamar quantas vezes for.
    $recusado = in_array($status, ['rejected', 'cancelled'], true);
    $motivo   = $recusado ? mpMotivoPt($detalhe) : null;

    if ($recusado) {
        $rotulos = ['mensalidade' => 'Mensalidade', 'uniforme' => 'Pedido de uniforme', 'batebola' => 'Bate Bola'];

        mpRegistrarErroPagamento($pdo, [
            'aluno_id'         => $contexto === 'batebola' ? null : ($alunoId ?? null),
            'aluno_nome'       => $_SESSION['aluno']['nome']  ?? ($_SESSION['jogador']['nome']  ?? null),
            'aluno_email'      => $_SESSION['aluno']['email'] ?? ($_SESSION['jogador']['email'] ?? null),
            'contexto'         => $contexto,
            'referencia_id'    => $refId,
            'referencia_label' => ($rotulos[$contexto] ?? 'Cobrança') . ' — recusa confirmada na verificação',
            'valor'            => isset($payment['transaction_amount']) ? (float) $payment['transaction_amount'] : null,
            'metodo'           => $payment['payment_method_id'] ?? null,
            'parcelas'         => isset($payment['installments']) ? (int) $payment['installments'] : null,
            'origem'           => 'site',
            'mp_payment_id'    => $pagId,
            'mp_status'        => $status,
            'mp_status_detail' => $detalhe,
            'mensagem'         => $motivo,
            'detalhe_tecnico'  => 'Detectado pela verificação pós-pagamento (o MP respondeu '
                                . 'in_process/pending no checkout e recusou depois). status_detail: ' . ($detalhe ?: 'sem detalhe'),
        ]);

        // Guardar o id de um pagamento recusado na cobrança confunde o admin e faz esta
        // própria verificação reconsultar pra sempre a tentativa velha em vez de achar uma
        // nova pelo external_reference. Só limpa se ainda for o id da tentativa recusada —
        // se o aluno já tentou de novo no meio tempo, o id novo tem que ser preservado.
        try {
            if ($contexto === 'mensalidade') {
                $pdo->prepare("UPDATE mensalidades SET mp_payment_id = NULL WHERE id = ? AND mp_payment_id = ? AND status != 'pago'")
                    ->execute([$refId, $pagId]);
            } elseif ($contexto === 'uniforme') {
                $pdo->prepare("UPDATE pedidos_uniforme SET mp_payment_id = NULL WHERE id = ? AND mp_payment_id = ? AND status_pagamento != 'pago'")
                    ->execute([$refId, $pagId]);
            }
        } catch (PDOException $e) {}
    }

    responder([
        'success'       => true,
        'pago'          => false,
        'status'        => $status ?: 'desconhecido',
        'status_detail' => $detalhe,
        'recusado'      => $recusado,
        'motivo'        => $motivo,
        'acao'          => $recusado ? mpAcaoSugerida($detalhe) : null,
    ]);

} catch (Throwable $e) {
    error_log('[verificar-pagamento] ' . $e->getMessage());
    http_response_code(500);
    responder(['success' => false, 'message' => 'Não foi possível verificar o pagamento agora.']);
}
