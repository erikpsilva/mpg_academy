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
if ($existente && $existente['status'] === 'pendente' && !empty($existente['pix_qr_code'])) {
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
    $detail       = $statusDetail ?? ($body['message'] ?? 'Erro ao gerar pagamento.');

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
        'detalhe_tecnico'  => mpExtrairErroApi($body, $statusDetail),
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
