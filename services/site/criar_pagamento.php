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
$token         = trim($input['token'] ?? '');

if ($mensalidadeId <= 0 || empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados insuficientes.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';
require_once dirname(__FILE__, 3) . '/config/app.php';

$pdo     = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];

// Busca mensalidade (deve pertencer ao aluno logado e não estar paga)
$stMens = $pdo->prepare("
    SELECT m.id, m.referencia, m.valor, m.vencimento, m.status,
           a.email AS aluno_email, a.nome AS aluno_nome, a.cpf AS aluno_cpf
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

// Calcula valor correto (inclui multa + juros se atrasada)
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

// Monta a descrição da referência (ex: "Mai/2026")
$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
[$refAno, $refMes] = explode('-', $mens['referencia']);
$refLabel = ($meses[$refMes] ?? $refMes) . '/' . $refAno;

// Monta payload para MP
$paymentData = [
    'transaction_amount' => $total,
    'token'              => $token,
    'description'        => 'MPG Academy — Mensalidade ' . $refLabel,
    'installments'       => (int) ($input['installments'] ?? 1),
    'payment_method_id'  => $input['payment_method_id'] ?? '',
    'payer'              => [
        'email'          => $input['payer']['email'] ?? $mens['aluno_email'],
        'identification' => [
            'type'   => $input['payer']['identification']['type']   ?? 'CPF',
            'number' => $input['payer']['identification']['number'] ?? preg_replace('/\D/', '', $mens['aluno_cpf']),
        ],
    ],
    'metadata' => ['mensalidade_id' => $mensalidadeId],
];

if (!empty($input['issuer_id'])) {
    $paymentData['issuer_id'] = (int) $input['issuer_id'];
}

$accessToken = mpAccessToken($pdo);
$modoTeste   = mpModoTeste($pdo);
error_log('[mpg-pagamento] modo_teste=' . ($modoTeste ? 'SIM' : 'NAO') . ' | token_fim=' . substr($accessToken, -10) . ' | appIsLocal=' . (APP_IS_LOCAL ? 'SIM' : 'NAO'));
$result      = mpCriarPagamento($accessToken, $paymentData);
$body        = $result['body'];
$status      = $body['status'] ?? '';

if (in_array($status, ['approved', 'pending', 'in_process'], true)) {
    // Atualiza mensalidade e cria lançamento financeiro imediatamente
    if ($status === 'approved') {
        $pdo->prepare("
            UPDATE mensalidades
            SET status = 'pago', data_pagamento = CURDATE(), atualizado_em = NOW()
            WHERE id = ?
        ")->execute([$mensalidadeId]);

        // Registra receita no livro-caixa (sem duplicar se já existir)
        $competencia = date('Y-m');
        $descLanc    = 'Mensalidade ' . $refLabel . ' — ' . $mens['aluno_nome'] . ' (via MP)';
        try {
            $pdo->prepare("
                INSERT IGNORE INTO lancamentos_financeiros
                    (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
                VALUES (?, CURDATE(), 'receita', 'mensalidade', ?, ?, 'auto', 'mensalidade', ?)
            ")->execute([$competencia, $descLanc, $total, $mensalidadeId]);
        } catch (PDOException) {}
    }

    echo json_encode([
        'success'      => true,
        'status'       => $status,
        'payment_id'   => $body['id'] ?? null,
        'referencia'   => $refLabel,
        'valor_pago'   => $total,
    ]);
} else {
    $detail = $body['status_detail'] ?? ($body['message'] ?? 'Pagamento recusado.');
    echo json_encode([
        'success' => false,
        'status'  => $status ?: 'rejected',
        'message' => $detail,
    ]);
}
