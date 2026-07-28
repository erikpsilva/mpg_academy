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

// Conta aulas programadas (por dia da semana da turma) no intervalo — não exclui
// feriados/cancelamentos, o aluno paga por mês tenha aula ou não.
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

    $count   = 0;
    $current = new DateTime($dataInicio);
    $fim     = new DateTime($dataFim);
    while ($current <= $fim) {
        if (in_array((int) $current->format('w'), $diasSemana, true)) $count++;
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

$aluno = $pdo->prepare("SELECT id, nome, email, celular, matricula_cobrada, isento_matricula FROM alunos WHERE id = ? AND status = 'ativo'");
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
    $entrada     = new DateTime($dataInicio);
    $valorBase   = (float) $turmaData['valor_mensalidade'];

    // Se a entrada cai na 1ª semana do mês (até 3 dias antes do dia 10 — dia 1 a 7), o
    // vencimento do dia 10 normal do ciclo já dá prazo suficiente: usa ele direto, sem
    // fatura "de afobação". Só usa o prazo curto de 3 dias (contados de HOJE, não da
    // entrada, que pode ser uma data futura) quando a entrada é tarde demais no mês pro
    // dia 10 ainda servir de vencimento (dia 8+, ou dia 10 do mês de entrada já passado).
    $diaEntrada = (int) $entrada->format('j');
    $vencNormalCiclo = new DateTime($entrada->format('Y-m') . '-10');
    $hoje = new DateTime();
    if ($diaEntrada <= 7 && $vencNormalCiclo >= $hoje) {
        $vencInicial = $vencNormalCiclo->format('Y-m-d');
    } else {
        $vencInicial = (clone $hoje)->modify('+3 days')->format('Y-m-d');
    }

    $temPromo = $turmaData['promo_valor'] !== null
             && $turmaData['promo_meses'] !== null
             && (float) $turmaData['promo_valor'] < $valorBase;

    // Ciclo continua fechando dia 30 e a geração mensal recorrente continua vencendo dia 10
    // (gerar_mensalidades.php / auth_check.php) — mas todo aluno novo já paga, na hora, o
    // proporcional das aulas que vai fazer no PRÓPRIO mês de entrada (não mais combinado
    // com o mês seguinte). Vencimento curto (3 dias) pra essa fatura de entrada.
    if ($temPromo) {
        $promoValor = (float) $turmaData['promo_valor'];
        $promoMeses = (int) $turmaData['promo_meses'];

        $proportional = calcProporcional($pdo, $turmaId, $entrada, $dataInicio, $promoValor);

        $mensalidadesParaGerar[] = [
            'referencia'         => $entrada->format('Y-m'),
            'valor'              => round($proportional, 2),
            'proporcional_valor' => $proportional,
            'vencimento'         => $vencInicial,
        ];

        // O mês de entrada (cobrado acima, proporcional ao valor promo) é um bônus à parte —
        // não conta como um dos meses da promoção. Os promo_meses de preço promocional cheio
        // começam a valer só a partir do mês SEGUINTE, aplicados pela geração mensal recorrente.
        $nextMonth = new DateTime($entrada->format('Y-m') . '-01');
        $nextMonth->modify('+1 month');
        $fimPromo = clone $nextMonth;
        $fimPromo->modify('+' . ($promoMeses - 1) . ' months');

        $desconto       = round($valorBase - $promoValor, 2);
        $descontoInicio = $dataInicio;
        $descontoFim    = $fimPromo->format('Y-m-d');

    } else {
        $proportional = calcProporcional($pdo, $turmaId, $entrada, $dataInicio, $valorBase);

        $mensalidadesParaGerar[] = [
            'referencia'         => $entrada->format('Y-m'),
            'valor'              => round($proportional, 2),
            'proporcional_valor' => $proportional,
            'vencimento'         => $vencInicial,
        ];
    }
}

// Verifica se deve cobrar matrícula (uma única vez por aluno, exceto se isento ou se a cobrança estiver desativada globalmente)
$matriculaValor = 0.0;
if (!$aluno['matricula_cobrada'] && !$aluno['isento_matricula']) {
    $cfgAtiva = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'matricula_ativa'");
    $cfgAtiva->execute();
    $rowAtiva       = $cfgAtiva->fetch();
    $matriculaAtiva = $rowAtiva === false || $rowAtiva['valor'] !== '0'; // default ativa se não configurado

    if ($matriculaAtiva) {
        $cfgSt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'valor_matricula'");
        $cfgSt->execute();
        $cfgRow = $cfgSt->fetch();
        $matriculaValor = $cfgRow ? (float) $cfgRow['valor'] : 0.0;
    }
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
        // A chave única de mensalidades é (aluno_id, referencia) — sem turma_id. Se o aluno
        // trocou de turma no meio do ciclo, já existe uma fatura pendente daquele mês presa na
        // turma antiga; o INSERT IGNORE sozinho seria silenciosamente bloqueado por ela, deixando
        // a fatura com a turma/valor errados. Por isso atualizamos a fatura existente pra turma
        // nova (exceto se ela já estiver paga — fatura paga nunca é alterada).
        $stmtMens = $pdo->prepare("
            INSERT INTO mensalidades (aluno_id, turma_id, referencia, valor, matricula_valor, proporcional_valor, vencimento, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')
            ON DUPLICATE KEY UPDATE
                turma_id           = IF(status = 'pago', turma_id, VALUES(turma_id)),
                valor              = IF(status = 'pago', valor, VALUES(valor)),
                matricula_valor    = IF(status = 'pago', matricula_valor, VALUES(matricula_valor)),
                proporcional_valor = IF(status = 'pago', proporcional_valor, VALUES(proporcional_valor)),
                vencimento         = IF(status = 'pago', vencimento, VALUES(vencimento))
        ");
        foreach ($mensalidadesParaGerar as $m) {
            $stmtMens->execute([
                $alunoId, $turmaId,
                $m['referencia'], $m['valor'],
                $m['matricula_valor'] ?? null,
                $m['proporcional_valor'] ?? null,
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
