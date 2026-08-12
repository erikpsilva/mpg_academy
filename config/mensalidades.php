<?php

/**
 * Geração de mensalidades recorrentes — usada tanto pelo disparo imediato (assim que uma
 * mensalidade é paga, já gera a do mês seguinte) quanto pelo fallback diário que garante que
 * todo aluno ativo tenha fatura pro mês em que estamos, mesmo que ele não tenha pago a anterior.
 *
 * Nunca gera adiantado: só cria fatura pro mês em que o sistema já está (ou pro mês seguinte ao
 * de uma fatura que acabou de ser paga) — nunca dois meses à frente do que o aluno já pagou.
 */

/**
 * Cria a mensalidade de $referencia (formato 'Y-m') pra um aluno numa turma, se ainda não
 * existir e se a matrícula dele nessa turma seguir ativa. Aplica o desconto pessoal/promo
 * vigente na data de referência (não em "hoje" — importante pra descontos agendados pro futuro).
 *
 * @return bool true se a fatura foi criada agora, false se já existia ou não se aplica.
 */
function gerarMensalidadeRecorrente(PDO $pdo, int $alunoId, int $turmaId, string $referencia): bool
{
    $stTa = $pdo->prepare("
        SELECT ta.data_entrada, ta.desconto, ta.desconto_tipo, ta.desconto_inicio, ta.desconto_fim, ta.desconto_vitalicio,
               t.valor_mensalidade, t.status AS turma_status
        FROM turma_alunos ta
        JOIN turmas t ON t.id = ta.turma_id
        WHERE ta.aluno_id = ? AND ta.turma_id = ? AND ta.status = 'ativo'
    ");
    $stTa->execute([$alunoId, $turmaId]);
    $ta = $stTa->fetch();

    if (!$ta || $ta['turma_status'] !== 'ativa' || $ta['valor_mensalidade'] === null) {
        return false;
    }

    // Nunca gera fatura pra um mês anterior ao mês de entrada do aluno na turma — evita cobrar
    // meses em que ele nem estava matriculado ainda (ex.: entrada agendada pro mês seguinte,
    // cadastrada com antecedência enquanto o fallback diário ainda roda no mês atual).
    if ($referencia < substr($ta['data_entrada'], 0, 7)) {
        return false;
    }

    $stCheck = $pdo->prepare("SELECT id FROM mensalidades WHERE aluno_id = ? AND referencia = ?");
    $stCheck->execute([$alunoId, $referencia]);
    if ($stCheck->fetchColumn()) {
        return false;
    }

    $valorBase  = (float) $ta['valor_mensalidade'];
    $dataRef    = $referencia . '-01';
    $descontoAtivo = $ta['desconto'] !== null && $ta['desconto'] > 0 && (
        $ta['desconto_vitalicio'] ||
        ($ta['desconto_inicio'] === null && $ta['desconto_fim'] === null) ||
        ($ta['desconto_inicio'] <= $dataRef && $ta['desconto_fim'] >= $dataRef)
    );

    $valor = $descontoAtivo
        ? ($ta['desconto_tipo'] === 'percentual'
            ? round($valorBase * (1 - $ta['desconto'] / 100), 2)
            : max(0, round($valorBase - (float) $ta['desconto'], 2)))
        : $valorBase;

    $vencimento = $referencia . '-10';

    try {
        $pdo->prepare("
            INSERT INTO mensalidades (aluno_id, turma_id, referencia, valor, vencimento, status)
            VALUES (?, ?, ?, ?, ?, 'pendente')
        ")->execute([$alunoId, $turmaId, $referencia, $valor, $vencimento]);
        return true;
    } catch (PDOException $e) {
        return false; // corrida entre dois disparos simultâneos — a unique key (aluno_id, referencia) protege
    }
}

/**
 * Garante que todo aluno com matrícula ativa tenha fatura pro mês ATUAL (nunca pro mês
 * seguinte) — é o fallback que cobre quem nunca pagou a fatura anterior e por isso não passou
 * pelo disparo imediato de gerarMensalidadeRecorrente() após pagamento.
 *
 * @return array ['geradas' => int, 'referencia' => string]
 */
function gerarMensalidadesMesAtual(PDO $pdo): array
{
    $referencia = (new DateTime())->format('Y-m');

    $ativos = $pdo->query("
        SELECT ta.aluno_id, ta.turma_id
        FROM turma_alunos ta
        JOIN turmas t ON t.id = ta.turma_id
        WHERE ta.status = 'ativo' AND t.status = 'ativa' AND t.valor_mensalidade IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    $geradas = 0;
    foreach ($ativos as $ta) {
        if (gerarMensalidadeRecorrente($pdo, (int) $ta['aluno_id'], (int) $ta['turma_id'], $referencia)) {
            $geradas++;
        }
    }

    return ['geradas' => $geradas, 'referencia' => $referencia];
}
