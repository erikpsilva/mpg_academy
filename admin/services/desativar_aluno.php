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

if (empty($_SESSION['usuario']) || ($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$alunoId = (int) ($_POST['id'] ?? 0);
if ($alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

// IDs de mensalidades pendentes/atrasadas que o admin escolheu cancelar (deixam de existir).
// Qualquer fatura não listada aqui é mantida ativa (continua cobrável — manual, automática ou via WhatsApp).
$cancelarIds = array_filter(array_map('intval', (array) ($_POST['cancelar'] ?? [])), fn($v) => $v > 0);

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$aluno = $pdo->prepare("SELECT id FROM alunos WHERE id = ?");
$aluno->execute([$alunoId]);
if (!$aluno->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE alunos SET status = 'inativo', atualizado_em = NOW() WHERE id = ?")->execute([$alunoId]);

    // Pausa as matrículas em turma — é o mesmo filtro (turma_alunos.status = 'ativo') usado pela
    // geração mensal de mensalidades (gerar_mensalidades.php / auth_check.php) e pelos lembretes
    // de treino, então isso já garante que nada novo será gerado/enviado pra esse aluno.
    $pdo->prepare("UPDATE turma_alunos SET status = 'inativo' WHERE aluno_id = ? AND status = 'ativo'")->execute([$alunoId]);

    if (!empty($cancelarIds)) {
        $placeholders = implode(',', array_fill(0, count($cancelarIds), '?'));
        $stmt = $pdo->prepare("
            DELETE FROM mensalidades
            WHERE aluno_id = ? AND status IN ('pendente', 'atrasado') AND id IN ($placeholders)
        ");
        $stmt->execute(array_merge([$alunoId], $cancelarIds));
    }

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao desativar aluno: ' . $e->getMessage()]);
}
