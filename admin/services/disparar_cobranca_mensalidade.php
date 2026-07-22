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
if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/services/whatsapp/wpp_mensalidade_cobranca.php';

$pdo = getDbConnection();

$mensalidadeId = (int) ($_POST['mensalidade_id'] ?? 0);
$mes           = $_POST['mes'] ?? '';

$baseSql = "
    SELECT m.id AS mensalidade_id, m.aluno_id, m.valor, m.vencimento, m.referencia, m.status,
           a.nome, a.celular
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
";

if ($mensalidadeId > 0) {
    $stmt = $pdo->prepare($baseSql . " WHERE m.id = ?");
    $stmt->execute([$mensalidadeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $stmt = $pdo->prepare($baseSql . " WHERE m.referencia = ? AND m.status IN ('pendente','atrasado')");
    $stmt->execute([$mes]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

if (!$rows) {
    echo json_encode(['success' => true, 'enviados' => 0, 'pulados' => 0, 'message' => 'Nenhuma fatura pendente pra avisar.']);
    exit;
}

$logUpsert = $pdo->prepare("
    INSERT INTO notificacoes_log (aluno_id, mensalidade_id, tipo)
    VALUES (?, ?, 'wpp_cobranca_manual')
    ON DUPLICATE KEY UPDATE enviado_em = NOW()
");

$enviados   = 0;
$pulados    = 0;
$semCelular = 0;

foreach ($rows as $r) {
    if ($r['status'] === 'pago') { $pulados++; continue; }
    if (empty($r['celular']))    { $semCelular++; continue; }

    // Sem trava de "já enviado hoje" — o admin pode reenviar quantas vezes quiser.
    if (wppMensalidadeCobranca($r)) {
        $logUpsert->execute([$r['aluno_id'], $r['mensalidade_id']]);
        $enviados++;
    }
}

$msg = $enviados > 0
    ? "{$enviados} aviso(s) de cobrança enviado(s)."
    : 'Nenhum aviso novo enviado.';
if ($pulados > 0)    $msg .= " {$pulados} fatura(s) já paga(s), pulada(s).";
if ($semCelular > 0) $msg .= " {$semCelular} sem celular cadastrado.";

echo json_encode([
    'success'     => true,
    'enviados'    => $enviados,
    'pulados'     => $pulados,
    'sem_celular' => $semCelular,
    'message'     => $msg,
]);
