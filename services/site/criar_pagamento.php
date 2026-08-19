<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// O checkout do Mercado Pago faz `response.json()` nesta resposta. Se um warning/notice do
// PHP vazar junto, o JSON quebra, o parse falha e a barra de carregamento do Brick fica
// girando pra sempre — sem erro nenhum pro aluno. Por isso: nada de saída fora do JSON, e
// qualquer erro fatal vira JSON válido via shutdown handler.
@ini_set('display_errors', '0');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        error_log('[criar-pagamento-fatal] ' . $e['message'] . ' em ' . $e['file'] . ':' . $e['line']);
        echo json_encode([
            'success' => false,
            'status'  => 'erro_servidor',
            'message' => 'Erro interno ao processar o pagamento. Tente novamente ou use o PIX.',
        ]);
    }
});

set_exception_handler(function (Throwable $ex) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    error_log('[criar-pagamento-exception] ' . $ex->getMessage());
    echo json_encode([
        'success' => false,
        'status'  => 'erro_servidor',
        'message' => 'Erro interno ao processar o pagamento. Tente novamente ou use o PIX.',
    ]);
});

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['aluno'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$mensalidadeId = (int) ($input['mensalidade_id'] ?? 0);
$isPix         = ($input['payment_method_id'] ?? '') === 'pix';
$token         = trim($input['token'] ?? '');
$salvarCartao  = !empty($input['salvar_cartao']) && !$isPix;

if ($mensalidadeId <= 0 || (!$isPix && empty($token))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados insuficientes.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';
require_once dirname(__FILE__, 3) . '/config/app.php';

$pdo     = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];

$stMens = $pdo->prepare("
    SELECT m.id, m.referencia, m.tipo, m.descricao, m.valor, m.vencimento, m.status,
           a.email AS aluno_email, a.nome AS aluno_nome, a.cpf AS aluno_cpf, a.mp_customer_id
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
    WHERE m.id = ? AND m.aluno_id = ? AND m.status != 'pago'
");
$stMens->execute([$mensalidadeId, $alunoId]);
$mens = $stMens->fetch();

if (!$mens) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Mensalidade não encontrada.']);
    exit;
}

$valor = (float) $mens['valor'];
$total = $mens['status'] === 'atrasado'
    ? mpCalcularMultaJuros($valor, $mens['vencimento'])['total']
    : $valor;

$isAvulso = ($mens['tipo'] ?? 'mensalidade') === 'avulso';
if ($isAvulso) {
    $refLabel = $mens['descricao'] ?? 'Cobrança extra';
} else {
    $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
    [$refAno, $refMes] = explode('-', $mens['referencia']);
    $refLabel = ($meses[$refMes] ?? $refMes) . '/' . $refAno;
}

$payer = [
    'email'          => $input['payer']['email'] ?? $mens['aluno_email'],
    'identification' => [
        'type'   => $input['payer']['identification']['type']   ?? 'CPF',
        'number' => $input['payer']['identification']['number'] ?? preg_replace('/\D/', '', $mens['aluno_cpf'] ?? ''),
    ],
];

$accessToken = mpAccessToken($pdo);

// ── Regra de ouro deste arquivo: COBRAR NUNCA PODE FALHAR POR CAUSA DE OUTRA COISA ──
//
// Salvar cartão e cobrar são operações separadas, e misturar as duas já quebrou pagamento
// em produção. Histórico do bug (erro 3031 do MP, "security_code_id can't be null"):
//
//   O token do Brick é de uso único e carrega o CVV digitado pelo aluno. O código antigo
//   gastava esse token salvando o cartão no customer e, pra cobrar, gerava um token NOVO a
//   partir do card_id — mas /v1/card_tokens sem `security_code` produz token SEM CVV, que
//   o emissor recusa. O CVV do aluno estava correto: nós é que o descartávamos.
//
// Também não dá pra "aproveitar" a cobrança pra vincular o cartão mandando o customer no
// payer: com customer no payload, o MP passa a exigir que o token pertença àquele customer
// (responde 404 "Card Token not found" em vez de validar o token normalmente) — verificado
// contra a API. Um token recém-criado pelo Brick não pertence ao customer, então isso
// reintroduziria risco de recusa.
//
// Por isso o pagamento aqui é sempre o mais simples possível: token do Brick + dados do
// aluno. Quem quiser cobrança automática ativa em /meuperfil, que tem um Brick dedicado
// só pra tokenizar e salvar o cartão (services/site/salvar_cartao.php), sem cobrar nada.
$salvarCartaoPedido = $salvarCartao; // usado só pra orientar o aluno depois do pagamento

// external_reference deixa a cobrança localizável no MP mesmo se a resposta se perder
// antes de salvarmos o mp_payment_id — é o que permite ao job de verificação reconciliar
// depois (ver services/site/verificar_pagamento.php).
$externalRef = 'mensalidade-' . $mensalidadeId;

if ($isPix) {
    $paymentData = [
        'transaction_amount' => $total,
        'payment_method_id'  => 'pix',
        'description'        => 'MPG Academy — Mensalidade ' . $refLabel,
        'payer'              => ['email' => $payer['email']],
        'metadata'           => ['mensalidade_id' => $mensalidadeId],
        'external_reference' => $externalRef,
    ];
} else {
    $paymentData = [
        'transaction_amount' => $total,
        'token'              => $token,
        'description'        => 'MPG Academy — Mensalidade ' . $refLabel,
        'installments'       => (int) ($input['installments'] ?? 1),
        'payment_method_id'  => $input['payment_method_id'] ?? '',
        // Sempre os dados do próprio aluno — nada de customer aqui (ver bloco acima).
        'payer'              => $payer,
        'metadata'           => ['mensalidade_id' => $mensalidadeId],
        'external_reference' => $externalRef,
    ];
    if (!empty($input['issuer_id'])) {
        $paymentData['issuer_id'] = (int) $input['issuer_id'];
    }
}

$result = mpCriarPagamento($accessToken, $paymentData);
$body   = $result['body'];

// O cartão NÃO é salvo aqui de propósito (ver bloco no topo). Se o aluno pediu, o front
// usa este flag pra oferecer o caminho seguro: ativar a cobrança automática em /meuperfil.
$cartaoSalvo = false;

$status      = $body['status'] ?? '';
$mpPaymentId = $body['id'] ?? null;

if (in_array($status, ['approved', 'pending', 'in_process'], true)) {

    // Salva mp_payment_id para rastreamento
    if ($mpPaymentId) {
        try {
            $pdo->prepare("UPDATE mensalidades SET mp_payment_id = ? WHERE id = ?")
                ->execute([$mpPaymentId, $mensalidadeId]);
        } catch (PDOException $e) {}
    }

    if ($status === 'approved') {
        // $total pode incluir multa/juros quando a fatura está atrasada — passado como
        // valorCobrado pra refletir no lançamento de receita e no cálculo da taxa do MP.
        mpMarcarMensalidadePaga($pdo, $mensalidadeId, (string) $mpPaymentId, $body, $total);

        echo json_encode([
            'success'      => true,
            'status'       => 'approved',
            'payment_id'   => $mpPaymentId,
            'referencia'   => $refLabel,
            'valor_pago'   => $total,
            'cartao_salvo'   => $cartaoSalvo,
            'oferecer_auto' => $salvarCartaoPedido,
        ]);

    } elseif ($isPix) {
        $txData = $body['point_of_interaction']['transaction_data'] ?? [];
        echo json_encode([
            'success'        => true,
            'status'         => 'pix_pending',
            'payment_id'     => $mpPaymentId,
            'qr_code'        => $txData['qr_code']        ?? '',
            'qr_code_base64' => $txData['qr_code_base64'] ?? '',
            'referencia'     => $refLabel,
            'valor_pago'     => $total,
        ]);

    } else {
        echo json_encode([
            'success'      => true,
            'status'       => $status,
            'payment_id'   => $mpPaymentId,
            'referencia'   => $refLabel,
            'valor_pago'   => $total,
            'cartao_salvo'   => $cartaoSalvo,
            'oferecer_auto' => $salvarCartaoPedido,
        ]);
    }

} else {
    $statusDetail = $body['status_detail'] ?? null;
    // Sem status_detail o MP nem criou o pagamento — é erro de API, e a mensagem dele vem
    // em inglês. mpMotivoApiPt() traduz os casos conhecidos (ex.: token expirado).
    $detail       = mpMotivoApiPt($body)
                    ?? mpMotivoPt($statusDetail, $body['message'] ?? null)
                    ?: 'Pagamento recusado.';

    // Registra a falha pro admin conseguir ajudar o aluno (admin/erros-pagamento + sino).
    mpRegistrarErroPagamento($pdo, [
        'aluno_id'         => $alunoId,
        'aluno_nome'       => $mens['aluno_nome']  ?? null,
        'aluno_email'      => $mens['aluno_email'] ?? null,
        'contexto'         => 'mensalidade',
        'referencia_id'    => $mensalidadeId,
        'referencia_label' => $refLabel,
        'valor'            => $total,
        'metodo'           => $isPix ? 'pix' : ($input['payment_method_id'] ?? 'cartao'),
        'parcelas'         => $isPix ? null : (int) ($input['installments'] ?? 1),
        'origem'           => 'site',
        'mp_payment_id'    => $mpPaymentId,
        'mp_status'        => $status ?: 'rejected',
        'mp_status_detail' => $statusDetail,
        'http_code'        => $result['http_code'] ?? null,
        'mensagem'         => mpMotivoPt($statusDetail, $body['message'] ?? null),
        'detalhe_tecnico'  => mpExtrairErroApi($body, $statusDetail)
                            // O x-request-id é o que o suporte do MP pede pra rastrear a recusa.
                            . (!empty($result['request_id']) ? ' | x-request-id: ' . $result['request_id'] : ''),
    ]);

    echo json_encode([
        'success' => false,
        'status'  => $status ?: 'rejected',
        'message' => $detail,
    ]);
}
