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

$filaId  = (int) ($_POST['fila_id']  ?? 0);
$dataInicio = trim($_POST['data_inicio'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
    $dataInicio = date('Y-m-d');
}

if ($filaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Busca entrada da fila
$fila = $pdo->prepare("SELECT * FROM fila_espera WHERE id = ? AND status = 'aguardando'");
$fila->execute([$filaId]);
$fila = $fila->fetch(PDO::FETCH_ASSOC);

if (!$fila) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Entrada não encontrada na fila de espera.']);
    exit;
}

$turmaId = (int) $fila['turma_id'];
$alunoId = (int) $fila['aluno_id'];

// Verifica vaga disponível
$turmaInfo = $pdo->prepare("SELECT valor_mensalidade, promo_valor, promo_meses, max_alunos FROM turmas WHERE id = ? AND status = 'ativa'");
$turmaInfo->execute([$turmaId]);
$turmaData = $turmaInfo->fetch(PDO::FETCH_ASSOC);

if ($turmaData && $turmaData['max_alunos'] !== null) {
    $count = $pdo->prepare("SELECT COUNT(*) FROM turma_alunos WHERE turma_id = ? AND status = 'ativo'");
    $count->execute([$turmaId]);
    if ((int) $count->fetchColumn() >= (int) $turmaData['max_alunos']) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A turma ainda não possui vagas disponíveis.']);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    // Insere na turma
    $pdo->prepare("
        INSERT INTO turma_alunos (turma_id, aluno_id, data_entrada)
        VALUES (?, ?, ?)
    ")->execute([$turmaId, $alunoId, $dataInicio]);

    // Marca como promovido na fila
    $pdo->prepare("UPDATE fila_espera SET status = 'promovido' WHERE id = ?")->execute([$filaId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Aluno promovido para a turma com sucesso.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao promover aluno.']);
}
