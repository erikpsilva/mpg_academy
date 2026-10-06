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
 * Reajusta o que depende do preço da turma quando ele (ou o valor promocional) muda.
 *
 * O desconto do aluno é gravado em REAIS na matrícula (valor da turma − valor promocional) e
 * fica congelado. Sem este ajuste, mexer no preço da turma mudava calado quanto cada aluno em
 * promoção paga — foi assim que baixar a mensalidade de 119,90 para 109,90 transformou a
 * promoção de 99,99 em 89,99 para a turma infantil inteira.
 *
 * Mexe só em quem está no desconto PADRÃO da promoção e em fatura ainda não paga que carrega
 * exatamente o preço antigo. Desconto negociado caso a caso e fatura com matrícula ou valor
 * proporcional não são tocados: ali o valor não é consequência do preço da turma.
 *
 * @param array|null $antes ['valor_mensalidade' => ..., 'promo_valor' => ...] antes do update.
 * @return array{descontos:int, faturas:int} quantas linhas foram realinhadas.
 */
function ajustarDescontosDaPromo(PDO $pdo, int $turmaId, ?array $antes, ?float $valorNovo, ?float $promoNovo): array
{
    $nada = ['descontos' => 0, 'faturas' => 0];

    $valorAntigo = isset($antes['valor_mensalidade']) ? (float) $antes['valor_mensalidade'] : null;
    $promoAntigo = isset($antes['promo_valor'])       ? (float) $antes['promo_valor']       : null;

    if ($valorAntigo === null || $valorNovo === null) return $nada;

    $resultado = $nada;

    // ── Alunos na promoção padrão ────────────────────────────────────────────
    if ($promoAntigo !== null && $promoNovo !== null) {
        $descontoAntigo = round($valorAntigo - $promoAntigo, 2);
        $descontoNovo   = round($valorNovo - $promoNovo, 2);

        if ($descontoNovo >= 0 && abs($descontoAntigo - $descontoNovo) >= 0.01) {
            $st = $pdo->prepare("
                UPDATE turma_alunos SET desconto = ?
                WHERE turma_id = ? AND status = 'ativo' AND ROUND(desconto, 2) = ?
            ");
            $st->execute([$descontoNovo, $turmaId, $descontoAntigo]);
            $resultado['descontos'] = $st->rowCount();
        }
    }

    // ── Faturas em aberto que ainda carregam o preço antigo ──────────────────
    //
    // Vale pros dois preços: quem paga cheio e quem está na promoção. Fatura com matrícula ou
    // proporcional fica fora — ali o valor é uma soma, não o preço da turma.
    $trocas = [];
    if (abs($valorAntigo - $valorNovo) >= 0.01) {
        $trocas[] = [$valorAntigo, $valorNovo];
    }
    if ($promoAntigo !== null && $promoNovo !== null && abs($promoAntigo - $promoNovo) >= 0.01) {
        $trocas[] = [$promoAntigo, $promoNovo];
    }

    foreach ($trocas as [$de, $para]) {
        $st = $pdo->prepare("
            UPDATE mensalidades SET valor = ?
            WHERE turma_id = ? AND status <> 'pago' AND tipo = 'mensalidade'
              AND matricula_valor IS NULL AND proporcional_valor IS NULL
              AND ROUND(valor, 2) = ?
        ");
        $st->execute([$para, $turmaId, $de]);
        $resultado['faturas'] += $st->rowCount();
    }

    return $resultado;
}

/**
 * Cria a mensalidade de $referencia (formato 'Y-m') pra um aluno numa turma, se ainda não
 * existir e se a matrícula dele nessa turma seguir ativa. Aplica o desconto pessoal/promo
 * vigente na data de referência (não em "hoje" — importante pra descontos agendados pro futuro).
 *
 * @return bool true se a fatura foi criada agora, false se já existia ou não se aplica.
 */
function gerarMensalidadeRecorrente(PDO $pdo, int $alunoId, int $turmaId, string $referencia): bool
{
    // Aluno inativo não gera fatura nova, ponto. Hoje desativar o aluno também pausa a
    // matrícula na turma, então o filtro de `turma_alunos` já bastaria — mas aluno inativo
    // com matrícula ativa (correção manual no banco, importação, uma tela futura) voltaria a
    // ser cobrado sem ninguém perceber. A regra fica aqui, junto de quem cria a cobrança.
    $stTa = $pdo->prepare("
        SELECT ta.data_entrada, ta.desconto, ta.desconto_tipo, ta.desconto_inicio, ta.desconto_fim, ta.desconto_vitalicio,
               t.valor_mensalidade, t.status AS turma_status
        FROM turma_alunos ta
        JOIN turmas t ON t.id = ta.turma_id
        JOIN alunos a ON a.id = ta.aluno_id
        WHERE ta.aluno_id = ? AND ta.turma_id = ? AND ta.status = 'ativo' AND a.status = 'ativo'
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

    $valorBase = (float) $ta['valor_mensalidade'];

    // O desconto vale pro mês se a janela dele ENCOSTA em qualquer dia do mês — não só no
    // dia 1º. Comparar com o dia 1º cobrava cheio justamente quem entrou no meio do mês: o
    // aluno que começa dia 5 com promoção a partir do dia 5 recebia a primeira fatura sem
    // desconto nenhum, e só o segundo mês saía promocional.
    $primeiroDia = $referencia . '-01';
    $ultimoDia   = date('Y-m-t', strtotime($primeiroDia));

    $descontoAtivo = $ta['desconto'] !== null && $ta['desconto'] > 0 && (
        $ta['desconto_vitalicio'] ||
        ($ta['desconto_inicio'] === null && $ta['desconto_fim'] === null) ||
        (($ta['desconto_inicio'] === null || $ta['desconto_inicio'] <= $ultimoDia) &&
         ($ta['desconto_fim']    === null || $ta['desconto_fim']    >= $primeiroDia))
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
        JOIN alunos a ON a.id = ta.aluno_id
        WHERE ta.status = 'ativo' AND t.status = 'ativa' AND t.valor_mensalidade IS NOT NULL
          AND a.status = 'ativo'
    ")->fetchAll(PDO::FETCH_ASSOC);

    $geradas = 0;
    foreach ($ativos as $ta) {
        if (gerarMensalidadeRecorrente($pdo, (int) $ta['aluno_id'], (int) $ta['turma_id'], $referencia)) {
            $geradas++;
        }
    }

    return ['geradas' => $geradas, 'referencia' => $referencia];
}
