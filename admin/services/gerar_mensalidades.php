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

// Modelo pré-pago: ciclo fecha dia 30, fatura gerada para o mês SEGUINTE com vencimento dia 5.
// Executar no dia 30 de cada mês.
$hoje       = new DateTime();
$proxMes    = (clone $hoje)->modify('first day of next month');
$referencia = $proxMes->format('Y-m');            // mês que o aluno vai usar
$vencimento = $proxMes->format('Y-m') . '-05';    // paga antes de começar a usar
$hojeStr    = $hoje->format('Y-m-d');

$pdo = getDbConnection();

$stAtivos = $pdo->query("
    SELECT ta.aluno_id, ta.turma_id,
           ta.desconto, ta.desconto_tipo, ta.desconto_inicio, ta.desconto_fim, ta.desconto_vitalicio,
           t.valor_mensalidade
    FROM turma_alunos ta
    JOIN turmas t ON t.id = ta.turma_id
    WHERE ta.status = 'ativo'
      AND t.status  = 'ativa'
      AND t.valor_mensalidade IS NOT NULL
");
$ativos = $stAtivos->fetchAll();

$geradas = 0;
$puladas = 0;
$erros   = 0;

$stCheck = $pdo->prepare("
    SELECT id FROM mensalidades WHERE aluno_id = ? AND turma_id = ? AND referencia = ?
");
$stInsert = $pdo->prepare("
    INSERT INTO mensalidades (aluno_id, turma_id, referencia, valor, vencimento, status)
    VALUES (?, ?, ?, ?, ?, 'pendente')
");

foreach ($ativos as $ta) {
    // Pula se já existe mensalidade para esta referência
    $stCheck->execute([$ta['aluno_id'], $ta['turma_id'], $referencia]);
    if ($stCheck->fetchColumn()) {
        $puladas++;
        continue;
    }

    $valorBase = (float) $ta['valor_mensalidade'];

    // Verifica se desconto pessoal está vigente
    $descontoAtivo = $ta['desconto'] !== null && $ta['desconto'] > 0 && (
        $ta['desconto_vitalicio'] ||
        ($ta['desconto_inicio'] === null && $ta['desconto_fim'] === null) ||
        ($ta['desconto_inicio'] <= $hojeStr && $ta['desconto_fim'] >= $hojeStr)
    );

    if ($descontoAtivo) {
        $valor = $ta['desconto_tipo'] === 'percentual'
            ? round($valorBase * (1 - $ta['desconto'] / 100), 2)
            : max(0, round($valorBase - (float) $ta['desconto'], 2));
    } else {
        $valor = $valorBase;
    }

    try {
        $stInsert->execute([$ta['aluno_id'], $ta['turma_id'], $referencia, $valor, $vencimento]);
        $geradas++;
    } catch (PDOException $e) {
        $erros++;
    }
}

echo json_encode([
    'success'    => true,
    'referencia' => $referencia,
    'vencimento' => (new DateTime($vencimento))->format('d/m/Y'),
    'geradas'    => $geradas,
    'puladas'    => $puladas,
    'erros'      => $erros,
]);
