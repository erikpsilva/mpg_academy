<?php

/**
 * Regras de vaga da aula experimental.
 *
 * A vaga de teste é POR DATA, não por turma.
 *
 * O limite da turma (`max_alunos`) diz quantas pessoas cabem na quadra num treino — e um
 * treino acontece num dia. Uma turma de 20 lugares com 10 alunos matriculados tem 10 vagas
 * de teste no dia 21/08 e outras 10 no dia 28/08: quem testa numa data não ocupa o lugar de
 * quem testa na outra.
 *
 * Antes a contagem era por turma inteira, somando todas as datas. Bastavam 10 agendamentos
 * espalhados por semanas diferentes pra turma aparecer lotada e os próximos caírem na fila,
 * mesmo com a quadra vazia em todas elas.
 */

/**
 * Quantas vagas de teste sobram na turma NAQUELA data.
 *
 * Devolve null quando a turma não tem limite (`max_alunos` nulo) — nesse caso não há teto e
 * quem chama deve tratar como "sempre cabe".
 *
 * `$ignorarAulaId` existe pro reagendamento: ao mover alguém para outra data, a própria
 * inscrição não pode contar contra ela mesma.
 */
function aulaTesteVagasNaData(PDO $pdo, int $turmaId, ?string $dataAgendada, ?int $maxAlunos, ?int $ignorarAulaId = null): ?int
{
    if ($maxAlunos === null) return null;

    $st = $pdo->prepare("SELECT COUNT(*) FROM turma_alunos WHERE turma_id = ? AND status = 'ativo'");
    $st->execute([$turmaId]);
    $ativos = (int) $st->fetchColumn();

    // Sem data definida não dá pra reservar lugar num dia específico: conta só os matriculados.
    if ($dataAgendada === null || $dataAgendada === '') {
        return max(0, $maxAlunos - $ativos);
    }

    $sql = "SELECT COUNT(*) FROM aulas_experimentais
            WHERE turma_id = ? AND status = 'agendada' AND data_agendada = ?";
    $par = [$turmaId, $dataAgendada];

    if ($ignorarAulaId !== null) {
        $sql .= " AND id <> ?";
        $par[] = $ignorarAulaId;
    }

    $st = $pdo->prepare($sql);
    $st->execute($par);
    $agendadasNaData = (int) $st->fetchColumn();

    return max(0, $maxAlunos - $ativos - $agendadasNaData);
}

/**
 * Cabe mais alguém nessa turma nessa data? Turma sem limite sempre cabe.
 */
function aulaTesteCabeNaData(PDO $pdo, int $turmaId, ?string $dataAgendada, ?int $maxAlunos, ?int $ignorarAulaId = null): bool
{
    $vagas = aulaTesteVagasNaData($pdo, $turmaId, $dataAgendada, $maxAlunos, $ignorarAulaId);
    return $vagas === null || $vagas > 0;
}
