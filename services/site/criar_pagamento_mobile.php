<?php
/**
 * Endpoint de criação de pagamento MP para o app mobile.
 * Auth via Bearer token no header Authorization.
 */
require_once __DIR__ . '/mobile_auth.php';
$aluno = mobileAuth();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Dados inválidos.']); exit; }

$mensalidadeId = (int)($input['mensalidade_id'] ?? 0);
$cardToken     = trim($input['token'] ?? '');

if ($mensalidadeId <= 0 || empty($cardToken)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Dados insuficientes.']);
    exit;
}

require_once dirname(__FILE__,3) . '/config/mercadopago.php';

$pdo = getDbConnection();

// Busca mensalidade (pertence ao aluno e não está paga)
$stmt = $pdo->prepare("
    SELECT m.id, m.referencia, m.valor, m.vencimento, m.status
    FROM mensalidades m
    WHERE m.id = ? AND m.aluno_id = ? AND m.status != 'pago'
");
$stmt->execute([$mensalidadeId, $aluno['id']]);
$mens = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mens) {
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'Mensalidade não encontrada.']);
    exit;
}

// Calcula total correto no backend
$valor = (float) $mens['valor'];
$hoje  = new DateTime('today');
$venc  = new DateTime($mens['vencimento']);
$total = $valor;

if ($mens['status'] === 'atrasado') {
    $dias  = (int) $venc->diff($hoje)->days;
    $multa = $valor * 0.05;
    $base  = $valor + $multa;
    $juros = $base * 0.005 * $dias;
    $total = round($base + $juros, 2);
}

$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
[$rAno,$rMes] = explode('-', $mens['referencia']);
$refLabel = ($meses[$rMes] ?? $rMes) . '/' . $rAno;

$paymentData = [
    'transaction_amount' => $total,
    'token'              => $cardToken,
    'description'        => 'MPG Academy — Mensalidade ' . $refLabel,
    'installments'       => (int)($input['installments'] ?? 1),
    'payment_method_id'  => $input['payment_method_id'] ?? '',
    'payer'              => [
        'email'          => $input['payer']['email'] ?? $aluno['email'],
        'identification' => [
            'type'   => $input['payer']['identification']['type']   ?? 'CPF',
            'number' => $input['payer']['identification']['number'] ?? preg_replace('/\D/','',$aluno['cpf']),
        ],
    ],
    'external_reference' => (string) $mensalidadeId,
    'metadata'           => ['mensalidade_id' => $mensalidadeId],
];

if (!empty($input['issuer_id'])) {
    $paymentData['issuer_id'] = (int)$input['issuer_id'];
}

$accessToken = mpAccessToken($pdo);
$result      = mpCriarPagamento($accessToken, $paymentData);
$body        = $result['body'];
$status      = $body['status'] ?? '';

$mpPaymentId = $body['id'] ?? null;

if (in_array($status, ['approved','pending','in_process'], true)) {
    if ($status === 'approved') {
        $pdo->prepare("UPDATE mensalidades SET status='pago', data_pagamento=CURDATE(), mp_payment_id=?, atualizado_em=NOW() WHERE id=?")
            ->execute([$mpPaymentId, $mensalidadeId]);
        // Registra no livro-caixa
        try {
            $pdo->prepare("INSERT IGNORE INTO lancamentos_financeiros (competencia,data,tipo,categoria,descricao,valor,origem,referencia_tipo,referencia_id) VALUES (?,CURDATE(),'receita','mensalidade',?,?,'auto','mensalidade',?)")
                ->execute([date('Y-m'), 'Mensalidade ' . $refLabel . ' — ' . $aluno['nome'], $total, $mensalidadeId]);
        } catch (PDOException $e) {}
    }
    echo json_encode(['success'=>true,'status'=>$status,'payment_id'=>$body['id']??null]);
} else {
    $detail = $body['status_detail'] ?? ($body['message'] ?? 'Pagamento recusado.');
    echo json_encode(['success'=>false,'status'=>$status?:'rejected','message'=>$detail]);
}
