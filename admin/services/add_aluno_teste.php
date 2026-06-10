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

$nome             = trim($_POST['nome']             ?? '');
$email            = trim($_POST['email']            ?? '') ?: null;
$celular          = trim($_POST['celular']          ?? '') ?: null;
$turmaId          = (int) ($_POST['turma_id']       ?? 0);
$dataAgendada     = trim($_POST['data_agendada']    ?? '');
$isMenor           = ($_POST['is_menor'] ?? '0') === '1' ? 1 : 0;
$dataNascimento    = $isMenor ? (trim($_POST['data_nascimento']    ?? '') ?: null) : null;
$responsavelNome   = $isMenor ? (trim($_POST['responsavel_nome']   ?? '') ?: null) : null;
$responsavelEmail  = $isMenor ? (trim($_POST['responsavel_email']  ?? '') ?: null) : null;
$responsavelCpf    = $isMenor ? (trim($_POST['responsavel_cpf']    ?? '') ?: null) : null;
$responsavelCelular = $isMenor ? (trim($_POST['responsavel_celular'] ?? '') ?: null) : null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAgendada)) {
    $dataAgendada = null;
}
if ($dataNascimento && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNascimento)) {
    $dataNascimento = null;
}

if (!$nome || $turmaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome e turma são obrigatórios.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Bloqueia e-mail duplicado em qualquer status ativo
if ($email) {
    $checkStmt = $pdo->prepare("
        SELECT ae.id, ae.status FROM aulas_experimentais ae
        JOIN alunos_teste at ON at.id = ae.aluno_teste_id
        WHERE at.email = ? AND ae.status IN ('agendada', 'fila', 'realizada')
        LIMIT 1
    ");
    $checkStmt->execute([$email]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $msg = $existing['status'] === 'realizada'
            ? 'Este e-mail já realizou uma aula experimental.'
            : 'Este e-mail já está cadastrado em uma aula experimental (agendada ou na fila).';
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
}

// Verifica se a turma existe e está ativa
$turmaStmt = $pdo->prepare("
    SELECT t.max_alunos, t.nome,
           q.nome AS quadra_nome, q.rua, q.numero, q.bairro, q.complemento, q.cidade, q.estado
    FROM turmas t
    LEFT JOIN quadras q ON q.id = t.quadra_id
    WHERE t.id = ? AND t.status = 'ativa'
");
$turmaStmt->execute([$turmaId]);
$turmaData = $turmaStmt->fetch(PDO::FETCH_ASSOC);

if (!$turmaData) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Turma não encontrada ou inativa.']);
    exit;
}

// Calcula vagas disponíveis para teste
$status = 'agendada';
if ($turmaData['max_alunos'] !== null) {
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
        $status = 'fila';
    }
}

try {
    $pdo->beginTransaction();

    // Insere o aluno teste
    $insAluno = $pdo->prepare(
        "INSERT INTO alunos_teste (nome, email, celular, is_menor, data_nascimento, responsavel_nome, responsavel_email, responsavel_cpf, responsavel_celular)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insAluno->execute([$nome, $email, $celular, $isMenor, $dataNascimento, $responsavelNome, $responsavelEmail, $responsavelCpf, $responsavelCelular]);
    $alunoTesteId = (int) $pdo->lastInsertId();

    // Insere a aula experimental
    $insAula = $pdo->prepare(
        "INSERT INTO aulas_experimentais (aluno_teste_id, turma_id, status, data_agendada)
         VALUES (?, ?, ?, ?)"
    );
    $insAula->execute([$alunoTesteId, $turmaId, $status, $dataAgendada]);
    $aulaId = (int) $pdo->lastInsertId();

    // Para menores: cria o termo automaticamente dentro da transação
    $termoToken = null;
    if ($isMenor) {
        $termoToken = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO termo_assinaturas (aula_experimental_id, token) VALUES (?, ?)")
            ->execute([$aulaId, $termoToken]);
    }

    $pdo->commit();

    // Envia email de confirmação se agendada, tiver email e data definida
    if ($status === 'agendada' && $email && $dataAgendada) {
        $diasSemana = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
        $meses      = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
        $dt         = new DateTime($dataAgendada);
        $dataFmt    = $dt->format('d') . ' de ' . $meses[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y')
                    . ' (' . $diasSemana[(int)$dt->format('w')] . ')';

        $horarioStmt = $pdo->prepare("
            SELECT qh.hora_inicio, qh.hora_fim
            FROM turma_horarios th
            JOIN quadra_horarios qh ON qh.id = th.horario_id
            WHERE th.turma_id = ?
            ORDER BY qh.hora_inicio
            LIMIT 1
        ");
        $horarioStmt->execute([$turmaId]);
        $horario = $horarioStmt->fetch(PDO::FETCH_ASSOC);

        $horarioFmt = $horario
            ? 'das ' . substr($horario['hora_inicio'], 0, 5) . 'h às ' . substr($horario['hora_fim'], 0, 5) . 'h'
            : 'a confirmar';

        $endFmt = '';
        if (!empty($turmaData['rua'])) {
            $endFmt = $turmaData['rua'] . ', ' . $turmaData['numero'];
            if (!empty($turmaData['complemento'])) $endFmt .= ' - ' . $turmaData['complemento'];
            $endFmt .= ' - ' . $turmaData['bairro'] . ', ' . $turmaData['cidade'] . '/' . $turmaData['estado'];
        }

        require_once dirname(__FILE__, 3) . '/config/app.php';
        require_once dirname(__FILE__, 3) . '/services/site/email_template.php';
        require_once dirname(__FILE__, 3) . '/services/whatsapp/wpp_aula_teste_confirmacao.php';
        require_once dirname(__FILE__, 3) . '/services/site/notificar_termo_responsavel.php';

        // E-mail de confirmação para o aluno
        sendMpgTesteConfirmation($email, $nome, $turmaData['nome'], $dataFmt, $horarioFmt, $endFmt);

        // WhatsApp de confirmação (aluno + responsável se menor)
        $termoUrl = ($isMenor && $termoToken)
            ? BASE_URL . '/termo?token=' . $termoToken
            : '';

        $alunoWpp = [
            'nome'                => $nome,
            'celular'             => $celular,
            'is_menor'            => $isMenor,
            'responsavel_nome'    => $responsavelNome,
            'responsavel_celular' => $responsavelCelular,
        ];
        wppAulaTesteConfirmacao($alunoWpp, $turmaData, $dataFmt, $horarioFmt, $termoUrl);

        // E-mail + WhatsApp do termo para o responsável (menor)
        // WPP já enviado junto com a confirmação em wppAulaTesteConfirmacao(); só envia email aqui
        if ($isMenor && $termoUrl && $responsavelEmail) {
            notificarTermoResponsavel([
                'responsavel_email'   => $responsavelEmail,
                'responsavel_nome'    => $responsavelNome,
                'responsavel_celular' => $responsavelCelular,
                'aluno_nome'          => $nome,
                'turma_nome'          => $turmaData['nome'],
            ], $termoUrl, true);
        }
    }

    echo json_encode([
        'success' => true,
        'status'  => $status,
        'id'      => $aulaId,
        'aluno'   => ['id' => $alunoTesteId, 'nome' => $nome, 'email' => $email, 'celular' => $celular],
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar aluno teste.']);
}
