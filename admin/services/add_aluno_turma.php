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

$aluno = $pdo->prepare("SELECT id, nome, email, celular FROM alunos WHERE id = ? AND status = 'ativo'");
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
    $entrada   = new DateTime($dataInicio);
    $entryDay  = (int) $entrada->format('j');
    $valorBase = (float) $turmaData['valor_mensalidade'];

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
            // Mês cheio: mês atual conta como mês 1 da promoção
            $refDate = new DateTime($entrada->format('Y-m') . '-01');
            for ($i = 0; $i < $promoMeses; $i++) {
                $vencDate = clone $refDate;
                $vencDate->modify('+1 month');
                $mensalidadesParaGerar[] = [
                    'referencia' => $refDate->format('Y-m'),
                    'valor'      => $promoValor,
                    'vencimento' => $vencDate->format('Y-m') . '-05',
                ];
                $refDate->modify('+1 month');
            }

            // Desconto vigente: do mês de entrada até o último mês promocional
            $fimPromo = new DateTime($entrada->format('Y-m') . '-01');
            $fimPromo->modify('+' . $promoMeses . ' months');
            $fimPromo->modify('-1 day');

            $desconto       = round($valorBase - $promoValor, 2);
            $descontoInicio = $dataInicio;
            $descontoFim    = $fimPromo->format('Y-m-d');

        } else {
            // Proporcional: cobra os dias usados no mês de entrada (sobre o valor promocional)
            // depois $promoMeses meses cheios de promoção
            $daysInMonth  = (int) $entrada->format('t');
            $daysUsed     = $daysInMonth - $entryDay + 1;
            $proportional = round(($daysUsed / $daysInMonth) * $promoValor, 2);

            $nextMonth = new DateTime($entrada->format('Y-m') . '-01');
            $nextMonth->modify('+1 month');

            // Fatura proporcional: referência = mês de entrada, vencimento = dia 5 do mês seguinte
            $mensalidadesParaGerar[] = [
                'referencia' => $entrada->format('Y-m'),
                'valor'      => $proportional,
                'vencimento' => $nextMonth->format('Y-m') . '-05',
            ];

            // Meses promocionais cheios a partir do mês seguinte ao de entrada
            $refDate = clone $nextMonth;
            for ($i = 0; $i < $promoMeses; $i++) {
                $vencDate = clone $refDate;
                $vencDate->modify('+1 month');
                $mensalidadesParaGerar[] = [
                    'referencia' => $refDate->format('Y-m'),
                    'valor'      => $promoValor,
                    'vencimento' => $vencDate->format('Y-m') . '-05',
                ];
                $refDate->modify('+1 month');
            }

            // Desconto vigente: do mês seguinte até o último mês promocional
            $fimPromo = clone $nextMonth;
            $fimPromo->modify('+' . $promoMeses . ' months');
            $fimPromo->modify('-1 day');

            $desconto       = round($valorBase - $promoValor, 2);
            $descontoInicio = $dataInicio;
            $descontoFim    = $fimPromo->format('Y-m-d');
        }

    } else {
        // Sem promoção: gera apenas a primeira fatura (proporcional ou mês cheio)
        if ($isExcecao) {
            $vencDate = new DateTime($entrada->format('Y-m') . '-01');
            $vencDate->modify('+1 month');
            $mensalidadesParaGerar[] = [
                'referencia' => $entrada->format('Y-m'),
                'valor'      => $valorBase,
                'vencimento' => $vencDate->format('Y-m') . '-05',
            ];
        } else {
            $daysInMonth  = (int) $entrada->format('t');
            $daysUsed     = $daysInMonth - $entryDay + 1;
            $proportional = round(($daysUsed / $daysInMonth) * $valorBase, 2);

            $nextMonth = new DateTime($entrada->format('Y-m') . '-01');
            $nextMonth->modify('+1 month');

            $mensalidadesParaGerar[] = [
                'referencia' => $entrada->format('Y-m'),
                'valor'      => $proportional,
                'vencimento' => $nextMonth->format('Y-m') . '-05',
            ];
        }
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO turma_alunos
            (turma_id, aluno_id, data_entrada, desconto, desconto_tipo, desconto_inicio, desconto_fim, desconto_vitalicio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$turmaId, $alunoId, $dataInicio, $desconto, $descontoTipo, $descontoInicio, $descontoFim, $descontoVitalicio]);

    if (!empty($mensalidadesParaGerar)) {
        $stmtMens = $pdo->prepare("
            INSERT INTO mensalidades (aluno_id, turma_id, referencia, valor, vencimento, status)
            VALUES (?, ?, ?, ?, ?, 'pendente')
        ");
        foreach ($mensalidadesParaGerar as $m) {
            $stmtMens->execute([$alunoId, $turmaId, $m['referencia'], $m['valor'], $m['vencimento']]);
        }
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
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'O aluno já faz parte dessa turma.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar aluno.']);
    }
}
