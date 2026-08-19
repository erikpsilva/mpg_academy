<?php

/**
 * Configurações e helpers do Bate Bola — encontro avulso de vôlei (domingo, 10h-13h,
 * Quadra Orion), separado do sistema de mensalidades/turmas da escola.
 */

const BATEBOLA_MAX_VAGAS  = 24;
const BATEBOLA_TIME_CORES = ['Azul', 'Vermelho', 'Amarelo', 'Verde'];
const BATEBOLA_LOCAL_NOME = 'Quadra Orion';
const BATEBOLA_LOCAL_ENDERECO = 'Rua André Domingues, 40 – Jardim Paraíso, São Paulo/SP – CEP 02417-080';

/** Se um domingo específico foi marcado pelo admin como "sem Bate Bola". */
function batebolaDataBloqueada(PDO $pdo, string $dataEvento): bool
{
    $st = $pdo->prepare("SELECT 1 FROM batebola_dias_bloqueados WHERE data_evento = ?");
    $st->execute([$dataEvento]);
    return (bool) $st->fetchColumn();
}

/**
 * Data do próximo encontro (sempre um domingo). Se hoje já é domingo, considera o
 * encontro de hoje — senão, o próximo domingo à frente. Pula domingos marcados pelo
 * admin como "sem Bate Bola" (ver batebolahistorico no admin).
 */
function batebolaProximoDomingo(PDO $pdo): string
{
    $data = new DateTime('today');
    if ((int) $data->format('w') !== 0) {
        $data->modify('next sunday');
    }

    for ($i = 0; $i < 52; $i++) {
        $dataStr = $data->format('Y-m-d');
        if (!batebolaDataBloqueada($pdo, $dataStr)) {
            return $dataStr;
        }
        $data->modify('+7 days');
    }

    return $data->format('Y-m-d');
}

/**
 * Janela em que a lista do Bate Bola fica aberta pra pagamento/inscrição: abre toda
 * segunda-feira às 06h00 e fecha sexta-feira às 23h59:59 (sábado e domingo, e a
 * madrugada de segunda antes das 06h, ficam fechados).
 *
 * @return DateTime[] [$abre, $fecha] da semana da data de referência.
 */
function batebolaJanelaInscricao(?DateTime $referencia = null): array
{
    $ref = $referencia ? clone $referencia : new DateTime('now');
    $diaSemana = (int) $ref->format('N'); // 1 = segunda ... 7 = domingo

    $abre = (clone $ref)->modify('-' . ($diaSemana - 1) . ' days')->setTime(6, 0, 0);
    $fecha = (clone $abre)->modify('+4 days')->setTime(23, 59, 59);

    return [$abre, $fecha];
}

/** Se a lista do Bate Bola está aberta pra pagamento/inscrição agora. */
function batebolaInscricoesAbertas(?DateTime $referencia = null): bool
{
    $ref = $referencia ? clone $referencia : new DateTime('now');
    [$abre, $fecha] = batebolaJanelaInscricao($ref);
    return $ref >= $abre && $ref <= $fecha;
}

/**
 * Estado da janela de inscrição agora, pronto pra exibir na tela.
 *
 * A regra em si (abre segunda 06h, fecha sexta 23h59) vive em batebolaJanelaInscricao().
 * Aqui traduzimos pra "está aberta?" + "quando muda", pra que o aviso na home nunca
 * fique desencontrado da regra real — as duas telas usam este mesmo cálculo.
 *
 * @return array{aberta:bool, abre:DateTime, fecha:DateTime, proxima_mudanca:DateTime}
 */
function batebolaEstadoJanela(?DateTime $referencia = null): array
{
    $ref = $referencia ? clone $referencia : new DateTime('now');
    [$abre, $fecha] = batebolaJanelaInscricao($ref);

    $aberta = ($ref >= $abre && $ref <= $fecha);

    if ($aberta) {
        // Aberta: o que interessa é quando fecha.
        $proxima = $fecha;
    } elseif ($ref < $abre) {
        // Madrugada de segunda, antes das 06h — abre hoje mesmo.
        $proxima = $abre;
    } else {
        // Sábado/domingo: já fechou nesta semana, reabre na segunda que vem.
        $proxima = (clone $abre)->modify('+7 days');
    }

    return ['aberta' => $aberta, 'abre' => $abre, 'fecha' => $fecha, 'proxima_mudanca' => $proxima];
}

/** Quantas vagas já estão confirmadas (pagas) pra uma data de encontro. */
function batebolaVagasConfirmadas(PDO $pdo, string $dataEvento): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM batebola_inscricoes WHERE data_evento = ? AND status = 'pago'");
    $st->execute([$dataEvento]);
    return (int) $st->fetchColumn();
}

/**
 * Confirma o pagamento de uma inscrição do Bate Bola. Chamado a partir do webhook do
 * Mercado Pago (via metadata.batebola_inscricao_id), no mesmo padrão de
 * mpMarcarMensalidadePaga() em config/mercadopago.php.
 *
 * A vaga (limite de BATEBOLA_MAX_VAGAS) é controlada na hora de GERAR o pix — quem já
 * pagou é sempre confirmado aqui, mesmo numa eventual corrida de última hora.
 */
function batebolaConfirmarInscricao(PDO $pdo, int $inscricaoId, string $mpPaymentId, ?array $payment = null): bool
{
    $st = $pdo->prepare("SELECT id, status FROM batebola_inscricoes WHERE id = ?");
    $st->execute([$inscricaoId]);
    $insc = $st->fetch();
    if (!$insc || $insc['status'] === 'pago') return false;

    $pdo->prepare("
        UPDATE batebola_inscricoes
        SET status = 'pago', mp_payment_id = ?, pago_em = NOW()
        WHERE id = ? AND status != 'pago'
    ")->execute([$mpPaymentId, $inscricaoId]);

    return true;
}

/**
 * Sorteia times de 6 jogadores (times de vôlei), tentando equilibrar entre os times,
 * nessa ordem de prioridade: 1) nível de jogo (1-5 estrelas), 2) altura, 3) mistura de
 * homens/mulheres. Retorna até 4 times (Azul, Vermelho, Amarelo, Verde).
 *
 * Estratégia: ordena por nível desc + altura desc e distribui em draft serpentina
 * (zig-zag entre os times), o que naturalmente equilibra nível/altura acumulados entre
 * os times. Depois faz uma passada de trocas pontuais (mesmo nível ou o mais próximo)
 * pra equilibrar a quantidade de mulheres por time.
 *
 * $seed (opcional) embaralha jogadores empatados em nível+altura antes de ordenar, pra
 * permitir "sortear novamente" e variar o resultado sem quebrar o equilíbrio.
 */
function batebolaSortearTimes(array $jogadores, ?int $seed = null): array
{
    if (empty($jogadores)) return [];

    foreach ($jogadores as &$j) {
        $j['nivel']     = isset($j['nivel']) ? (int) $j['nivel'] : 3;
        $j['altura_cm'] = isset($j['altura_cm']) && $j['altura_cm'] !== null ? (int) $j['altura_cm'] : 170;
        $j['sexo']      = $j['sexo'] ?? 'masculino';
    }
    unset($j);

    if ($seed !== null) {
        mt_srand($seed);
        shuffle($jogadores);
    }

    usort($jogadores, function ($a, $b) {
        if ($a['nivel'] !== $b['nivel']) return $b['nivel'] <=> $a['nivel'];
        return $b['altura_cm'] <=> $a['altura_cm'];
    });

    $numTimes = min(count(BATEBOLA_TIME_CORES), max(1, (int) ceil(count($jogadores) / 6)));

    $times = array_fill(0, $numTimes, []);
    $idx = 0;
    $dir = 1;
    foreach ($jogadores as $j) {
        $times[$idx][] = $j;
        if ($numTimes > 1) {
            $idx += $dir;
            if ($idx === $numTimes) { $idx = $numTimes - 1; $dir = -1; }
            elseif ($idx < 0) { $idx = 0; $dir = 1; }
        }
    }

    $times = batebolaEquilibrarSexoTimes($times);

    $resultado = [];
    foreach ($times as $i => $membros) {
        $resultado[] = ['cor' => BATEBOLA_TIME_CORES[$i], 'jogadores' => $membros];
    }
    return $resultado;
}

/** Troca jogadores de nível igual (ou o mais próximo) entre times pra equilibrar homens/mulheres. */
function batebolaEquilibrarSexoTimes(array $times): array
{
    if (count($times) < 2) return $times;

    for ($iter = 0; $iter < 40; $iter++) {
        $femPorTime = array_map(function ($time) {
            return count(array_filter($time, fn ($p) => $p['sexo'] === 'feminino'));
        }, $times);

        $timeMax = array_keys($femPorTime, max($femPorTime))[0];
        $timeMin = array_keys($femPorTime, min($femPorTime))[0];

        if ($femPorTime[$timeMax] - $femPorTime[$timeMin] < 2) break;

        $melhorSwap = null;
        $melhorDiff = null;
        foreach ($times[$timeMax] as $i => $mulher) {
            if ($mulher['sexo'] !== 'feminino') continue;
            foreach ($times[$timeMin] as $j => $homem) {
                if ($homem['sexo'] !== 'masculino') continue;
                $diff = abs($mulher['nivel'] - $homem['nivel']);
                if ($melhorDiff === null || $diff < $melhorDiff) {
                    $melhorDiff = $diff;
                    $melhorSwap = [$i, $j];
                }
            }
        }

        if ($melhorSwap === null) break;

        [$i, $j] = $melhorSwap;
        $tmp = $times[$timeMax][$i];
        $times[$timeMax][$i] = $times[$timeMin][$j];
        $times[$timeMin][$j] = $tmp;
    }

    return $times;
}

// ─────────────────────────────────────────────────────────────────────────────
// Ajustes manuais do admin sobre o sorteio
// ─────────────────────────────────────────────────────────────────────────────
//
// Os times NÃO ficam gravados: batebolaSortearTimes() os recalcula sempre, a partir do
// seed salvo em configuracoes. Isso é bom (o resultado é reproduzível), mas significa que
// uma troca feita à mão sumiria no próximo carregamento da página.
//
// Por isso a troca é guardada à parte, como uma LISTA DE PARES de jogadores, e reaplicada
// por cima do sorteio toda vez que os times são montados. Guardar os pares — e não os times
// prontos — mantém o sorteio como fonte da verdade: se alguém entrar ou sair da lista, o
// equilíbrio é refeito e as trocas continuam valendo em cima do resultado novo.

/** Chave em `configuracoes` onde ficam as trocas manuais de um domingo. */
function batebolaChaveTrocas(string $dataEvento): string
{
    return 'batebola_trocas_' . $dataEvento;
}

/** Trocas manuais salvas pro domingo, como lista de pares [idA, idB]. */
function batebolaTrocasSalvas(PDO $pdo, string $dataEvento): array
{
    $st = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $st->execute([batebolaChaveTrocas($dataEvento)]);
    $json = $st->fetchColumn();

    if ($json === false) return [];

    $trocas = json_decode((string) $json, true);
    return is_array($trocas) ? $trocas : [];
}

function batebolaSalvarTrocas(PDO $pdo, string $dataEvento, array $trocas): void
{
    $pdo->prepare("
        INSERT INTO configuracoes (chave, valor)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = CURRENT_TIMESTAMP
    ")->execute([batebolaChaveTrocas($dataEvento), json_encode(array_values($trocas))]);
}

/** Zera as trocas — usado ao sortear de novo ou cancelar o sorteio. */
function batebolaLimparTrocas(PDO $pdo, string $dataEvento): void
{
    $pdo->prepare("DELETE FROM configuracoes WHERE chave = ?")
        ->execute([batebolaChaveTrocas($dataEvento)]);
}

/**
 * Reaplica as trocas manuais em cima do resultado do sorteio.
 *
 * Um par cujos jogadores não estejam mais nos times (saiu da lista, por exemplo) é
 * simplesmente ignorado — a troca deixa de fazer sentido sozinha, sem precisar de limpeza.
 */
function batebolaAplicarTrocas(array $times, array $trocas): array
{
    if (empty($times) || empty($trocas)) return $times;

    foreach ($trocas as $par) {
        if (!is_array($par) || count($par) !== 2) continue;

        $posA = batebolaPosicaoJogador($times, (int) $par[0]);
        $posB = batebolaPosicaoJogador($times, (int) $par[1]);
        if ($posA === null || $posB === null) continue;

        [$tA, $iA] = $posA;
        [$tB, $iB] = $posB;
        if ($tA === $tB) continue; // trocar dentro do mesmo time não muda nada

        $tmp = $times[$tA]['jogadores'][$iA];
        $times[$tA]['jogadores'][$iA] = $times[$tB]['jogadores'][$iB];
        $times[$tB]['jogadores'][$iB] = $tmp;
    }

    return $times;
}

/** Onde um jogador está: [índice do time, índice dentro do time] ou null. */
function batebolaPosicaoJogador(array $times, int $jogadorId): ?array
{
    foreach ($times as $t => $time) {
        foreach ($time['jogadores'] as $i => $j) {
            if ((int) ($j['id'] ?? 0) === $jogadorId) return [$t, $i];
        }
    }
    return null;
}

/**
 * Monta os times de um domingo já com as trocas manuais aplicadas.
 *
 * Existe pra que admin e jogador vejam exatamente a mesma escalação — antes cada tela
 * repetia a montagem, e bastava uma esquecer as trocas pra mostrarem times diferentes.
 *
 * @return array Lista de ['cor' => string, 'jogadores' => array]; vazio se ainda não sorteou.
 */
function batebolaTimesDoEvento(PDO $pdo, string $dataEvento): array
{
    $st = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $st->execute(['batebola_times_' . $dataEvento]);
    $seed = $st->fetchColumn();

    if ($seed === false) return [];

    $stJog = $pdo->prepare("
        SELECT j.id, j.nome, j.nivel, j.altura_cm, j.sexo, j.foto
        FROM batebola_inscricoes bi
        JOIN jogadores_batebola j ON j.id = bi.jogador_id
        WHERE bi.data_evento = ? AND bi.status = 'pago'
        ORDER BY j.nome ASC
    ");
    $stJog->execute([$dataEvento]);

    $times = batebolaSortearTimes($stJog->fetchAll(), (int) $seed);

    return batebolaAplicarTrocas($times, batebolaTrocasSalvas($pdo, $dataEvento));
}

/**
 * Registra que o admin incluiu alguém na lista (recebeu por fora) ou tirou alguém
 * (devolveu a grana). É o que alimenta o sininho — serve de lembrete do dinheiro que
 * entrou ou saiu sem passar pelo Mercado Pago.
 */
function batebolaRegistrarMovimentacao(
    PDO $pdo,
    int $jogadorId,
    string $dataEvento,
    string $acao,
    ?float $valor = null,
    ?int $usuarioId = null,
    ?string $observacao = null
): void {
    if (!in_array($acao, ['incluido', 'removido'], true)) return;

    try {
        $pdo->prepare("
            INSERT INTO batebola_movimentacoes
                (jogador_id, data_evento, acao, valor, usuario_id, observacao)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$jogadorId, $dataEvento, $acao, $valor, $usuarioId, $observacao]);
    } catch (Throwable $e) {
        // Falhar ao registrar o lembrete nunca pode desfazer a inclusão/remoção em si.
        error_log('[batebola-movimentacao] ' . $e->getMessage());
    }
}
