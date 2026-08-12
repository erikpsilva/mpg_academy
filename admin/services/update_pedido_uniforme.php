<?php

/**
 * Correção de um pedido de uniforme já pago, pelo admin.
 *
 * Serve pro caso real de erro de digitação: o aluno pediu com o nome errado na camisa, o
 * número trocado ou o tamanho errado, e alguém precisa arrumar antes da confecção. Só mexe
 * no que vai bordado/costurado — aluno, turma, gênero, modelo, valor e pagamento continuam
 * sendo do fluxo original, porque mudar qualquer um deles não é "corrigir um pedido", é
 * outro pedido.
 *
 * O número passa exatamente pela mesma trava do fluxo do aluno (balde turma+gênero, com
 * SELECT ... FOR UPDATE): não adianta a tela validar se dois admins salvarem ao mesmo tempo.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (empty($_SESSION['usuario']) || !in_array($nivel, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo      = getDbConnection();
$pedidoId = (int) ($_POST['pedido_id'] ?? 0);

if ($pedidoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Pedido inválido.']);
    exit;
}

$st = $pdo->prepare("
    SELECT id, aluno_id, turma_id, genero, numero, nome_camisa, tamanho_camisa, tamanho_shorts, status_pagamento
    FROM pedidos_uniforme WHERE id = ?
");
$st->execute([$pedidoId]);
$pedido = $st->fetch();

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    exit;
}

// A tela lista só pedidos pagos. Editar uma reserva ainda não paga mexeria num pedido que
// pode expirar sozinho em minutos — não é o caso de uso daqui.
if ($pedido['status_pagamento'] !== 'pago') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Só é possível corrigir pedidos com pagamento confirmado.']);
    exit;
}

$genero = $pedido['genero'];

// ── Entrada ───────────────────────────────────────────────────────────────────
$nomeCamisa    = uniformeNormalizarNome((string) ($_POST['nome_camisa'] ?? ''));
$numero        = (int) ($_POST['numero'] ?? 0);
$tamanhoCamisa = trim($_POST['tamanho_camisa'] ?? '');
$tamanhoShorts = trim($_POST['tamanho_shorts'] ?? '');

// ── Validação ─────────────────────────────────────────────────────────────────
$erro = null;

if ($nomeCamisa === '') {
    $erro = 'Informe o nome que vai na camisa.';
} elseif ($numero < UNIFORME_NUMERO_MIN || $numero > UNIFORME_NUMERO_MAX) {
    $erro = 'Escolha um número de ' . UNIFORME_NUMERO_MIN . ' a ' . UNIFORME_NUMERO_MAX . '.';
} elseif (!in_array($tamanhoCamisa, uniformeTamanhos($genero, 'camisa'), true)) {
    $erro = 'Tamanho da camisa inválido para esse uniforme.';
} elseif (!in_array($tamanhoShorts, uniformeTamanhos($genero, 'shorts'), true)) {
    // "a bermuda" (fem) x "o calção" (masc) — o artigo muda com a peça.
    $erro = 'Tamanho ' . ($genero === 'feminino' ? 'da bermuda' : 'do calção') . ' inválido para esse uniforme.';
}

if ($erro) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $erro]);
    exit;
}

$numeroAntigo = (int) $pedido['numero'];

/**
 * Marca (ou desmarca) o alerta de número duplicado de todos os pedidos pagos que usam um
 * número dentro do balde. Precisa rodar pro número antigo também: ao liberar um número que
 * estava em conflito, quem ficou com ele deixa de estar duplicado.
 */
function uniformeRecalcularConflito(PDO $pdo, int $turmaId, string $genero, int $numero): void
{
    $st = $pdo->prepare("
        SELECT id, aluno_id FROM pedidos_uniforme
        WHERE turma_id = ? AND genero = ? AND numero = ? AND status_pagamento = 'pago'
    ");
    $st->execute([$turmaId, $genero, $numero]);
    $linhas = $st->fetchAll();

    // Duplicado é o mesmo número em ALUNOS diferentes. O mesmo aluno pedir duas camisas com
    // o número dele não é conflito nenhum.
    $donos    = array_unique(array_map(fn($l) => (int) $l['aluno_id'], $linhas));
    $conflito = count($donos) > 1 ? 1 : 0;

    foreach ($linhas as $l) {
        $pdo->prepare("UPDATE pedidos_uniforme SET conflito_numero = ? WHERE id = ?")
            ->execute([$conflito, (int) $l['id']]);
    }
}

// ── Gravação ──────────────────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    uniformeExpirarReservas($pdo);

    if ($numero !== $numeroAntigo) {
        $stLock = $pdo->prepare("
            SELECT aluno_id
            FROM pedidos_uniforme
            WHERE turma_id = ?
              AND genero = ?
              AND numero = ?
              AND id != ?
              AND (
                    status_pagamento = 'pago'
                    OR (status_pagamento = 'aguardando' AND reserva_expira_em > NOW())
                  )
            FOR UPDATE
        ");
        $stLock->execute([(int) $pedido['turma_id'], $genero, $numero, $pedidoId]);

        foreach ($stLock->fetchAll(PDO::FETCH_COLUMN) as $donoId) {
            if ((int) $donoId !== (int) $pedido['aluno_id']) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'O número ' . $numero . ' já está em uso por outro aluno dessa turma/gênero.',
                ]);
                exit;
            }
        }
    }

    $pdo->prepare("
        UPDATE pedidos_uniforme
        SET nome_camisa = ?, numero = ?, tamanho_camisa = ?, tamanho_shorts = ?, atualizado_em = NOW()
        WHERE id = ?
    ")->execute([$nomeCamisa, $numero, $tamanhoCamisa, $tamanhoShorts, $pedidoId]);

    uniformeRecalcularConflito($pdo, (int) $pedido['turma_id'], $genero, $numero);
    if ($numero !== $numeroAntigo) {
        uniformeRecalcularConflito($pdo, (int) $pedido['turma_id'], $genero, $numeroAntigo);
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[uniforme-editar-pedido] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar a correção.']);
    exit;
}

// Log de auditoria: correção de pedido pago mexe no que vai ser produzido, então precisa
// deixar rastro de quem mudou o quê.
$antes = sprintf('%s #%d (camisa %s / shorts %s)',
    $pedido['nome_camisa'], $numeroAntigo, $pedido['tamanho_camisa'], $pedido['tamanho_shorts']);
$depois = sprintf('%s #%d (camisa %s / shorts %s)',
    $nomeCamisa, $numero, $tamanhoCamisa, $tamanhoShorts);

error_log(sprintf('[uniforme-editar-pedido] pedido %d por usuario %d: %s -> %s',
    $pedidoId, (int) ($_SESSION['usuario']['id'] ?? 0), $antes, $depois));

echo json_encode([
    'success' => true,
    'message' => 'Pedido corrigido.',
    'pedido'  => [
        'id'             => $pedidoId,
        'nome_camisa'    => $nomeCamisa,
        'numero'         => $numero,
        'tamanho_camisa' => $tamanhoCamisa,
        'tamanho_shorts' => $tamanhoShorts,
    ],
]);
