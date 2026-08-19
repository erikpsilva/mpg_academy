<?php

/**
 * Cria o pedido de uniforme e RESERVA o número escolhido por UNIFORME_RESERVA_MINUTOS.
 * O pedido nasce com status_pagamento = 'aguardando' — só vira pedido de verdade (e entra
 * na fila do admin) quando o pagamento é confirmado, em config/uniformes.php.
 *
 * Se o aluno não pagar dentro da janela, uniformeExpirarReservas() devolve o número
 * pro pool na próxima consulta de disponibilidade.
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

if (empty($_SESSION['aluno'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo     = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];

$turmaId    = (int) ($_POST['turma_id'] ?? 0);
$genero     = trim($_POST['genero']  ?? '');
$modelo     = trim($_POST['modelo']  ?? '');
$nomeCamisa    = uniformeNormalizarNome($_POST['nome_camisa'] ?? '');
$numero        = (int) ($_POST['numero'] ?? 0);
$tamanhoCamisa = strtoupper(trim($_POST['tamanho_camisa'] ?? ''));
$tamanhoShorts = strtoupper(trim($_POST['tamanho_shorts'] ?? ''));

if (!in_array($genero, UNIFORME_GENEROS, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione o uniforme masculino ou feminino.']);
    exit;
}

if (!in_array($modelo, UNIFORME_MODELOS, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione o modelo do uniforme.']);
    exit;
}

if ($nomeCamisa === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe o nome que vai na camiseta.']);
    exit;
}

// Cada peça tem sua grade — a do shorts não bate com a da camisa, nem entre gêneros.
if (!in_array($tamanhoCamisa, uniformeTamanhos($genero, 'camisa'), true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione um tamanho válido para a camisa.']);
    exit;
}

if (!in_array($tamanhoShorts, uniformeTamanhos($genero, 'shorts'), true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Selecione um tamanho válido para ' . ($genero === 'feminino' ? 'a bermuda' : 'o calção') . '.',
    ]);
    exit;
}

if ($numero < UNIFORME_NUMERO_MIN || $numero > UNIFORME_NUMERO_MAX) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Escolha um número de 1 a 99.']);
    exit;
}

// A turma define o balde de numeração — só vale turma em que o aluno está matriculado.
$turmas   = uniformeTurmasDoAluno($pdo, $alunoId);
$turmaIds = array_map(fn($t) => (int) $t['id'], $turmas);

if (empty($turmaIds)) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'Você precisa estar matriculado em uma turma para pedir o uniforme.',
    ]);
    exit;
}

if ($turmaId <= 0 && count($turmaIds) === 1) {
    $turmaId = $turmaIds[0];
}

if (!in_array($turmaId, $turmaIds, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Turma inválida para este aluno.']);
    exit;
}

$valor = uniformeValor($pdo);

try {
    // Transação + lock: dois alunos clicando no mesmo número ao mesmo tempo não podem
    // os dois reservar. O SELECT ... FOR UPDATE segura as linhas do balde até o commit.
    $pdo->beginTransaction();

    uniformeExpirarReservas($pdo);

    $stLock = $pdo->prepare("
        SELECT aluno_id
        FROM pedidos_uniforme
        WHERE turma_id = ?
          AND genero = ?
          AND numero = ?
          AND (
                status_pagamento = 'pago'
                OR (status_pagamento = 'aguardando' AND reserva_expira_em > NOW())
              )
        FOR UPDATE
    ");
    $stLock->execute([$turmaId, $genero, $numero]);
    $donos = $stLock->fetchAll(PDO::FETCH_COLUMN);

    // Números já pertencentes ao próprio aluno podem ser reutilizados por ele.
    foreach ($donos as $donoId) {
        if ((int) $donoId !== $alunoId) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'success'      => false,
                'numero_usado' => true,
                'message'      => 'O número ' . $numero . ' acabou de ser escolhido por outro aluno da sua turma. Escolha outro.',
            ]);
            exit;
        }
    }

    // A expiração é calculada pelo MySQL (DATE_ADD sobre NOW()), nunca pelo PHP: o fuso do
    // PHP e o do banco podem divergir (no XAMPP local dão 5h de diferença), e como toda a
    // checagem de reserva compara com NOW(), misturar os dois faria a janela de 30 minutos
    // virar horas — ou expirar na hora.
    // pessoa_tipo/pessoa_id identificam quem pediu desde que o pedido passou a poder ser de
    // professor ou equipe MPG. Sem preencher, a listagem do admin não acha o nome e mostra
    // "(removido)" no lugar do aluno.
    $pdo->prepare("
        INSERT INTO pedidos_uniforme
            (pessoa_tipo, pessoa_id, tipo_uniforme,
             aluno_id, turma_id, genero, modelo, nome_camisa, numero, tamanho_camisa, tamanho_shorts, valor,
             status_pagamento, status_pedido, reserva_expira_em)
        VALUES ('aluno', ?, 'completo', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aguardando', 'pendente', DATE_ADD(NOW(), INTERVAL ? MINUTE))
    ")->execute([$alunoId, $alunoId, $turmaId, $genero, $modelo, $nomeCamisa, $numero,
                 $tamanhoCamisa, $tamanhoShorts, $valor, UNIFORME_RESERVA_MINUTOS]);

    $pedidoId = (int) $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        'success'          => true,
        'pedido_id'        => $pedidoId,
        'valor'            => $valor,
        'reserva_minutos'  => UNIFORME_RESERVA_MINUTOS,
        'redirect'         => BASE_URL . '/pagamentouniforme?pedido_id=' . $pedidoId,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[uniforme-pedido] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar o pedido. Tente novamente.']);
}
