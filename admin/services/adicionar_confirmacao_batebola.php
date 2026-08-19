<?php

/**
 * Admin inclui um jogador na lista de um domingo.
 *
 * Incluir aqui significa "já recebi por fora" — PIX na mão, dinheiro, o que for. Por isso a
 * inscrição nasce direto como paga, sem passar pelo Mercado Pago, e o movimento fica
 * registrado em batebola_movimentacoes pra aparecer no sininho como lembrete.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Erro de banco aqui virava HTML e o front mostrava só uma mensagem genérica — mesmo
// problema que já apareceu em outros endpoints. Sempre JSON, com o motivo.
set_exception_handler(function (Throwable $e) {
    error_log('[batebola-incluir] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao incluir: ' . $e->getMessage()]);
});

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

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/batebola.php';

$pdo        = getDbConnection();
$jogadorId  = (int) ($_POST['jogador_id'] ?? 0);
$dataEvento = trim($_POST['data_evento'] ?? '');

if ($jogadorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Jogador inválido.']);
    exit;
}

// A data precisa ser mesmo um domingo — a lista é por rodada, e aceitar outro dia criaria
// um "evento" que nenhuma outra tela sabe exibir.
$dt = DateTime::createFromFormat('Y-m-d', $dataEvento);
if (!$dt || $dt->format('Y-m-d') !== $dataEvento || (int) $dt->format('w') !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida (precisa ser um domingo).']);
    exit;
}

$stJog = $pdo->prepare("SELECT id, nome FROM jogadores_batebola WHERE id = ?");
$stJog->execute([$jogadorId]);
$jogador = $stJog->fetch();

if (!$jogador) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Jogador não encontrado.']);
    exit;
}

$valor = (float) ($pdo->query("SELECT valor FROM configuracoes WHERE chave = 'valor_batebola'")->fetchColumn() ?: 17.00);

try {
    $pdo->beginTransaction();

    // Trava a contagem de vagas junto com a leitura: sem isso, dois admins incluindo ao mesmo
    // tempo poderiam estourar o limite de 24.
    $stExiste = $pdo->prepare("SELECT id, status FROM batebola_inscricoes WHERE jogador_id = ? AND data_evento = ? FOR UPDATE");
    $stExiste->execute([$jogadorId, $dataEvento]);
    $existente = $stExiste->fetch();

    if ($existente && $existente['status'] === 'pago') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $jogador['nome'] . ' já está na lista desse domingo.']);
        exit;
    }

    $stVagas = $pdo->prepare("SELECT COUNT(*) FROM batebola_inscricoes WHERE data_evento = ? AND status = 'pago'");
    $stVagas->execute([$dataEvento]);
    $confirmados = (int) $stVagas->fetchColumn();

    if ($confirmados >= BATEBOLA_MAX_VAGAS) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'As ' . BATEBOLA_MAX_VAGAS . ' vagas desse domingo já estão preenchidas.',
        ]);
        exit;
    }

    // Reaproveita a linha quando ela já existe (pendente ou cancelada): a unique
    // uq_jogador_data (jogador_id, data_evento) não deixa inserir uma segunda.
    if ($existente) {
        $pdo->prepare("
            UPDATE batebola_inscricoes
            SET status = 'pago', valor = ?, pago_em = NOW(), mp_payment_id = NULL
            WHERE id = ?
        ")->execute([$valor, (int) $existente['id']]);
    } else {
        $pdo->prepare("
            INSERT INTO batebola_inscricoes (jogador_id, data_evento, valor, status, pago_em)
            VALUES (?, ?, ?, 'pago', NOW())
        ")->execute([$jogadorId, $dataEvento, $valor]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

batebolaRegistrarMovimentacao(
    $pdo, $jogadorId, $dataEvento, 'incluido', $valor,
    (int) ($_SESSION['usuario']['id'] ?? 0),
    'Incluído pelo admin — pagamento recebido por fora'
);

// Quem entra depois do sorteio muda o equilíbrio dos times, então as trocas manuais feitas
// antes deixam de fazer sentido. Melhor zerar do que exibir uma escalação meio antiga.
batebolaLimparTrocas($pdo, $dataEvento);

echo json_encode([
    'success' => true,
    'message' => $jogador['nome'] . ' entrou na lista como pago.',
    'valor'   => $valor,
]);
