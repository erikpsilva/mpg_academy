<?php

// Recalcula o valor de faturas pendentes/atrasadas de uma turma+referência considerando
// o desconto pessoal ATUAL do aluno (turma_alunos.desconto) — chamado por
// save_desconto_aluno.php sempre que um desconto é criado/alterado/removido, pra que
// faturas já geradas reflitam o novo valor sem precisar esperar o próximo ciclo.
//
// Aula cancelada (feriado etc.) NÃO afeta mais a cobrança do aluno — ele paga por mês,
// tenha aula ou não (decisão do usuário, 2026-07-10). `desconto_aula_valor` só existe
// hoje em faturas antigas (geradas quando essa função ainda aplicava esse desconto) e é
// preservado como está, nunca mais recalculado — só entra na conta como um valor
// congelado, se já existir.

function _daGetDiasSemanaTurma(PDO $pdo, int $turmaId): array {
    $st = $pdo->prepare("
        SELECT DISTINCT qh.dia_semana
        FROM turma_horarios th
        JOIN quadra_horarios qh ON qh.id = th.horario_id
        WHERE th.turma_id = ?
    ");
    $st->execute([$turmaId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// Conta quantos dias de aula (por dia da semana da turma) caem num intervalo de datas —
// não exclui feriados/cancelamentos, o aluno paga por essas aulas do mesmo jeito.
function _daContarDiasSemanaNoIntervalo(array $diasSemana, string $dataInicio, string $dataFim): int {
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

// Recalcula o valor do proporcional de entrada (aulas restantes da entrada até o fim do
// ciclo, sobre o total de aulas do ciclo) — mesma lógica de calcProporcional() em
// add_aluno_turma.php. Usado quando o desconto pessoal do aluno muda depois que a
// fatura de entrada já foi gerada.
function _daRecalcularProporcionalEntrada(array $diasSemana, string $dataEntrada, float $valorCheio): float {
    $entrada       = new DateTime($dataEntrada);
    $fechamentoDia = min(30, (int) $entrada->format('t'));
    $fimCiclo      = $entrada->format('Y-m') . '-' . str_pad($fechamentoDia, 2, '0', STR_PAD_LEFT);
    $iniCiclo      = $entrada->format('Y-m-01');

    $totalAulas     = _daContarDiasSemanaNoIntervalo($diasSemana, $iniCiclo, $fimCiclo);
    $aulasPendentes = _daContarDiasSemanaNoIntervalo($diasSemana, $dataEntrada, $fimCiclo);

    if ($totalAulas > 0) {
        return round(($aulasPendentes / $totalAulas) * $valorCheio, 2);
    }
    // Fallback: proporcional por dias corridos, quando a turma não tem horários cadastrados
    $daysInMonth = (int) $entrada->format('t');
    $entryDay    = (int) $entrada->format('j');
    $daysUsed    = $daysInMonth - $entryDay + 1;
    return round(($daysUsed / $daysInMonth) * $valorCheio, 2);
}

// Valor mensal do aluno já líquido de desconto pessoal/promo (sem matrícula/proporcional/desconto de aula).
function _daValorCheioAluno(float $valorBase, ?float $desconto, string $descontoTipo, ?string $descontoInicio, ?string $descontoFim, $descontoVitalicio, string $hojeStr): float {
    $descontoAtivo = $desconto !== null && $desconto > 0 && (
        $descontoVitalicio ||
        ($descontoInicio === null && $descontoFim === null) ||
        ($descontoInicio <= $hojeStr && $descontoFim >= $hojeStr)
    );
    if (!$descontoAtivo) return $valorBase;

    return $descontoTipo === 'percentual'
        ? round($valorBase * (1 - $desconto / 100), 2)
        : max(0, round($valorBase - $desconto, 2));
}

// Recalcula as faturas pendentes/atrasadas de uma turma+referência com o desconto pessoal
// atual — tanto faturas "cheias" normais quanto faturas de entrada (proporcional puro,
// quando o aluno entrou nesse mesmo mês).
function recalcularDescontoAulaTurma(PDO $pdo, int $turmaId, string $referencia): void {
    $turmaSt = $pdo->prepare("SELECT valor_mensalidade FROM turmas WHERE id = ?");
    $turmaSt->execute([$turmaId]);
    $turma = $turmaSt->fetch();
    if (!$turma || $turma['valor_mensalidade'] === null) return;

    $diasSemana = _daGetDiasSemanaTurma($pdo, $turmaId);
    $valorBase  = (float) $turma['valor_mensalidade'];
    $hojeStr    = date('Y-m-d');

    $stMens = $pdo->prepare("
        SELECT m.id, m.matricula_valor, m.proporcional_valor, m.desconto_aula_valor,
               ta.data_entrada,
               ta.desconto, ta.desconto_tipo, ta.desconto_inicio, ta.desconto_fim, ta.desconto_vitalicio
        FROM mensalidades m
        JOIN turma_alunos ta ON ta.turma_id = m.turma_id AND ta.aluno_id = m.aluno_id
        WHERE m.turma_id = ? AND m.referencia = ? AND m.tipo = 'mensalidade'
          AND m.status IN ('pendente', 'atrasado')
    ");
    $stMens->execute([$turmaId, $referencia]);
    $linhas = $stMens->fetchAll();

    $updCheia   = $pdo->prepare("UPDATE mensalidades SET valor = ? WHERE id = ?");
    $updEntrada = $pdo->prepare("UPDATE mensalidades SET valor = ?, proporcional_valor = ? WHERE id = ?");

    foreach ($linhas as $l) {
        $valorCheio = _daValorCheioAluno(
            $valorBase, $l['desconto'] !== null ? (float) $l['desconto'] : null, $l['desconto_tipo'],
            $l['desconto_inicio'], $l['desconto_fim'], $l['desconto_vitalicio'], $hojeStr
        );

        $entradaNesseMes = $l['proporcional_valor'] !== null && substr($l['data_entrada'], 0, 7) === $referencia;

        if ($entradaNesseMes) {
            // Fatura de entrada: recalcula só o proporcional com o desconto pessoal atual.
            $novoProporcional = _daRecalcularProporcionalEntrada($diasSemana, $l['data_entrada'], $valorCheio);
            $novoValor = max(0, round((float) ($l['matricula_valor'] ?? 0) + $novoProporcional, 2));
            $updEntrada->execute([$novoValor, $novoProporcional, $l['id']]);
            continue;
        }

        // Fatura cheia normal (ou a parte "mês cheio" de uma fatura combinada legada).
        // desconto_aula_valor não é mais recalculado — só entra como valor já congelado,
        // se a fatura já tinha um (gerado antes de 2026-07-10).
        $descontoAulaExistente = (float) ($l['desconto_aula_valor'] ?? 0);
        $novoValor = round((float) ($l['matricula_valor'] ?? 0) + (float) ($l['proporcional_valor'] ?? 0) + $valorCheio - $descontoAulaExistente, 2);
        $novoValor = max(0, $novoValor);

        $updCheia->execute([$novoValor, $l['id']]);
    }
}
