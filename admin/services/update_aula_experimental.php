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

$id     = (int) ($_POST['id']     ?? 0);
$action = trim($_POST['action']   ?? '');

if ($id <= 0 || !in_array($action, ['realizar', 'cancelar', 'promover'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Busca a entrada atual
$aulaStmt = $pdo->prepare("SELECT * FROM aulas_experimentais WHERE id = ?");
$aulaStmt->execute([$id]);
$aula = $aulaStmt->fetch(PDO::FETCH_ASSOC);

if (!$aula) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Registro não encontrado.']);
    exit;
}

$turmaId = (int) $aula['turma_id'];

if ($action === 'realizar') {
    if ($aula['status'] !== 'agendada') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Apenas aulas agendadas podem ser marcadas como realizadas.']);
        exit;
    }

    // Permite confirmar/corrigir em qual turma a aula teste foi de fato realizada
    // (ex: aluno estava agendado numa turma mas testou em outra por troca de horário).
    $turmaConfirmada = $turmaId;
    if (!empty($_POST['turma_id'])) {
        $candidata = (int) $_POST['turma_id'];
        if ($candidata > 0 && $candidata !== $turmaId) {
            $checkTurma = $pdo->prepare("SELECT id FROM turmas WHERE id = ?");
            $checkTurma->execute([$candidata]);
            if ($checkTurma->fetch()) {
                $turmaConfirmada = $candidata;
            }
        }
    }

    $pdo->prepare("UPDATE aulas_experimentais SET status = 'realizada', turma_id = ? WHERE id = ?")
        ->execute([$turmaConfirmada, $id]);

} elseif ($action === 'cancelar') {
    if (!in_array($aula['status'], ['agendada', 'fila'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Este registro não pode ser cancelado.']);
        exit;
    }
    $pdo->prepare("UPDATE aulas_experimentais SET status = 'cancelada' WHERE id = ?")->execute([$id]);

} elseif ($action === 'promover') {
    if ($aula['status'] !== 'fila') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Apenas entradas na fila podem ser promovidas.']);
        exit;
    }

    // Verifica se há vaga disponível para o teste
    $turmaStmt = $pdo->prepare("SELECT max_alunos FROM turmas WHERE id = ?");
    $turmaStmt->execute([$turmaId]);
    $turmaData = $turmaStmt->fetch(PDO::FETCH_ASSOC);

    if ($turmaData && $turmaData['max_alunos'] !== null) {
        $stmtAtivos = $pdo->prepare(
            "SELECT COUNT(*) FROM turma_alunos WHERE turma_id = ? AND status = 'ativo'"
        );
        $stmtAtivos->execute([$turmaId]);
        $countAtivos = (int) $stmtAtivos->fetchColumn();

        $stmtAgend = $pdo->prepare(
            "SELECT COUNT(*) FROM aulas_experimentais WHERE turma_id = ? AND status = 'agendada'"
        );
        $stmtAgend->execute([$turmaId]);
        $countAgendadas = (int) $stmtAgend->fetchColumn();

        $vagasTeste = (int) $turmaData['max_alunos'] - $countAtivos - $countAgendadas;
        if ($vagasTeste <= 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Sem vagas de teste disponíveis para promover.']);
            exit;
        }
    }

    $pdo->prepare("UPDATE aulas_experimentais SET status = 'agendada' WHERE id = ?")->execute([$id]);
}

echo json_encode(['success' => true]);
