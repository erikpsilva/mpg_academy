<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

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
$hoje  = new DateTime('today');
$venc  = new DateTime($mens['vencimento']);

if ($mens['status'] === 'atrasado') {
    $dias  = (int) $venc->diff($hoje)->days;
    $multa = $valor * 0.05;
    $base  = $valor + $multa;
    $juros = $base * 0.005 * $dias;
    $total = round($base + $juros, 2);
} else {
    $total = $valor;
}

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

// Se o aluno marcou "salvar cartão / ativar cobrança automática", salva o cartão no
// customer do MP e gera um novo token a partir dele (token avulso do Brick é uso único,
// não pode ser reaproveitado pra cobranças futuras). Se algo falhar aqui, não bloqueia o
// pagamento — segue com o token original e cobra normalmente, só não ativa a recorrência.
$cartaoSalvo     = false;
$cartaoInfo      = null;
$customerIdUsado = null;

if ($salvarCartao) {
    $customerIdUsado = $mens['mp_customer_id'] ?: mpObterOuCriarCustomer($accessToken, $payer['email']);

    if (!empty($customerIdUsado)) {
        $cartaoInfo = mpSalvarCartaoCustomer($accessToken, $customerIdUsado, $token);
        if ($cartaoInfo) {
            $novoToken = mpGerarTokenCartaoSalvo($accessToken, $cartaoInfo['id'], $customerIdUsado);
            if ($novoToken) {
                $token       = $novoToken;
                $cartaoSalvo = true;
            }
        }
    }
}

if ($isPix) {
    $paymentData = [
        'transaction_amount' => $total,
        'payment_method_id'  => 'pix',
        'description'        => 'MPG Academy — Mensalidade ' . $refLabel,
        'payer'              => ['email' => $payer['email']],
        'metadata'           => ['mensalidade_id' => $mensalidadeId],
    ];
} else {
    $paymentData = [
        'transaction_amount' => $total,
        'token'              => $token,
        'description'        => 'MPG Academy — Mensalidade ' . $refLabel,
        'installments'       => (int) ($input['installments'] ?? 1),
        'payment_method_id'  => $input['payment_method_id'] ?? '',
        'payer'              => $cartaoSalvo ? ['type' => 'customer', 'id' => $customerIdUsado] : $payer,
        'metadata'           => ['mensalidade_id' => $mensalidadeId],
    ];
    if (!empty($input['issuer_id'])) {
        $paymentData['issuer_id'] = (int) $input['issuer_id'];
    }
}

$result = mpCriarPagamento($accessToken, $paymentData);

// Persiste o cartão salvo independente do resultado dessa cobrança específica
// (o cartão pode ter sido salvo com sucesso mesmo que essa cobrança seja recusada).
if ($cartaoSalvo && $cartaoInfo) {
    $bandeira = $cartaoInfo['payment_method']['id'] ?? ($cartaoInfo['payment_method_id'] ?? '');
    $final4   = $cartaoInfo['last_four_digits'] ?? '';
    try {
        $pdo->prepare("
            UPDATE alunos
            SET mp_customer_id = ?, mp_card_id = ?, cartao_bandeira = ?, cartao_final4 = ?, auto_pagamento = 1
            WHERE id = ?
        ")->execute([$customerIdUsado, $cartaoInfo['id'], $bandeira, $final4, $alunoId]);
    } catch (PDOException $e) {}
}
$body        = $result['body'];
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
        $pdo->prepare("
            UPDATE mensalidades
            SET status = 'pago', data_pagamento = CURDATE(), atualizado_em = NOW()
            WHERE id = ?
        ")->execute([$mensalidadeId]);

        $competencia = date('Y-m');
        $descLanc    = 'Mensalidade ' . $refLabel . ' — ' . $mens['aluno_nome'] . ' (via MP)';
        try {
            $pdo->prepare("
                INSERT IGNORE INTO lancamentos_financeiros
                    (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
                VALUES (?, CURDATE(), 'receita', 'mensalidade', ?, ?, 'auto', 'mensalidade', ?)
            ")->execute([$competencia, $descLanc, $total, $mensalidadeId]);
        } catch (PDOException $e) {}

        echo json_encode([
            'success'      => true,
            'status'       => 'approved',
            'payment_id'   => $mpPaymentId,
            'referencia'   => $refLabel,
            'valor_pago'   => $total,
            'cartao_salvo' => $cartaoSalvo,
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
            'cartao_salvo' => $cartaoSalvo,
        ]);
    }

} else {
    $detail = $body['status_detail'] ?? ($body['message'] ?? 'Pagamento recusado.');
    echo json_encode([
        'success' => false,
        'status'  => $status ?: 'rejected',
        'message' => $detail,
    ]);
}
