<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
require_once dirname(__FILE__, 3) . '/config/app.php';   // BASE_URL das back_urls
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['jogador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';
require_once dirname(__FILE__, 3) . '/config/batebola.php';

if (!batebolaInscricoesAbertas()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'fechado' => true,
        'message' => 'A lista do Bate Bola está fechada agora. Abre toda segunda-feira às 06h e fecha sexta-feira às 23h59.',
    ]);
    exit;
}

$pdo       = getDbConnection();
$jogadorId = (int) $_SESSION['jogador']['id'];

$stJog = $pdo->prepare("SELECT id, nome, email FROM jogadores_batebola WHERE id = ?");
$stJog->execute([$jogadorId]);
$jogador = $stJog->fetch();
if (!$jogador) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Jogador não encontrado.']);
    exit;
}

$dataEvento = batebolaProximoDomingo($pdo);

$cfg   = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'valor_batebola'")->fetch();
$valor = $cfg ? (float) $cfg['valor'] : 17.00;

// Já pago pra essa data — nada a fazer, só confirma.
$stExist = $pdo->prepare("SELECT id, status, pix_qr_code, pix_qr_code_base64 FROM batebola_inscricoes WHERE jogador_id = ? AND data_evento = ?");
$stExist->execute([$jogadorId, $dataEvento]);
$existente = $stExist->fetch();

if ($existente && $existente['status'] === 'pago') {
    echo json_encode(['success' => true, 'status' => 'pago', 'data_evento' => $dataEvento]);
    exit;
}

// Já tem um pix pendente gerado — reaproveita em vez de criar outra cobrança.
// No Checkout Pro não há QR guardado: o link é gerado por preferência a cada tentativa.
if (mpCheckoutModo($pdo) !== 'pro' && $existente && $existente['status'] === 'pendente' && !empty($existente['pix_qr_code'])) {
    echo json_encode([
        'success'        => true,
        'status'         => 'pix_pending',
        'qr_code'        => $existente['pix_qr_code'],
        'qr_code_base64' => $existente['pix_qr_code_base64'],
        'valor'          => $valor,
        'data_evento'    => $dataEvento,
    ]);
    exit;
}

// Limite de vagas — controlado na hora de gerar o pix (quem já pagou sempre é honrado).
$vagasConfirmadas = batebolaVagasConfirmadas($pdo, $dataEvento);
if ($vagasConfirmadas >= BATEBOLA_MAX_VAGAS) {
    echo json_encode(['success' => false, 'message' => 'As vagas desse domingo já esgotaram.', 'esgotado' => true]);
    exit;
}

// Cria a inscrição (pendente) pra ter um ID pra amarrar no metadata do pagamento.
$stmt = $pdo->prepare("
    INSERT INTO batebola_inscricoes (jogador_id, data_evento, valor, status)
    VALUES (?, ?, ?, 'pendente')
    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
");
$stmt->execute([$jogadorId, $dataEvento, $valor]);

$stId = $pdo->prepare("SELECT id FROM batebola_inscricoes WHERE jogador_id = ? AND data_evento = ?");
$stId->execute([$jogadorId, $dataEvento]);
$inscricaoId = (int) $stId->fetchColumn();

$accessToken = mpAccessToken($pdo);

$dataFmt = (new DateTime($dataEvento))->format('d/m/Y');

// ── Checkout Pro ─────────────────────────────────────────────────────────────
//
// Toda a validação acima (janela aberta, vagas, inscrição duplicada) vale igual — muda
// só o modo de cobrar: em vez de criar o pagamento PIX direto (bloqueado na conta desde
// 19/08/2026), pedimos uma preferência e mandamos o jogador pro Mercado Pago.
//
// A preferência exclui cartão — Bate Bola é só PIX (ver criar_preferencia.php).
if (mpCheckoutModo($pdo) === 'pro') {
    $pref = mpCriarPreferencia($accessToken, [
        'items' => [[
            'title'       => 'MPG Academy — Bate Bola ' . $dataFmt,
            'quantity'    => 1,
            'unit_price'  => round($valor, 2),
            'currency_id' => 'BRL',
        ]],
        'payer'              => ['email' => $jogador['email'] ?? ''],
        'external_reference' => 'batebola-' . $inscricaoId,
        'metadata'           => ['batebola_inscricao_id' => $inscricaoId],
        'back_urls'          => [
            'success' => BASE_URL . '/batebolainicio',
            'pending' => BASE_URL . '/batebolainicio',
            'failure' => BASE_URL . '/batebolapagamento',
        ],
        'payment_methods'    => [
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
        ],
    ]);

    if ($pref['http_code'] >= 300 || empty($pref['body']['init_point'])) {
        $motivo = mpMotivoApiPt($pref['body']) ?? ($pref['body']['message'] ?? 'Não foi possível gerar o pagamento.');
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => $motivo]);
        exit;
    }

    echo json_encode([
        'success'     => true,
        'status'      => 'redirect',
        'init_point'  => $pref['body']['init_point'],
        'valor'       => $valor,
        'data_evento' => $dataEvento,
    ]);
    exit;
}

$paymentData = [
    'transaction_amount' => $valor,
    'payment_method_id'  => 'pix',
    'description'        => 'MPG Academy — Bate Bola ' . $dataFmt,
    'payer'              => ['email' => $jogador['email']],
    'metadata'           => ['batebola_inscricao_id' => $inscricaoId],
    'external_reference' => 'batebola-' . $inscricaoId,
];

$result = mpCriarPagamento($accessToken, $paymentData);
$body   = $result['body'];
$status = $body['status'] ?? '';

if (!in_array($status, ['approved', 'pending', 'in_process'], true)) {
    $statusDetail = $body['status_detail'] ?? null;
    // Sem status_detail o MP nem criou o pagamento — mensagem de API, em inglês.
    $detail       = mpMotivoApiPt($body)
                    ?? mpMotivoPt($statusDetail, $body['message'] ?? null)
                    ?: 'Erro ao gerar pagamento.';

    // Jogador do Bate Bola não é aluno (tabela própria) — por isso vai sem aluno_id,
    // só com nome/e-mail pra identificação no admin.
    mpRegistrarErroPagamento($pdo, [
        'aluno_nome'       => $jogador['nome']  ?? null,
        'aluno_email'      => $jogador['email'] ?? null,
        'contexto'         => 'batebola',
        'referencia_id'    => $inscricaoId,
        'referencia_label' => 'Bate Bola ' . $dataFmt,
        'valor'            => $valor,
        'metodo'           => 'pix',
        'origem'           => 'site',
        'mp_payment_id'    => $body['id'] ?? null,
        'mp_status'        => $status ?: 'rejected',
        'mp_status_detail' => $statusDetail,
        'http_code'        => $result['http_code'] ?? null,
        'mensagem'         => mpMotivoPt($statusDetail, $body['message'] ?? null),
        'detalhe_tecnico'  => mpExtrairErroApi($body, $statusDetail)
                            // O x-request-id é o que o suporte do MP pede pra rastrear a recusa.
                            . (!empty($result['request_id']) ? ' | x-request-id: ' . $result['request_id'] : ''),
    ]);

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $detail]);
    exit;
}

$mpPaymentId = $body['id'] ?? null;
$txData      = $body['point_of_interaction']['transaction_data'] ?? [];
$qrCode      = $txData['qr_code']        ?? '';
$qrCodeB64   = $txData['qr_code_base64'] ?? '';

$pdo->prepare("
    UPDATE batebola_inscricoes
    SET mp_payment_id = ?, pix_qr_code = ?, pix_qr_code_base64 = ?
    WHERE id = ?
")->execute([$mpPaymentId, $qrCode, $qrCodeB64, $inscricaoId]);

if ($status === 'approved') {
    batebolaConfirmarInscricao($pdo, $inscricaoId, (string) $mpPaymentId, $body);
    echo json_encode(['success' => true, 'status' => 'pago', 'data_evento' => $dataEvento]);
    exit;
}

echo json_encode([
    'success'        => true,
    'status'         => 'pix_pending',
    'qr_code'        => $qrCode,
    'qr_code_base64' => $qrCodeB64,
    'valor'          => $valor,
    'data_evento'    => $dataEvento,
]);
