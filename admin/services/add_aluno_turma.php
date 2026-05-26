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

$turmaId    = (int) ($_POST['turma_id'] ?? 0);
$alunoId    = (int) ($_POST['aluno_id'] ?? 0);
$dataInicio = trim($_POST['data_inicio'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
    $dataInicio = date('Y-m-d');
}

if ($turmaId <= 0 || $alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDs inválidos.']);
    exit;
}

$pdo = getDbConnection();

// Fetch aluno data to return
$aluno = $pdo->prepare("SELECT id, nome, email, celular FROM alunos WHERE id = ? AND status = 'ativo'");
$aluno->execute([$alunoId]);
$aluno = $aluno->fetch();

if (!$aluno) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado ou inativo.']);
    exit;
}

// Fetch turma info (promo + capacidade)
$turmaInfo = $pdo->prepare("SELECT valor_mensalidade, promo_valor, promo_meses, max_alunos FROM turmas WHERE id = ? AND status = 'ativa'");
$turmaInfo->execute([$turmaId]);
$turmaData = $turmaInfo->fetch();

// Verifica capacidade máxima
if ($turmaData && $turmaData['max_alunos'] !== null) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM turma_alunos WHERE turma_id = ? AND status = 'ativo'");
    $countStmt->execute([$turmaId]);
    $alunosAtivos = (int) $countStmt->fetchColumn();
    if ($alunosAtivos >= (int) $turmaData['max_alunos']) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Esta turma já atingiu o limite máximo de alunos.']);
        exit;
    }
}

// Calculate promo discount if applicable
$desconto          = null;
$descontoTipo      = 'fixo';
$descontoInicio    = null;
$descontoFim       = null;
$descontoVitalicio = 0;

if ($turmaData &&
    $turmaData['promo_valor'] !== null &&
    $turmaData['promo_meses'] !== null &&
    $turmaData['valor_mensalidade'] !== null &&
    (float) $turmaData['promo_valor'] < (float) $turmaData['valor_mensalidade']
) {
    $today  = new DateTime($dataInicio);
    $day    = (int) $today->format('j');
    $year   = (int) $today->format('Y');
    $month  = (int) $today->format('n');

    // Next occurrence of day 5
    if ($day <= 5) {
        $nextDay5 = new DateTime(sprintf('%04d-%02d-05', $year, $month));
    } elseif ($month === 12) {
        $nextDay5 = new DateTime(sprintf('%04d-%02d-05', $year + 1, 1));
    } else {
        $nextDay5 = new DateTime(sprintf('%04d-%02d-05', $year, $month + 1));
    }

    $daysToNext5 = (int) $today->diff($nextDay5)->days;
    $promoMeses  = (int) $turmaData['promo_meses'];

    $promoFim = clone $nextDay5;
    if ($daysToNext5 < 10) {
        // Less than 10 days to payment day → partial doesn't count → full promo_meses from next day 5
        $promoFim->modify('+' . $promoMeses . ' months');
    } else {
        // Partial month counts as month 1 → (promo_meses - 1) more full months
        $promoFim->modify('+' . max(0, $promoMeses - 1) . ' months');
    }

    $desconto       = round((float) $turmaData['valor_mensalidade'] - (float) $turmaData['promo_valor'], 2);
    $descontoInicio = $dataInicio;
    $descontoFim    = $promoFim->format('Y-m-d');
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO turma_alunos
            (turma_id, aluno_id, data_entrada, desconto, desconto_tipo, desconto_inicio, desconto_fim, desconto_vitalicio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$turmaId, $alunoId, $dataInicio, $desconto, $descontoTipo, $descontoInicio, $descontoFim, $descontoVitalicio]);

    $turmaStmt = $pdo->prepare("
        SELECT t.id, t.nome, t.valor_mensalidade, t.promo_valor, t.promo_meses, q.nome AS quadra_nome
        FROM turmas t LEFT JOIN quadras q ON q.id = t.quadra_id
        WHERE t.id = ?
    ");
    $turmaStmt->execute([$turmaId]);
    $turma = $turmaStmt->fetch();
    $turma['data_entrada'] = $dataInicio;

    // Valor efetivo para exibição imediata
    $valorEfetivo = $turma['valor_mensalidade'];
    if ($desconto !== null && $desconto > 0) {
        $valorEfetivo = max(0, (float)$turma['valor_mensalidade'] - $desconto);
    } elseif ($turma['promo_valor'] !== null && $turma['promo_meses'] !== null
              && (float)$turma['promo_valor'] < (float)$turma['valor_mensalidade']) {
        $fimPromo = date('Y-m-d', strtotime($dataInicio . ' +' . $turma['promo_meses'] . ' months'));
        if ($fimPromo >= $dataInicio) {
            $valorEfetivo = (float)$turma['promo_valor'];
        }
    }
    $turma['valor_efetivo'] = $valorEfetivo;

    echo json_encode(['success' => true, 'aluno' => $aluno, 'aluno_turma' => $turma]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'O aluno já faz parte dessa turma.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar aluno.']);
    }
}
