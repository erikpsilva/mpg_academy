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

if ($id <= 0 || !in_array($action, ['realizar', 'cancelar', 'promover', 'reagendar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/aulas_teste.php';
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

    // Vaga é por data: quem sai da fila ocupa lugar no dia em que está marcado, e a
    // própria inscrição não pode contar contra ela mesma (por isso o $id no fim).
    $turmaStmt = $pdo->prepare("SELECT max_alunos FROM turmas WHERE id = ?");
    $turmaStmt->execute([$turmaId]);
    $turmaData = $turmaStmt->fetch(PDO::FETCH_ASSOC);
    $maxAlunos = ($turmaData && $turmaData['max_alunos'] !== null) ? (int) $turmaData['max_alunos'] : null;

    if (!aulaTesteCabeNaData($pdo, $turmaId, $aula['data_agendada'] ?? null, $maxAlunos, $id)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Sem vagas de teste nessa data. Reagende para outro dia antes de promover.',
        ]);
        exit;
    }

    $pdo->prepare("UPDATE aulas_experimentais SET status = 'agendada' WHERE id = ?")->execute([$id]);

} elseif ($action === 'reagendar') {
    // Reagenda tanto um teste cancelado quanto um que ainda está agendado/na fila
    // (troca de data e/ou turma). Em todos os casos o registro volta para 'agendada'.
    if (!in_array($aula['status'], ['cancelada', 'agendada', 'fila'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Este registro não pode ser reagendado.']);
        exit;
    }

    $dataAgendada = trim($_POST['data_agendada'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAgendada)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Informe a nova data do teste.']);
        exit;
    }

    // Permite escolher outra turma; default mantém a turma original
    $turmaConfirmada = $turmaId;
    if (!empty($_POST['turma_id'])) {
        $candidata = (int) $_POST['turma_id'];
        if ($candidata > 0) $turmaConfirmada = $candidata;
    }

    $turmaStmt = $pdo->prepare("
        SELECT t.nome, t.max_alunos,
               q.nome AS quadra_nome, q.rua, q.numero, q.bairro, q.complemento, q.cidade, q.estado, q.maps_link
        FROM turmas t
        LEFT JOIN quadras q ON q.id = t.quadra_id
        WHERE t.id = ? AND t.status = 'ativa'
    ");
    $turmaStmt->execute([$turmaConfirmada]);
    $turmaData = $turmaStmt->fetch(PDO::FETCH_ASSOC);

    if (!$turmaData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Turma não encontrada ou inativa.']);
        exit;
    }

    // Vaga é da DATA nova, não da turma inteira: mover alguém pro dia 28/08 só depende de
    // quantos já estão marcados no 28/08. O $id no fim tira o próprio registro da conta —
    // senão reagendar dentro da mesma data acusaria "sem vagas" contra si mesmo.
    $maxAlunos = $turmaData['max_alunos'] !== null ? (int) $turmaData['max_alunos'] : null;

    if (!aulaTesteCabeNaData($pdo, $turmaConfirmada, $dataAgendada, $maxAlunos, $id)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Sem vagas de teste nessa turma para o dia ' . date('d/m/Y', strtotime($dataAgendada)) . '.',
        ]);
        exit;
    }

    $pdo->prepare("UPDATE aulas_experimentais SET status = 'agendada', turma_id = ?, data_agendada = ? WHERE id = ?")
        ->execute([$turmaConfirmada, $dataAgendada, $id]);

    // Dispara WhatsApp de reagendamento
    $alunoStmt = $pdo->prepare("SELECT nome, celular, is_menor, responsavel_nome, responsavel_celular FROM alunos_teste WHERE id = ?");
    $alunoStmt->execute([(int) $aula['aluno_teste_id']]);
    $alunoData = $alunoStmt->fetch(PDO::FETCH_ASSOC);

    if ($alunoData) {
        $diasSemana = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
        $meses      = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
        $dt         = new DateTime($dataAgendada);
        $dataFmt    = $dt->format('d') . ' de ' . $meses[(int) $dt->format('n') - 1] . ' de ' . $dt->format('Y')
                    . ' (' . $diasSemana[(int) $dt->format('w')] . ')';

        $horarioStmt = $pdo->prepare("
            SELECT qh.hora_inicio, qh.hora_fim
            FROM turma_horarios th
            JOIN quadra_horarios qh ON qh.id = th.horario_id
            WHERE th.turma_id = ?
            ORDER BY qh.hora_inicio
            LIMIT 1
        ");
        $horarioStmt->execute([$turmaConfirmada]);
        $horario = $horarioStmt->fetch(PDO::FETCH_ASSOC);

        $horarioFmt = $horario
            ? 'das ' . substr($horario['hora_inicio'], 0, 5) . 'h às ' . substr($horario['hora_fim'], 0, 5) . 'h'
            : 'a confirmar';

        require_once dirname(__FILE__, 3) . '/services/whatsapp/wpp_aula_teste_confirmacao.php';
        wppAulaTesteReagendada($alunoData, $turmaData, $dataFmt, $horarioFmt);
    }
}

echo json_encode(['success' => true]);
