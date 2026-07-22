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
require_once dirname(__FILE__) . '/lib_desconto_aula.php';

$alunoId = (int) ($_POST['aluno_id'] ?? 0);
$turmaId = (int) ($_POST['turma_id'] ?? 0);

if ($alunoId <= 0 || $turmaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDs inválidos.']);
    exit;
}

$remover = ($_POST['remover'] ?? '') === '1';

$pdo = getDbConnection();

// Recalcula o valor das faturas pendentes/atrasadas já geradas pra esse aluno+turma,
// aplicando o desconto (novo, alterado ou removido) que acabou de ser salvo — sem isso,
// faturas geradas antes da mudança ficam com o valor antigo (bug: valor errado na tela
// de mensalidades e no aviso de cobrança via WhatsApp).
function recalcularFaturasPendentes(PDO $pdo, int $alunoId, int $turmaId): void {
    $stmt = $pdo->prepare("
        SELECT DISTINCT referencia FROM mensalidades
        WHERE aluno_id = ? AND turma_id = ? AND tipo = 'mensalidade' AND status IN ('pendente','atrasado')
    ");
    $stmt->execute([$alunoId, $turmaId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $referencia) {
        recalcularDescontoAulaTurma($pdo, $turmaId, $referencia);
    }
}

if ($remover) {
    $stmt = $pdo->prepare("
        UPDATE turma_alunos
        SET desconto = NULL, desconto_tipo = 'fixo',
            desconto_inicio = NULL, desconto_fim = NULL, desconto_vitalicio = 0
        WHERE turma_id = ? AND aluno_id = ? AND status = 'ativo'
    ");
    $stmt->execute([$turmaId, $alunoId]);
    recalcularFaturasPendentes($pdo, $alunoId, $turmaId);
    echo json_encode(['success' => true]);
    exit;
}

$desconto    = isset($_POST['desconto']) && $_POST['desconto'] !== '' ? (float) $_POST['desconto'] : null;
$tipo        = in_array($_POST['desconto_tipo'] ?? '', ['fixo', 'percentual']) ? $_POST['desconto_tipo'] : 'fixo';
$vitalicio   = ($_POST['desconto_vitalicio'] ?? '0') === '1' ? 1 : 0;
$inicio      = !$vitalicio && !empty($_POST['desconto_inicio']) ? $_POST['desconto_inicio'] : null;
$fim         = !$vitalicio && !empty($_POST['desconto_fim'])    ? $_POST['desconto_fim']    : null;

if ($desconto === null || $desconto < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor de desconto inválido.']);
    exit;
}

if ($tipo === 'percentual' && $desconto > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Desconto percentual não pode exceder 100%.']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE turma_alunos
    SET desconto = ?, desconto_tipo = ?,
        desconto_inicio = ?, desconto_fim = ?, desconto_vitalicio = ?
    WHERE turma_id = ? AND aluno_id = ? AND status = 'ativo'
");
$stmt->execute([$desconto, $tipo, $inicio, $fim, $vitalicio, $turmaId, $alunoId]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Vínculo não encontrado.']);
    exit;
}

recalcularFaturasPendentes($pdo, $alunoId, $turmaId);

echo json_encode(['success' => true]);
