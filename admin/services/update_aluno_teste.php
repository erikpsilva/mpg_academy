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

$aulaId           = (int)($_POST['id']                ?? 0);
$nome             = trim($_POST['nome']               ?? '');
$email            = trim($_POST['email']              ?? '') ?: null;
$celular          = trim($_POST['celular']            ?? '') ?: null;
$isMenor            = ($_POST['is_menor'] ?? '0') === '1' ? 1 : 0;
$dataNascimento     = $isMenor ? (trim($_POST['data_nascimento']     ?? '') ?: null) : null;
$responsavelNome    = $isMenor ? (trim($_POST['responsavel_nome']    ?? '') ?: null) : null;
$responsavelEmail   = $isMenor ? (trim($_POST['responsavel_email']   ?? '') ?: null) : null;
$responsavelCpf     = $isMenor ? (trim($_POST['responsavel_cpf']     ?? '') ?: null) : null;
$responsavelCelular = $isMenor ? (trim($_POST['responsavel_celular'] ?? '') ?: null) : null;
$turmaId          = (int)($_POST['turma_id']          ?? 0);
$dataAgendada     = trim($_POST['data_agendada']      ?? '') ?: null;

if (!$aulaId || !$nome || !$turmaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}
if ($dataNascimento && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNascimento)) $dataNascimento = null;
if ($dataAgendada   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAgendada))   $dataAgendada   = null;

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Busca o aluno_teste_id a partir do id da aula experimental
$stmt = $pdo->prepare("SELECT aluno_teste_id FROM aulas_experimentais WHERE id = ? LIMIT 1");
$stmt->execute([$aulaId]);
$aula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aula) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Registro não encontrado.']);
    exit;
}

$alunoTesteId = (int)$aula['aluno_teste_id'];

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE alunos_teste
        SET nome = ?, email = ?, celular = ?,
            is_menor = ?, data_nascimento = ?, responsavel_nome = ?,
            responsavel_email = ?, responsavel_cpf = ?, responsavel_celular = ?
        WHERE id = ?
    ")->execute([$nome, $email, $celular, $isMenor, $dataNascimento, $responsavelNome, $responsavelEmail, $responsavelCpf, $responsavelCelular, $alunoTesteId]);

    $pdo->prepare("
        UPDATE aulas_experimentais SET turma_id = ?, data_agendada = ? WHERE id = ?
    ")->execute([$turmaId, $dataAgendada, $aulaId]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar.']);
}
