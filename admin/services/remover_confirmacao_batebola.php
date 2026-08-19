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

if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$inscricaoId = (int) ($_POST['inscricao_id'] ?? 0);
if ($inscricaoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Inscrição inválida.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/batebola.php';
$pdo = getDbConnection();

$st = $pdo->prepare("
    SELECT bi.id, bi.jogador_id, bi.data_evento, bi.valor, j.nome
    FROM batebola_inscricoes bi
    JOIN jogadores_batebola j ON j.id = bi.jogador_id
    WHERE bi.id = ? AND bi.status = 'pago'
");
$st->execute([$inscricaoId]);
$inscricao = $st->fetch();

if (!$inscricao) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Confirmação não encontrada.']);
    exit;
}

$pdo->prepare("UPDATE batebola_inscricoes SET status = 'cancelado' WHERE id = ?")->execute([$inscricaoId]);

// Tirar alguém da lista significa que a grana foi devolvida. Fica registrado pra aparecer
// no sininho — é dinheiro saindo sem passar pelo Mercado Pago, e sem isso não sobra rastro.
batebolaRegistrarMovimentacao(
    $pdo,
    (int) $inscricao['jogador_id'],
    $inscricao['data_evento'],
    'removido',
    (float) $inscricao['valor'],
    (int) ($_SESSION['usuario']['id'] ?? 0),
    'Removido pelo admin — valor devolvido'
);

// Quem sai depois do sorteio desequilibra os times; as trocas manuais anteriores passam a
// não fazer sentido (ver batebolaAplicarTrocas em config/batebola.php).
batebolaLimparTrocas($pdo, $inscricao['data_evento']);

echo json_encode(['success' => true, 'message' => $inscricao['nome'] . ' saiu da lista.']);
