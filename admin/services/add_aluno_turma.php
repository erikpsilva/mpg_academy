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

function contarAulasTurma(PDO $pdo, int $turmaId, string $dataInicio, string $dataFim): int {
    $st = $pdo->prepare("
        SELECT DISTINCT qh.dia_semana
        FROM turma_horarios th
        JOIN quadra_horarios qh ON qh.id = th.horario_id
        WHERE th.turma_id = ?
    ");
    $st->execute([$turmaId]);
    $diasSemana = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (empty($diasSemana)) return 0;

    $st2 = $pdo->prepare("
        SELECT DATE_FORMAT(data, '%Y-%m-%d')
        FROM aulas_canceladas
        WHERE data BETWEEN ? AND ? AND (turma_id = ? OR turma_id IS NULL)
    ");
    $st2->execute([$dataInicio, $dataFim, $turmaId]);
    $canceladas = $st2->fetchAll(PDO::FETCH_COLUMN);

    $count   = 0;
    $current = new DateTime($dataInicio);
    $fim     = new DateTime($dataFim);
    while ($current <= $fim) {
        $dow = (int) $current->format('w');
        if (in_array($dow, $diasSemana, true) && !in_array($current->format('Y-m-d'), $canceladas, true)) {
            $count++;
        }
        $current->modify('+1 day');
    }
    return $count;
}

function calcProporcional(PDO $pdo, int $turmaId, DateTime $entrada, string $dataInicio, float $baseValor): float {
    $fechamentoDia  = min(30, (int) $entrada->format('t'));
    $fimCiclo       = $entrada->format('Y-m') . '-' . str_pad($fechamentoDia, 2, '0', STR_PAD_LEFT);
    $iniCiclo       = $entrada->format('Y-m-01');

    $totalAulas     = contarAulasTurma($pdo, $turmaId, $iniCiclo, $fimCiclo);
    $aulasPendentes = contarAulasTurma($pdo, $turmaId, $dataInicio, $fimCiclo);

    if ($totalAulas > 0) {
        return round(($aulasPendentes / $totalAulas) * $baseValor, 2);
    }
    // Fallback: proporcional por dias quando a turma não tem horários cadastrados
    $daysInMonth = (int) $entrada->format('t');
    $entryDay    = (int) $entrada->format('j');
    $daysUsed    = $daysInMonth - $entryDay + 1;
    return round(($daysUsed / $daysInMonth) * $baseValor, 2);
}

$aluno = $pdo->prepare("SELECT id, nome, email, celular, matricula_cobrada FROM alunos WHERE id = ? AND status = 'ativo'");
$aluno->execute([$alunoId]);
$aluno = $aluno->fetch();

if (!$aluno) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado ou inativo.']);
    exit;
}

$turmaInfo = $pdo->prepare("SELECT valor_mensalidade, promo_valor, promo_meses, max_alunos FROM turmas WHERE id = ? AND status = 'ativa'");
$turmaInfo->execute([$turmaId]);
$turmaData = $turmaInfo->fetch();

if ($turmaData && $turmaData['max_alunos'] !== null) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM turma_alunos WHERE turma_id = ? AND status = 'ativo'");
    $countStmt->execute([$turmaId]);
    if ((int) $countStmt->fetchColumn() >= (int) $turmaData['max_alunos']) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Esta turma já atingiu o limite máximo de alunos.']);
        exit;
    }
}

$desconto          = null;
$descontoTipo      = 'fixo';
$descontoInicio    = null;
$descontoFim       = null;
$descontoVitalicio = 0;
$mensalidadesParaGerar = [];

if ($turmaData && $turmaData['valor_mensalidade'] !== null) {
    $entrada          = new DateTime($dataInicio);
    $entryDay         = (int) $entrada->format('j');
    $valorBase        = (float) $turmaData['valor_mensalidade'];
    // Prazo de 5 dias para o aluno pagar a primeira fatura
    $vencInicial      = (clone $entrada)->modify('+5 days')->format('Y-m-d');

    $temPromo = $turmaData['promo_valor'] !== null
             && $turmaData['promo_meses'] !== null
             && (float) $turmaData['promo_valor'] < $valorBase;

    // Exceção: aluno entrou nos 5 primeiros dias do mês (dentro de 5 dias após o fechamento
    // do ciclo no dia 30 do mês anterior) → mês cheio, sem cobrança proporcional
    $isExcecao = ($entryDay <= 5);

    if ($temPromo) {
        $promoValor = (float) $turmaData['promo_valor'];
        $promoMeses = (int) $turmaData['promo_meses'];

        if ($isExcecao) {
            // Dia 1–5: mês cheio ao valor promocional, pago na entrada.
            // Mês de entrada = mês 1 da promoção. Os meses seguintes são gerados pelo cron.
            $mensalidadesParaGerar[] = [
                'referencia' => $entrada->format('Y-m'),
                'valor'      => $promoValor,
                'vencimento' => $vencInicial,
            ];

            // descontoFim = 1º dia do mês (promo_meses - 1) após o mês de entrada.
            // Garante que o cron aplica desconto apenas nos meses 2..promo_meses.
            $fimPromo = new DateTime($entrada->format('Y-m') . '-01');
            $fimPromo->modify('+' . ($promoMeses - 1) . ' months');

            $desconto       = round($valorBase - $promoValor, 2);
            $descontoInicio = $dataInicio;
            $descontoFim    = $fimPromo->format('Y-m-d');

        } else {
            // Dia 6–30: fatura proporcional (aulas restantes no ciclo) ao valor promocional.
            $proportional = calcProporcional($pdo, $turmaId, $entrada, $dataInicio, $promoValor);

            $mensalidadesParaGerar[] = [
                'referencia' => $entrada->format('Y-m'),
                'valor'      => $proportional,
                'vencimento' => $vencInicial,
            ];

            $nextMonth = new DateTime($entrada->format('Y-m') . '-01');
            $nextMonth->modify('+1 month');

            // descontoFim = 1º dia do mês (promo_meses - 1) após o nextMonth.
            // Cron aplica desconto nos meses 1..promo_meses (meses completos após o proporcional).
            $fimPromo = clone $nextMonth;
            $fimPromo->modify('+' . ($promoMeses - 1) . ' months');

            $desconto       = round($valorBase - $promoValor, 2);
            $descontoInicio = $dataInicio;
            $descontoFim    = $fimPromo->format('Y-m-d');
        }

    } else {
        // Sem promoção: apenas a primeira fatura (proporcional ou mês cheio), paga na entrada.
        if ($isExcecao) {
            $mensalidadesParaGerar[] = [
                'referencia' => $entrada->format('Y-m'),
                'valor'      => $valorBase,
                'vencimento' => $vencInicial,
            ];
        } else {
            $proportional = calcProporcional($pdo, $turmaId, $entrada, $dataInicio, $valorBase);

            $mensalidadesParaGerar[] = [
                'referencia' => $entrada->format('Y-m'),
                'valor'      => $proportional,
                'vencimento' => $vencInicial,
            ];
        }
    }
}

// Verifica se deve cobrar matrícula (uma única vez por aluno)
$matriculaValor = 0.0;
if (!$aluno['matricula_cobrada']) {
    $cfgSt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'valor_matricula'");
    $cfgSt->execute();
    $cfgRow = $cfgSt->fetch();
    $matriculaValor = $cfgRow ? (float) $cfgRow['valor'] : 0.0;
}

if ($matriculaValor > 0 && !empty($mensalidadesParaGerar)) {
    $mensalidadesParaGerar[0]['valor']          = round($mensalidadesParaGerar[0]['valor'] + $matriculaValor, 2);
    $mensalidadesParaGerar[0]['matricula_valor'] = $matriculaValor;
}

try {
    $pdo->beginTransaction();

    // INSERT com ON DUPLICATE KEY UPDATE para permitir re-adicionar aluno
    // que já foi removido (UNIQUE KEY uk_turma_aluno)
    $stmt = $pdo->prepare("
        INSERT INTO turma_alunos
            (turma_id, aluno_id, data_entrada, desconto, desconto_tipo, desconto_inicio, desconto_fim, desconto_vitalicio, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
        ON DUPLICATE KEY UPDATE
            data_entrada     = VALUES(data_entrada),
            desconto         = VALUES(desconto),
            desconto_tipo    = VALUES(desconto_tipo),
            desconto_inicio  = VALUES(desconto_inicio),
            desconto_fim     = VALUES(desconto_fim),
            desconto_vitalicio = VALUES(desconto_vitalicio),
            status           = 'ativo'
    ");
    $stmt->execute([$turmaId, $alunoId, $dataInicio, $desconto, $descontoTipo, $descontoInicio, $descontoFim, $descontoVitalicio]);

    if (!empty($mensalidadesParaGerar)) {
        $stmtMens = $pdo->prepare("
            INSERT IGNORE INTO mensalidades (aluno_id, turma_id, referencia, valor, matricula_valor, vencimento, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pendente')
        ");
        foreach ($mensalidadesParaGerar as $m) {
            $stmtMens->execute([
                $alunoId, $turmaId,
                $m['referencia'], $m['valor'],
                $m['matricula_valor'] ?? null,
                $m['vencimento'],
            ]);
        }
    }

    if ($matriculaValor > 0) {
        $pdo->prepare("UPDATE alunos SET matricula_cobrada = 1 WHERE id = ?")->execute([$alunoId]);
    }

    $pdo->commit();

    $turmaStmt = $pdo->prepare("
        SELECT t.id, t.nome, t.valor_mensalidade, t.promo_valor, t.promo_meses, q.nome AS quadra_nome
        FROM turmas t LEFT JOIN quadras q ON q.id = t.quadra_id
        WHERE t.id = ?
    ");
    $turmaStmt->execute([$turmaId]);
    $turma = $turmaStmt->fetch();
    $turma['data_entrada'] = $dataInicio;

    $valorEfetivo = (float) $turma['valor_mensalidade'];
    if ($desconto !== null && $desconto > 0) {
        $valorEfetivo = max(0, (float) $turma['valor_mensalidade'] - $desconto);
    } elseif ($turma['promo_valor'] !== null && $turma['promo_meses'] !== null
              && (float) $turma['promo_valor'] < (float) $turma['valor_mensalidade']) {
        $fimPromoChk = date('Y-m-d', strtotime($dataInicio . ' +' . $turma['promo_meses'] . ' months'));
        if ($fimPromoChk >= $dataInicio) {
            $valorEfetivo = (float) $turma['promo_valor'];
        }
    }
    $turma['valor_efetivo'] = $valorEfetivo;

    echo json_encode(['success' => true, 'aluno' => $aluno, 'aluno_turma' => $turma]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar aluno: ' . $e->getMessage()]);
}
