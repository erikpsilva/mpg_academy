<?php

/**
 * Conciliação com o Mercado Pago: procura no MP cobranças APROVADAS que no sistema ainda
 * estão em aberto e confirma.
 *
 * Existe porque a confirmação não pode depender só dos dois canais "ao vivo":
 *  - o webhook, que depende de configuração no painel do MP (e do segredo certo);
 *  - o polling da tela de pagamento, que só roda enquanto a pessoa está com a tela aberta.
 * Quem paga o PIX e fecha a página — caso mais comum no Bate Bola — ficava pendente para
 * sempre e fora da lista, mesmo com o dinheiro na conta.
 *
 * Como funciona: para cada item em aberto, busca no MP pelo external_reference que gravamos
 * na criação (`batebola-123`, `uniforme-45`, `mensalidade-678`), filtrando só aprovados.
 * Buscar por referência, e não só pelo mp_payment_id salvo, cobre o caso de a pessoa gerar
 * um PIX, gerar outro e pagar o primeiro: o id salvo é o do segundo, mas o aprovado é o
 * primeiro — os dois têm a mesma referência.
 *
 * Só marca como pago o que a API do MP responder `approved` com o nosso token, e as funções
 * de confirmação são idempotentes: rodar de novo, ou junto com o webhook, não duplica nada.
 *
 * Chamada pelo cron (cron/conciliar_pagamentos.php) e, com intervalo mínimo, ao abrir
 * páginas do Bate Bola e o painel admin.
 */

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/mercadopago.php';
require_once __DIR__ . '/batebola.php';
require_once __DIR__ . '/uniformes.php';

/** Intervalo mínimo (s) entre duas rodadas de cada tipo, pra não martelar a API do MP. */
const MP_CONCILIACAO_INTERVALO = [
    'batebola'    => 60,
    'uniforme'    => 300,
    'mensalidade' => 600,
];

/** Mensalidades por rodada — são muitas em aberto; vai rodiziando entre as rodadas. */
const MP_CONCILIACAO_LOTE_MENSALIDADES = 25;

/**
 * Roda a conciliação dos tipos pedidos.
 *
 * $forcar ignora o intervalo mínimo (botão "Sincronizar" do admin, cron).
 * $orcamentoSeg limita o tempo total — o cron da KingHost corta em 60s.
 *
 * Retorna ['batebola' => [ids confirmados], 'uniforme' => [...], 'mensalidade' => [...]].
 */
function mpConciliarPendentes(PDO $pdo, array $tipos = ['batebola', 'uniforme', 'mensalidade'], bool $forcar = false, int $orcamentoSeg = 40): array
{
    $resultado = ['batebola' => [], 'uniforme' => [], 'mensalidade' => []];

    // Em modo teste o token é o de sandbox — as cobranças reais não aparecem nele.
    if (mpModoTeste($pdo)) return $resultado;

    // Uma rodada por vez: duas abas abrindo a página ao mesmo tempo não disparam duas buscas.
    $lock = (int) $pdo->query("SELECT GET_LOCK('mpg_conciliacao_mp', 0)")->fetchColumn();
    if ($lock !== 1) return $resultado;

    try {
        $token  = mpAccessToken($pdo);
        $limite = microtime(true) + $orcamentoSeg;

        foreach ($tipos as $tipo) {
            if (!isset(MP_CONCILIACAO_INTERVALO[$tipo])) continue;
            if (!$forcar && !mpConciliacaoVencida($pdo, $tipo)) continue;
            if (microtime(true) >= $limite) break;

            mpConciliacaoMarcarRodada($pdo, $tipo);

            if ($tipo === 'batebola') {
                $resultado['batebola'] = mpConciliarBatebola($pdo, $token, $limite);
            } elseif ($tipo === 'uniforme') {
                $resultado['uniforme'] = mpConciliarUniformes($pdo, $token, $limite);
            } else {
                $resultado['mensalidade'] = mpConciliarMensalidades($pdo, $token, $limite);
            }
        }
    } catch (Throwable $e) {
        error_log('[mpg-conciliacao] ' . $e->getMessage());
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('mpg_conciliacao_mp')");
    }

    $total = count($resultado['batebola']) + count($resultado['uniforme']) + count($resultado['mensalidade']);
    if ($total > 0) {
        error_log('[mpg-conciliacao] confirmados sem webhook: ' . json_encode($resultado));
    }

    return $resultado;
}

/** Bate Bola: inscrições pendentes do domingo atual/próximo e da última semana. */
function mpConciliarBatebola(PDO $pdo, string $token, float $limite): array
{
    $pendentes = $pdo->query("
        SELECT id, mp_payment_id FROM batebola_inscricoes
        WHERE status = 'pendente' AND data_evento >= CURDATE() - INTERVAL 7 DAY
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $confirmados = [];
    foreach ($pendentes as $p) {
        if (microtime(true) >= $limite) break;
        $pag = mpBuscarAprovado($token, ['batebola-' . $p['id']], $p['mp_payment_id']);
        if ($pag && batebolaConfirmarInscricao($pdo, (int) $p['id'], (string) $pag['id'], $pag)) {
            $confirmados[] = (int) $p['id'];
        }
    }
    return $confirmados;
}

/** Uniformes: pedidos aguardando (ou com reserva expirada) dos últimos 30 dias. */
function mpConciliarUniformes(PDO $pdo, string $token, float $limite): array
{
    $pendentes = $pdo->query("
        SELECT id, mp_payment_id FROM pedidos_uniforme
        WHERE status_pagamento IN ('aguardando', 'expirado')
          AND criado_em >= NOW() - INTERVAL 30 DAY
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $confirmados = [];
    foreach ($pendentes as $p) {
        if (microtime(true) >= $limite) break;
        $pag = mpBuscarAprovado($token, ['uniforme-' . $p['id']], $p['mp_payment_id']);
        if ($pag && uniformeConfirmarPedido($pdo, (int) $p['id'], (string) $pag['id'], $pag)) {
            $confirmados[] = (int) $p['id'];
        }
    }
    return $confirmados;
}

/**
 * Mensalidades em aberto dos últimos 90 dias, num lote por rodada. O cursor guarda o último
 * id visto; quando chega ao fim, recomeça — assim todas são conferidas ao longo das rodadas
 * sem uma rodada só estourar o tempo do cron.
 */
function mpConciliarMensalidades(PDO $pdo, string $token, float $limite): array
{
    $cursor = (int) mpConciliacaoConfig($pdo, 'mp_conciliacao_mensalidade_cursor');

    $sql = "
        SELECT id, mp_payment_id FROM mensalidades
        WHERE status IN ('pendente', 'atrasado')
          AND vencimento >= CURDATE() - INTERVAL 90 DAY
          AND id > ?
        ORDER BY id
        LIMIT " . MP_CONCILIACAO_LOTE_MENSALIDADES;
    $st = $pdo->prepare($sql);
    $st->execute([$cursor]);
    $lote = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$lote && $cursor > 0) {
        $st->execute([0]);
        $lote = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $confirmados = [];
    $ultimoId    = 0;
    foreach ($lote as $m) {
        if (microtime(true) >= $limite) break;
        $ultimoId = (int) $m['id'];
        // "mensalidade-X" é o site; "X" puro é o formato antigo do app mobile.
        $pag = mpBuscarAprovado($token, ['mensalidade-' . $m['id'], (string) $m['id']], $m['mp_payment_id']);
        if ($pag && mpMarcarMensalidadePaga($pdo, (int) $m['id'], (string) $pag['id'], $pag)) {
            $confirmados[] = (int) $m['id'];
        }
    }

    mpConciliacaoSalvarConfig($pdo, 'mp_conciliacao_mensalidade_cursor', (string) $ultimoId);
    return $confirmados;
}

/**
 * Procura um pagamento aprovado: primeiro pelas referências, depois pelo id salvo (cobrança
 * automática no cartão não tem referência, só o id). Devolve o pagamento ou null.
 */
function mpBuscarAprovado(string $token, array $referencias, ?string $mpPaymentId): ?array
{
    foreach ($referencias as $ref) {
        $busca = mpRequest($token, 'GET',
            '/v1/payments/search?external_reference=' . urlencode($ref)
            . '&status=approved&sort=date_created&criteria=desc&limit=1');
        $pag = $busca['body']['results'][0] ?? null;
        // Confere a referência de novo: nunca confirmar um item com pagamento de outro.
        if ($pag && ($pag['status'] ?? '') === 'approved' && ($pag['external_reference'] ?? '') === $ref) {
            return $pag;
        }
    }

    if (!empty($mpPaymentId)) {
        $pag = mpConsultarPagamento($token, (string) $mpPaymentId);
        if ($pag && ($pag['status'] ?? '') === 'approved') return $pag;
    }

    return null;
}

function mpConciliacaoVencida(PDO $pdo, string $tipo): bool
{
    $ultima = (int) mpConciliacaoConfig($pdo, 'mp_conciliacao_' . $tipo . '_em');
    return (time() - $ultima) >= MP_CONCILIACAO_INTERVALO[$tipo];
}

function mpConciliacaoMarcarRodada(PDO $pdo, string $tipo): void
{
    mpConciliacaoSalvarConfig($pdo, 'mp_conciliacao_' . $tipo . '_em', (string) time());
}

function mpConciliacaoConfig(PDO $pdo, string $chave): ?string
{
    $st = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

function mpConciliacaoSalvarConfig(PDO $pdo, string $chave, string $valor): void
{
    $pdo->prepare("
        INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ")->execute([$chave, $valor]);
}
