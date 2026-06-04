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

$filaId = (int) ($_POST['fila_id'] ?? 0);

if ($filaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Busca entrada da fila
$filaStmt = $pdo->prepare("SELECT * FROM fila_espera WHERE id = ? AND status = 'aguardando'");
$filaStmt->execute([$filaId]);
$fila = $filaStmt->fetch(PDO::FETCH_ASSOC);

if (!$fila) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Entrada não encontrada na fila de espera.']);
    exit;
}

$turmaId      = (int) $fila['turma_id'];
$alunoTesteId = !empty($fila['aluno_teste_id']) ? (int) $fila['aluno_teste_id'] : null;

// ── Aluno teste: envia email de cadastro ──────────────────────────────────────
if ($alunoTesteId !== null) {
    $atStmt = $pdo->prepare("SELECT nome, email FROM alunos_teste WHERE id = ?");
    $atStmt->execute([$alunoTesteId]);
    $aluno = $atStmt->fetch(PDO::FETCH_ASSOC);

    if (!$aluno || empty($aluno['email'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Aluno não possui e-mail cadastrado.']);
        exit;
    }

    // Marca como promovido
    $pdo->prepare("UPDATE fila_espera SET status = 'promovido' WHERE id = ?")->execute([$filaId]);

    // Envia email de cadastro via serviço existente
    require_once dirname(__FILE__, 3) . '/config/app.php';
    require_once dirname(__FILE__, 3) . '/config/mail.php';
    require_once dirname(__FILE__, 3) . '/services/site/email_template.php';

    $nome         = $aluno['nome'];
    $email        = $aluno['email'];
    $primeiroNome = explode(' ', $nome)[0];
    $cadastroUrl  = appBaseUrl() . '/cadastro';

    $turmaStmt = $pdo->prepare("SELECT t.nome AS turma, q.nome AS quadra FROM turmas t JOIN quadras q ON q.id = t.quadra_id WHERE t.id = ?");
    $turmaStmt->execute([$turmaId]);
    $turmaInfo = $turmaStmt->fetch(PDO::FETCH_ASSOC);
    $turmaNome = $turmaInfo ? $turmaInfo['turma'] . ' — ' . $turmaInfo['quadra'] : '';

    $mensagemExtra = $turmaNome
        ? "Uma vaga foi aberta para você na turma <strong>" . htmlspecialchars($turmaNome) . "</strong>. Finalize seu cadastro para garantir sua vaga!"
        : "Uma vaga foi aberta para você! Finalize seu cadastro para garantir sua vaga.";

    $subject = 'Sua vaga abriu! Finalize seu cadastro — MPG Academy';

    // Reutiliza a função de email do serviço existente
    $sent = sendMpgSignupConfirmation($email, $nome);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'E-mail de cadastro enviado para ' . $email . '.']);
    } else {
        // Mesmo com falha no email, a promoção já foi registrada
        echo json_encode(['success' => true, 'message' => 'Promovido, mas houve falha ao enviar o e-mail.']);
    }
    exit;
}

// ── Aluno regular (fluxo legado) ──────────────────────────────────────────────
$alunoId    = (int) $fila['aluno_id'];
$dataInicio = trim($_POST['data_inicio'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
    $dataInicio = date('Y-m-d');
}

$turmaInfo = $pdo->prepare("SELECT max_alunos FROM turmas WHERE id = ? AND status = 'ativa'");
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
    $pdo->prepare("INSERT INTO turma_alunos (turma_id, aluno_id, data_entrada) VALUES (?, ?, ?)")
        ->execute([$turmaId, $alunoId, $dataInicio]);
    $pdo->prepare("UPDATE fila_espera SET status = 'promovido' WHERE id = ?")->execute([$filaId]);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Aluno promovido para a turma com sucesso.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao promover aluno.']);
}
