<?php

/**
 * Cria uma preferência de Checkout Pro e devolve o link de pagamento.
 *
 * Caminho alternativo ao Checkout Transparente: em vez do formulário de cartão dentro do
 * site, o aluno é levado à página do Mercado Pago, paga com cartão/PIX/boleto e volta.
 *
 * Existe porque o MP bloqueou a criação de pagamento direto nesta conta em 19/08/2026
 * (403 PA_UNAUTHORIZED_RESULT_FROM_POLICIES), enquanto a preferência seguiu liberada.
 *
 * A confirmação NÃO depende do aluno voltar: o webhook (services/site/mp_webhook.php)
 * identifica a cobrança pelo external_reference e dá baixa sozinho. O retorno do navegador
 * e o botão "Sincronizar MP" são as outras duas redes de segurança.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e) {
    error_log('[criar-preferencia] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar o pagamento: ' . $e->getMessage()]);
});

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';
require_once dirname(__FILE__, 3) . '/config/app.php';

$pdo      = getDbConnection();
$contexto = trim($_POST['contexto'] ?? 'mensalidade');
$refId    = (int) ($_POST['referencia_id'] ?? 0);

if ($refId <= 0 || !in_array($contexto, ['mensalidade', 'uniforme', 'batebola'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados insuficientes.']);
    exit;
}

// ── Autorização + valor, sempre do servidor ───────────────────────────────────
//
// O valor NUNCA vem do navegador: é lido da própria cobrança. Caso contrário daria pra
// pagar R$ 1,00 numa mensalidade de R$ 180 só editando a requisição.
$titulo  = '';
$valor   = 0.0;
$email   = '';
$voltar  = BASE_URL;

if ($contexto === 'batebola') {
    if (empty($_SESSION['jogador'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
        exit;
    }

    $st = $pdo->prepare("
        SELECT bi.valor, bi.status, bi.data_evento, j.email
        FROM batebola_inscricoes bi
        JOIN jogadores_batebola j ON j.id = bi.jogador_id
        WHERE bi.id = ? AND bi.jogador_id = ?
    ");
    $st->execute([$refId, (int) $_SESSION['jogador']['id']]);
    $row = $st->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Inscrição não encontrada.']);
        exit;
    }
    if ($row['status'] === 'pago') {
        echo json_encode(['success' => false, 'message' => 'Essa inscrição já está paga.']);
        exit;
    }

    $valor  = (float) $row['valor'];
    $titulo = 'MPG Academy — Bate Bola ' . (new DateTime($row['data_evento']))->format('d/m/Y');
    $email  = (string) $row['email'];
    $voltar = BASE_URL . '/batebolainicio';

} else {
    if (empty($_SESSION['aluno'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
        exit;
    }
    $alunoId = (int) $_SESSION['aluno']['id'];

    if ($contexto === 'mensalidade') {
        $st = $pdo->prepare("
            SELECT m.valor, m.vencimento, m.status, m.referencia, m.tipo, m.descricao, a.email
            FROM mensalidades m
            JOIN alunos a ON a.id = m.aluno_id
            WHERE m.id = ? AND m.aluno_id = ?
        ");
        $st->execute([$refId, $alunoId]);
        $row = $st->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Cobrança não encontrada.']);
            exit;
        }
        if ($row['status'] === 'pago') {
            echo json_encode(['success' => false, 'message' => 'Essa cobrança já está paga.']);
            exit;
        }

        // Atrasado paga multa e juros, igual ao fluxo transparente.
        $valor = $row['status'] === 'atrasado'
            ? mpCalcularMultaJuros((float) $row['valor'], $row['vencimento'])['total']
            : (float) $row['valor'];

        $titulo = ($row['tipo'] ?? 'mensalidade') === 'avulso'
            ? 'MPG Academy — ' . ($row['descricao'] ?: 'Cobrança')
            : 'MPG Academy — Mensalidade ' . $row['referencia'];

        $email  = (string) $row['email'];
        $voltar = BASE_URL . '/mensalidades';

    } else { // uniforme
        $st = $pdo->prepare("
            SELECT p.valor, p.status_pagamento, p.nome_camisa, p.numero, a.email
            FROM pedidos_uniforme p
            JOIN alunos a ON a.id = p.aluno_id
            WHERE p.id = ? AND p.aluno_id = ?
        ");
        $st->execute([$refId, $alunoId]);
        $row = $st->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
            exit;
        }
        if ($row['status_pagamento'] === 'pago') {
            echo json_encode(['success' => false, 'message' => 'Esse pedido já está pago.']);
            exit;
        }

        $valor  = (float) $row['valor'];
        $titulo = 'MPG Academy — Uniforme ' . $row['nome_camisa']
                . ($row['numero'] !== null ? ' #' . $row['numero'] : '');
        $email  = (string) $row['email'];
        $voltar = BASE_URL . '/areadoaluno#meus-uniformes';
    }
}

if ($valor <= 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Valor inválido para cobrança.']);
    exit;
}

// ── Preferência ───────────────────────────────────────────────────────────────
//
// external_reference é o que amarra o pagamento à cobrança — é por ele que o webhook e a
// verificação encontram o que dar baixa (ver mpResolverReferencia). O metadata vai junto
// como atalho, mas não dá pra depender dele no Checkout Pro.
$externalRef = $contexto . '-' . $refId;

// auto_return faz o aluno voltar sozinho depois de aprovar. O MP exige uma back_url
// pública pra aceitar — em ambiente local (localhost) ele recusa a preferência inteira,
// então lá o aluno volta clicando, e a confirmação segue igual pelo webhook.
$preferencia = [
    'items' => [[
        'title'       => $titulo,
        'quantity'    => 1,
        'unit_price'  => round($valor, 2),
        'currency_id' => 'BRL',
    ]],
    'payer'              => ['email' => $email],
    'external_reference' => $externalRef,
    'metadata'           => [
        'contexto'      => $contexto,
        'referencia_id' => $refId,
    ],
    'back_urls' => [
        'success' => $voltar,
        'pending' => $voltar,
        'failure' => $voltar,
    ],
    'statement_descriptor' => 'MPG ACADEMY',
];

// Bate Bola é só PIX, por decisão da academia — cartão fica de fora do checkout.
// Sem isso o Mercado Pago ofereceria crédito e débito junto.
if ($contexto === 'batebola') {
    $preferencia['payment_methods'] = [
        'excluded_payment_types' => [
            // Bate Bola é só PIX. O Mercado Pago não permite excluir account_money
            // (saldo da carteira dele) — o resto sai, inclusive a Linha de Crédito,
            // que aparecia como opção e é crédito na prática.
            ['id' => 'credit_card'],  ['id' => 'debit_card'],
            ['id' => 'ticket'],       ['id' => 'atm'],
            ['id' => 'digital_wallet'], ['id' => 'prepaid_card'],
            ['id' => 'voucher_card'],  ['id' => 'digital_currency'],
            ['id' => 'crypto_transfer'], ['id' => 'consumer_credits'],
        ],
        'installments' => 1,
    ];
}

if (!APP_IS_LOCAL) {
    $preferencia['auto_return'] = 'approved';
}

$resposta = mpCriarPreferencia(mpAccessToken($pdo), $preferencia);

if ($resposta['http_code'] >= 300 || empty($resposta['body']['init_point'])) {
    $motivo = mpMotivoApiPt($resposta['body'])
              ?? ($resposta['body']['message'] ?? 'Não foi possível preparar o pagamento.');

    mpRegistrarErroPagamento($pdo, [
        'aluno_id'         => $contexto === 'batebola' ? null : ($alunoId ?? null),
        'aluno_nome'       => $_SESSION['aluno']['nome'] ?? ($_SESSION['jogador']['nome'] ?? null),
        'aluno_email'      => $email,
        'contexto'         => $contexto,
        'referencia_id'    => $refId,
        'referencia_label' => $titulo,
        'valor'            => $valor,
        'metodo'           => 'checkout_pro',
        'origem'           => 'site',
        'http_code'        => $resposta['http_code'],
        'mensagem'         => $motivo,
        'detalhe_tecnico'  => mpExtrairErroApi($resposta['body']),
    ]);

    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $motivo]);
    exit;
}

echo json_encode([
    'success'    => true,
    'init_point' => $resposta['body']['init_point'],
    'preference' => $resposta['body']['id'],
    'valor'      => round($valor, 2),
]);
