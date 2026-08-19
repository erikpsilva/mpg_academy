<?php

require_once __DIR__ . '/mensalidades.php';

// ─── Credenciais de Produção ──────────────────────────────────────────────────
define('MP_PUBLIC_KEY_PROD',   'APP_USR-497a2b32-3066-4547-aa73-d0df2ae8cbbc');
define('MP_ACCESS_TOKEN_PROD', 'APP_USR-4134788022840522-081916-6ce126f8c99d36be892e4409af96fffb-3629082884');

// ─── Credenciais de Teste ─────────────────────────────────────────────────────
define('MP_PUBLIC_KEY_TEST',   'TEST-13eed63f-902c-4298-bc56-8d3e296a51d7');
define('MP_ACCESS_TOKEN_TEST', 'TEST-4134788022840522-081916-dd4de08a42a18d566e72580f1602783d-3629082884');

// ─── Assinatura secreta dos Webhooks (valida que a notificação veio do MP) ────
// Assinatura secreta do webhook da aplicação 4134788022840522 (conta CNPJ).
//
// O valor anterior era o da conta ANTIGA (131746200). Como os pagamentos passaram a nascer
// na conta CNPJ, o MP assinava com outro segredo e mp_webhook.php rejeitava TODA notificação
// com 401 "Assinatura inválida" — foi o que deixou batebola-45 e batebola-32 aprovados no
// Mercado Pago e parados em 'pendente' no sistema.
//
// Trocar aqui exige trocar junto no painel (Notificações → Webhooks → Redefinir), e vice-versa.
define('MP_WEBHOOK_SECRET_PROD', '309d851d1ecfa52bb912f823f07ea0440624642381dd19625e39be712165422a');
define('MP_WEBHOOK_SECRET_TEST', '');

/**
 * Liga/desliga a seção de pagamento automático no cartão salvo em /meuperfil.
 *
 * true mantém exatamente o comportamento atual de produção. Existe porque a tela do
 * perfil (trazida da versaoPROD) consulta esta constante — sem ela a página quebra.
 * Trocar pra false esconde a seção sem mexer em mais nada.
 */
const MP_COBRANCA_AUTOMATICA_ATIVA = true;

// ─── Tradução dos códigos de rejeição mais comuns do MP (pra log/diagnóstico) ──
const MP_STATUS_DETAIL_PT = [
    'cc_rejected_insufficient_amount'        => 'saldo/limite insuficiente',
    'cc_rejected_bad_filled_security_code'   => 'CVV incorreto ou não fornecido — cartão exige revalidação, não suporta cobrança recorrente sem CVV',
    'cc_rejected_bad_filled_date'            => 'data de validade incorreta',
    'cc_rejected_bad_filled_card_number'     => 'número do cartão incorreto',
    'cc_rejected_bad_filled_other'           => 'dado do cartão incorreto',
    'cc_rejected_call_for_authorize'         => 'banco exige autorização manual (aluno precisa ligar pro banco)',
    'cc_rejected_card_disabled'              => 'cartão desabilitado — aluno precisa ligar pro banco',
    'cc_rejected_card_error'                 => 'não foi possível processar o cartão',
    'cc_rejected_duplicated_payment'         => 'pagamento duplicado (já existe um igual recente)',
    'cc_rejected_high_risk'                  => 'recusado por análise antifraude do Mercado Pago',
    'cc_rejected_max_attempts'               => 'excedeu tentativas com esse cartão',
    'cc_rejected_other_reason'               => 'recusado pelo banco emissor (motivo genérico, sem detalhe)',
    'cc_rejected_invalid_installments'       => 'parcelamento inválido',
    'cc_rejected_blacklist'                  => 'cartão/titular bloqueado',
];

/**
 * O que a secretaria deve orientar o aluno a fazer, por motivo de recusa. Aparece na tela
 * admin/erros-pagamento pra quem for ajudar o aluno saber o que falar sem precisar
 * interpretar código do Mercado Pago.
 */
const MP_STATUS_DETAIL_ACAO = [
    'cc_rejected_insufficient_amount'      => 'Peça pro aluno usar outro cartão ou pagar via PIX.',
    'cc_rejected_bad_filled_security_code' => 'Peça pro aluno conferir o CVV (3 dígitos no verso; 4 na frente no Amex).',
    'cc_rejected_bad_filled_date'          => 'Peça pro aluno conferir o mês/ano de validade do cartão.',
    'cc_rejected_bad_filled_card_number'   => 'Peça pro aluno conferir o número do cartão.',
    'cc_rejected_bad_filled_other'         => 'Peça pro aluno revisar todos os dados do cartão (número, validade, CVV e CPF do titular).',
    'cc_rejected_call_for_authorize'       => 'O aluno precisa ligar pro banco e autorizar essa compra, depois tentar de novo.',
    'cc_rejected_card_disabled'            => 'Cartão bloqueado/desabilitado — o aluno precisa falar com o banco ou usar outro cartão.',
    'cc_rejected_card_error'               => 'Peça pro aluno tentar de novo em alguns minutos ou usar outro cartão.',
    'cc_rejected_duplicated_payment'       => 'Provavelmente já existe um pagamento igual. Confira no Mercado Pago antes de cobrar de novo.',
    'cc_rejected_high_risk'                => 'Antifraude do Mercado Pago recusou. Oriente pagar via PIX — costuma passar.',
    'cc_rejected_max_attempts'             => 'Muitas tentativas com esse cartão. Peça pra aguardar e usar outro cartão ou PIX.',
    'cc_rejected_other_reason'             => 'Recusa genérica do banco emissor. Oriente tentar outro cartão ou PIX.',
    'cc_rejected_invalid_installments'     => 'O cartão não aceita esse número de parcelas. Peça pra escolher menos parcelas.',
    'cc_rejected_blacklist'                => 'Cartão/titular bloqueado pelo Mercado Pago. Só via PIX ou outro cartão.',
];

/**
 * Registra uma falha de pagamento pra aparecer em admin/erros-pagamento e no sino.
 *
 * Nunca lança exceção: um problema ao gravar o log não pode quebrar (nem mascarar) o
 * fluxo de pagamento que já estava dando errado. Também nunca guarda dado sensível de
 * cartão — só o que o próprio Mercado Pago devolve como motivo.
 *
 * @param array $dados Chaves aceitas: aluno_id, aluno_nome, aluno_email, contexto,
 *        referencia_id, referencia_label, valor, metodo, parcelas, origem, mp_payment_id,
 *        mp_status, mp_status_detail, http_code, mensagem, detalhe_tecnico.
 */
function mpRegistrarErroPagamento(PDO $pdo, array $dados): void
{
    try {
        // A mesma recusa pode chegar por mais de um caminho (resposta do checkout, polling do
        // navegador, webhook) — todos reconsultam o MESMO pagamento no MP. Sem esse guarda, uma
        // recusa só viraria várias linhas repetidas em admin/erros-pagamento e vários avisos no
        // sino. Só vale quando há mp_payment_id: falha de rede e erro 400 da API não têm id, e
        // aí cada tentativa é mesmo um evento distinto.
        if (!empty($dados['mp_payment_id'])) {
            $stDup = $pdo->prepare("SELECT id FROM pagamento_erros WHERE mp_payment_id = ? LIMIT 1");
            $stDup->execute([$dados['mp_payment_id']]);
            if ($stDup->fetchColumn()) return;
        }

        $st = $pdo->prepare("
            INSERT INTO pagamento_erros
                (aluno_id, aluno_nome, aluno_email, contexto, referencia_id, referencia_label,
                 valor, metodo, parcelas, origem, mp_payment_id, mp_status, mp_status_detail,
                 http_code, mensagem, detalhe_tecnico)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $st->execute([
            $dados['aluno_id']         ?? null,
            $dados['aluno_nome']       ?? null,
            $dados['aluno_email']      ?? null,
            $dados['contexto']         ?? 'outro',
            $dados['referencia_id']    ?? null,
            $dados['referencia_label'] ?? null,
            $dados['valor']            ?? null,
            $dados['metodo']           ?? null,
            $dados['parcelas']         ?? null,
            $dados['origem']           ?? 'site',
            $dados['mp_payment_id']    ?? null,
            $dados['mp_status']        ?? null,
            $dados['mp_status_detail'] ?? null,
            $dados['http_code']        ?? null,
            mb_substr((string) ($dados['mensagem'] ?? 'Erro no pagamento'), 0, 255),
            $dados['detalhe_tecnico']  ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('[pagamento-erro-log] ' . $e->getMessage());
    }
}

/**
 * Como o dinheiro entrou, em português.
 *
 * `mensalidades.mp_payment_method` (e `pedidos_uniforme.mp_payment_method`) guardam o
 * **payment_type_id** do Mercado Pago — não o payment_method_id. Por isso PIX chega como
 * `bank_transfer`, e não como `pix`.
 *
 * @param string|null $paymentType payment_type_id do MP (bank_transfer, credit_card, ...).
 * @param bool $recorrente         Se veio da cobrança automática no cartão salvo (cron).
 * @param bool $manual             Lançamento manual/externo, sem passar pelo Mercado Pago.
 */
function mpFormaPagamentoLabel(?string $paymentType, bool $recorrente = false, bool $manual = false): string
{
    if ($manual) return 'Externo/manual';

    if ($paymentType === null || $paymentType === '') {
        return 'Não informado';
    }

    $labels = [
        'bank_transfer' => 'PIX',
        'pix'           => 'PIX',
        'credit_card'   => 'Cartão de crédito',
        'debit_card'    => 'Cartão de débito',
        'prepaid_card'  => 'Cartão pré-pago',
        'ticket'        => 'Boleto',
        'account_money' => 'Saldo Mercado Pago',
        'bank_transfer_pix' => 'PIX',
    ];

    $label = $labels[$paymentType] ?? $paymentType;

    // Só faz sentido marcar recorrência em cartão — PIX nunca é cobrado automaticamente.
    if ($recorrente && in_array($paymentType, ['credit_card', 'debit_card', 'prepaid_card'], true)) {
        $label .= ' (recorrente)';
    }

    return $label;
}

/** Explicação em português do motivo da recusa, pronta pra exibir no admin. */
function mpMotivoPt(?string $statusDetail, ?string $fallback = null): string
{
    if ($statusDetail && isset(MP_STATUS_DETAIL_PT[$statusDetail])) {
        return MP_STATUS_DETAIL_PT[$statusDetail];
    }
    return $fallback ?: ($statusDetail ?: 'Motivo não informado pelo Mercado Pago');
}

/** O que orientar o aluno a fazer, quando conhecemos o motivo. */
function mpAcaoSugerida(?string $statusDetail): ?string
{
    return $statusDetail ? (MP_STATUS_DETAIL_ACAO[$statusDetail] ?? null) : null;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Calcula multa (5%) + juros (0,5% ao dia) de uma fatura atrasada — mesma regra usada
 * em toda cobrança (site, mobile, cobrança automática). Se a fatura não estiver
 * vencida, retorna tudo zerado e total = valor original.
 */
function mpCalcularMultaJuros(float $valor, string $vencimento, ?string $hoje = null): array
{
    $hoje   = $hoje ?? date('Y-m-d');
    $vencDt = new DateTime(substr($vencimento, 0, 10));
    $hojeDt = new DateTime(substr($hoje, 0, 10));

    if ($hojeDt <= $vencDt) {
        return ['dias_atraso' => 0, 'multa' => 0.0, 'juros' => 0.0, 'total' => round($valor, 2)];
    }

    $dias  = (int) $vencDt->diff($hojeDt)->days;
    $multa = $valor * 0.05;
    $base  = $valor + $multa;
    $juros = $base * 0.005 * $dias;
    $total = round($base + $juros, 2);

    return [
        'dias_atraso' => $dias,
        'multa'       => round($multa, 2),
        'juros'       => round($juros, 2),
        'total'       => $total,
    ];
}

function mpModoTeste(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $st = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'pagamento_modo_teste'");
    $st->execute();
    $row   = $st->fetch();
    $cache = $row && $row['valor'] === '1';
    return $cache;
}

function mpPublicKey(PDO $pdo): string
{
    return mpModoTeste($pdo) ? MP_PUBLIC_KEY_TEST : MP_PUBLIC_KEY_PROD;
}

function mpAccessToken(PDO $pdo): string
{
    return mpModoTeste($pdo) ? MP_ACCESS_TOKEN_TEST : MP_ACCESS_TOKEN_PROD;
}

function mpWebhookSecret(PDO $pdo): string
{
    return mpModoTeste($pdo) ? MP_WEBHOOK_SECRET_TEST : MP_WEBHOOK_SECRET_PROD;
}

/**
 * Valida a assinatura HMAC do webhook do MP (header x-signature), conforme
 * https://www.mercadopago.com.br/developers — garante que a notificação
 * realmente veio do Mercado Pago e não foi forjada por terceiros.
 */
function mpValidarAssinaturaWebhook(string $secret, string $xSignature, string $xRequestId, string $dataId): bool
{
    if ($secret === '' || $xSignature === '') return false;

    $partes = [];
    foreach (explode(',', $xSignature) as $par) {
        $kv = explode('=', trim($par), 2);
        if (count($kv) === 2) $partes[trim($kv[0])] = trim($kv[1]);
    }
    $ts = $partes['ts'] ?? '';
    $v1 = $partes['v1'] ?? '';
    if ($ts === '' || $v1 === '') return false;

    $manifest = 'id:' . strtolower($dataId) . ';request-id:' . $xRequestId . ';ts:' . $ts . ';';
    $hash     = hash_hmac('sha256', $manifest, $secret);

    return hash_equals($hash, strtolower($v1));
}

/**
 * Cria um pagamento via Mercado Pago API v1/payments.
 * Retorna ['http_code' => int, 'body' => array].
 */
function mpCriarPagamento(string $accessToken, array $dados): array
{
    $ch = curl_init('https://api.mercadopago.com/v1/payments');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($dados),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'X-Idempotency-Key: mpg-' . uniqid('', true),
        ],
        // Em produção, SSL é sempre verificado.
        // Em local (XAMPP/Windows) pode falhar sem CA bundle — desativa verificação.
        CURLOPT_SSL_VERIFYPEER => !APP_IS_LOCAL,
        CURLOPT_SSL_VERIFYHOST => APP_IS_LOCAL ? 0 : 2,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErro = curl_error($ch);
    curl_close($ch);

    $body = json_decode($resp ?: '{}', true) ?? [];

    // Falha de rede/SSL não devolve corpo nenhum — sem isso o motivo real sumia e sobrava
    // só um "Pagamento recusado" genérico, impossível de diagnosticar depois.
    if ($curlErro !== '' && empty($body)) {
        $body['message'] = 'Falha de conexão com o Mercado Pago: ' . $curlErro;
    }

    return ['http_code' => $httpCode, 'body' => $body];
}

/**
 * Helper genérico para chamadas à API do Mercado Pago (customers/cards/card_tokens).
 * Retorna ['http_code' => int, 'body' => array].
 */
function mpRequest(string $accessToken, string $method, string $path, ?array $body = null): array
{
    $ch = curl_init('https://api.mercadopago.com' . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_SSL_VERIFYPEER => !APP_IS_LOCAL,
        CURLOPT_SSL_VERIFYHOST => APP_IS_LOCAL ? 0 : 2,
        CURLOPT_TIMEOUT        => 30,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => json_decode($resp ?: '{}', true) ?? []];
}

/**
 * Busca um customer do MP pelo e-mail, criando um novo caso não exista.
 * Necessário pra poder anexar cartões salvos e cobrar depois sem o aluno presente.
 */
function mpObterOuCriarCustomer(string $accessToken, string $email): ?string
{
    $busca   = mpRequest($accessToken, 'GET', '/v1/customers/search?email=' . urlencode($email));
    $achados = $busca['body']['results'] ?? [];
    if (!empty($achados[0]['id'])) {
        return $achados[0]['id'];
    }

    $criado = mpRequest($accessToken, 'POST', '/v1/customers', ['email' => $email]);
    return $criado['body']['id'] ?? null;
}

/**
 * Anexa um cartão tokenizado (token gerado no front via Brick) a um customer do MP.
 * Retorna os dados do cartão salvo (id, last_four_digits, payment_method...) ou null em falha.
 */
function mpSalvarCartaoCustomer(string $accessToken, string $customerId, string $cardToken): ?array
{
    $resp = mpRequest($accessToken, 'POST', '/v1/customers/' . urlencode($customerId) . '/cards', ['token' => $cardToken]);
    return !empty($resp['body']['id']) ? $resp['body'] : null;
}

/**
 * Remove um cartão salvo de um customer do MP.
 */
function mpRemoverCartaoCustomer(string $accessToken, string $customerId, string $cardId): bool
{
    $resp = mpRequest($accessToken, 'DELETE', '/v1/customers/' . urlencode($customerId) . '/cards/' . urlencode($cardId));
    return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
}

/**
 * Extrai a mensagem de erro mais útil de uma resposta de erro da API do MP. O detalhe real
 * de rejeição/validação costuma vir em `cause[]` (não em `message`, que é genérica tipo
 * "invalid parameters"), e a razão de recusa de pagamento vem em `status_detail` — junta os
 * dois quando disponíveis, pra nunca perder informação de diagnóstico.
 */
function mpExtrairErroApi(array $body, ?string $statusDetail = null): string
{
    $partes = [];
    if ($statusDetail) {
        $partes[] = 'status_detail: ' . $statusDetail . (MP_STATUS_DETAIL_PT[$statusDetail] ?? '' ? ' (' . MP_STATUS_DETAIL_PT[$statusDetail] . ')' : '');
    }
    if (!empty($body['cause']) && is_array($body['cause'])) {
        foreach ($body['cause'] as $c) {
            $desc = trim($c['description'] ?? '');
            if ($desc !== '') $partes[] = $desc . (isset($c['code']) ? ' (código ' . $c['code'] . ')' : '');
        }
    }
    if (!$partes && !empty($body['message'])) $partes[] = $body['message'];

    return $partes ? implode(' | ', $partes) : 'Erro desconhecido na API do Mercado Pago (resposta sem detalhe).';
}

/**
 * Gera um novo token de pagamento a partir de um cartão já salvo, sem precisar do CVV
 * nem do aluno presente — usado pela cobrança automática recorrente (cron).
 * Retorna ['token' => string|null, 'erro' => string|null] — nunca descarta o motivo da
 * falha (importante pra diagnosticar cartão sem suporte a cobrança recorrente sem CVV).
 */
function mpGerarTokenCartaoSalvo(string $accessToken, string $cardId, string $customerId): array
{
    $resp  = mpRequest($accessToken, 'POST', '/v1/card_tokens', [
        'card_id'     => $cardId,
        'customer_id' => $customerId,
    ]);
    $token = $resp['body']['id'] ?? null;

    return [
        'token' => $token,
        'erro'  => $token ? null : mpExtrairErroApi($resp['body']),
    ];
}

/** Cartões já salvos num customer do Mercado Pago. */
function mpListarCartoesCustomer(string $accessToken, string $customerId): array
{
    $resp = mpRequest($accessToken, 'GET', '/v1/customers/' . $customerId . '/cards');
    $body = $resp['body'] ?? [];

    return is_array($body) && isset($body[0]) ? $body : [];
}

/**
 * Descobre qual cartão do customer corresponde ao pagamento que acabou de ser feito.
 *
 * Roda DEPOIS da cobrança, de propósito: o token do Brick é de uso único e precisa ser
 * gasto no pagamento (é ele que carrega o CVV). Tentar salvar o cartão antes obrigava a
 * gerar um segundo token sem CVV, que o emissor recusa — ver o comentário longo em
 * services/site/criar_pagamento.php.
 *
 * @return array{id:string, bandeira:string, final4:string}|null
 */
function mpResolverCartaoSalvo(string $accessToken, string $customerId, array $payment): ?array
{
    $final4   = $payment['card']['last_four_digits'] ?? '';
    $bandeira = $payment['payment_method_id'] ?? '';

    // 1) O MP costuma devolver, no próprio pagamento, o id do cartão já vinculado ao
    //    customer quando o customer foi informado no payer.
    $idNoPagamento = $payment['card']['id'] ?? null;
    if (!empty($idNoPagamento)) {
        return ['id' => (string) $idNoPagamento, 'bandeira' => $bandeira, 'final4' => $final4];
    }

    // 2) Fallback: procura entre os cartões do customer o que bate com o que foi cobrado.
    //    Cobre o caso de o pagamento não trazer o id, sem depender de suposição.
    if ($final4 === '') return null;

    $mes = $payment['card']['expiration_month'] ?? null;
    $ano = $payment['card']['expiration_year']  ?? null;

    foreach (mpListarCartoesCustomer($accessToken, $customerId) as $c) {
        if (($c['last_four_digits'] ?? '') !== $final4) continue;
        if ($mes !== null && isset($c['expiration_month']) && (int) $c['expiration_month'] !== (int) $mes) continue;
        if ($ano !== null && isset($c['expiration_year'])  && (int) $c['expiration_year']  !== (int) $ano) continue;

        return [
            'id'       => (string) $c['id'],
            'bandeira' => $c['payment_method']['id'] ?? $bandeira,
            'final4'   => $final4,
        ];
    }

    return null;
}

/**
 * Consulta um pagamento direto na API do MP pelo ID. Usado pelo webhook e pela
 * sincronização manual — nunca confiamos no status que vem na notificação, sempre
 * reconsultamos com nosso próprio access token.
 */
function mpConsultarPagamento(string $accessToken, string $paymentId): ?array
{
    $resp = mpRequest($accessToken, 'GET', '/v1/payments/' . urlencode($paymentId));
    return !empty($resp['body']['id']) ? $resp['body'] : null;
}

/**
 * Extrai taxa e valor líquido do objeto de pagamento retornado pela API do MP.
 * Preferência: transaction_details.net_received_amount (valor que efetivamente cai
 * na conta). Fallback: soma de fee_details[].amount. Retorna [taxa, liquido, metodo]
 * — qualquer um pode vir null se o payload não trouxer essa informação (ex.: pagamento
 * manual fora do MP, ou resposta antiga sem esses campos).
 */
function mpExtrairTaxaELiquido(array $payment, float $valorBruto): array
{
    $metodo = $payment['payment_type_id'] ?? null;

    $liquido = $payment['transaction_details']['net_received_amount'] ?? null;
    if ($liquido !== null) {
        $liquido = (float) $liquido;
        $taxa    = round($valorBruto - $liquido, 2);
        return [$taxa, $liquido, $metodo];
    }

    if (!empty($payment['fee_details']) && is_array($payment['fee_details'])) {
        $taxa = round(array_sum(array_column($payment['fee_details'], 'amount')), 2);
        return [$taxa, round($valorBruto - $taxa, 2), $metodo];
    }

    return [null, null, $metodo];
}

/**
 * Marca uma mensalidade como paga e lança no financeiro. Idempotente: se já estiver
 * paga, não faz nada (protege contra notificações duplicadas do MP, que reenvia
 * webhooks até receber 200).
 *
 * @param array|null $payment      Objeto de pagamento completo retornado pela API do MP
 *                                  (mpConsultarPagamento/mpCriarPagamento) — usado pra
 *                                  registrar taxa/valor líquido/forma de pagamento. Null
 *                                  quando não há esses dados (não deve acontecer nos fluxos
 *                                  via MP, mas o parâmetro é opcional por segurança).
 * @param float|null $valorCobrado Valor realmente cobrado nesse pagamento, quando diferente
 *                                  de mensalidades.valor (ex.: fatura atrasada com multa/juros
 *                                  somados na hora de cobrar). Default: usa mensalidades.valor.
 *                                  Nunca sobrescreve a coluna valor — só afeta o lançamento de
 *                                  receita e o cálculo da taxa/líquido, que devem refletir o
 *                                  valor real da transação no MP.
 */
function mpMarcarMensalidadePaga(PDO $pdo, int $mensalidadeId, string $mpPaymentId, ?array $payment = null, ?float $valorCobrado = null): bool
{
    $st = $pdo->prepare("SELECT id, valor, referencia, aluno_id, turma_id, tipo, status FROM mensalidades WHERE id = ?");
    $st->execute([$mensalidadeId]);
    $mens = $st->fetch();
    if (!$mens || $mens['status'] === 'pago') return false;

    $valorBruto = $valorCobrado ?? (float) $mens['valor'];
    [$taxa, $liquido, $metodo] = $payment ? mpExtrairTaxaELiquido($payment, $valorBruto) : [null, null, null];

    $pdo->prepare("
        UPDATE mensalidades
        SET status = 'pago', data_pagamento = COALESCE(data_pagamento, CURDATE()), mp_payment_id = ?,
            mp_taxa_valor = ?, mp_valor_liquido = ?, mp_payment_method = ?, atualizado_em = NOW()
        WHERE id = ? AND status != 'pago'
    ")->execute([$mpPaymentId, $taxa, $liquido, $metodo, $mensalidadeId]);

    $stAluno = $pdo->prepare("SELECT nome FROM alunos WHERE id = ?");
    $stAluno->execute([$mens['aluno_id']]);
    $alunoNome = $stAluno->fetchColumn() ?: '';

    $competencia = $mens['referencia'];
    $descLanc    = 'Mensalidade ' . $mens['referencia'] . ' — ' . $alunoNome . ' (via MP)';
    try {
        $pdo->prepare("
            INSERT IGNORE INTO lancamentos_financeiros
                (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
            VALUES (?, CURDATE(), 'receita', 'mensalidade', ?, ?, 'auto', 'mensalidade', ?)
        ")->execute([$competencia, $descLanc, $valorBruto, $mensalidadeId]);
    } catch (PDOException $e) {}

    // Taxa do MP lançada como despesa separada — receita continua bruta (correto pra
    // contabilidade/imposto), mas o Saldo em Caixa já reflete a realidade líquida.
    if ($taxa !== null && $taxa > 0) {
        $descTaxa = 'Taxa Mercado Pago — Mensalidade ' . $mens['referencia'] . ' — ' . $alunoNome;
        try {
            $pdo->prepare("
                INSERT IGNORE INTO lancamentos_financeiros
                    (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
                VALUES (?, CURDATE(), 'despesa', 'taxa_mercadopago', ?, ?, 'auto', 'mensalidade_taxa_mp', ?)
            ")->execute([$competencia, $descTaxa, $taxa, $mensalidadeId]);
        } catch (PDOException $e) {}
    }

    // Paguei o mês atual → já gera a fatura do mês seguinte na hora, sem esperar o fallback
    // diário (gerarMensalidadesMesAtual()) — assim o aluno nunca fica com duas faturas em
    // aberto ao mesmo tempo sem necessidade, e vê a próxima fatura assim que quita a atual.
    if (($mens['tipo'] ?? 'mensalidade') === 'mensalidade' && $mens['turma_id']) {
        $proximoMes = (new DateTime($mens['referencia'] . '-01'))->modify('+1 month')->format('Y-m');
        gerarMensalidadeRecorrente($pdo, (int) $mens['aluno_id'], (int) $mens['turma_id'], $proximoMes);
    }

    return true;
}

/**
 * Cobra automaticamente, via cartão salvo, todas as mensalidades pendentes/atrasadas já
 * vencidas de alunos com auto_pagamento=1. Nunca tenta a mesma mensalidade duas vezes no
 * mesmo dia (cobranca_automatica_log). Usada tanto pelo cron (cron/cobranca_automatica.php,
 * disparado pelo cPanel) quanto por um fallback em admin/includes/auth_check.php — se o
 * cron do cPanel não estiver configurado ou falhar, qualquer admin logando no painel no dia
 * já dispara essa mesma cobrança, evitando depender só do agendamento externo.
 *
 * @return array ['sucesso' => int, 'falha' => int, 'detalhes_falha' => [['aluno' => string, 'motivo' => string], ...]]
 */
function mpExecutarCobrancaAutomatica(PDO $pdo): array
{
    $hoje        = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    $accessToken = mpAccessToken($pdo);

    // O JOIN só casa com tentativas de HOJE que já tiveram sucesso — assim, se o cron rodar
    // mais de uma vez no mesmo dia (ex.: manhã e tarde), uma tentativa que falhou de manhã
    // (cartão sem saldo, instabilidade da API etc.) continua elegível pra tentar de novo à
    // tarde, em vez de esperar até o dia seguinte. Uma cobrança com sucesso nunca é repetida.
    $st = $pdo->prepare("
        SELECT m.id AS mensalidade_id, m.referencia, m.tipo, m.descricao, m.valor, m.vencimento, m.status,
               a.id AS aluno_id, a.nome, a.mp_customer_id, a.mp_card_id
        FROM mensalidades m
        JOIN alunos a ON a.id = m.aluno_id
        LEFT JOIN cobranca_automatica_log cl
               ON cl.mensalidade_id = m.id AND cl.data_tentativa = ? AND cl.status = 'sucesso'
        WHERE m.status IN ('pendente', 'atrasado')
          AND DATE(m.vencimento) <= ?
          AND a.auto_pagamento = 1
          AND a.mp_customer_id IS NOT NULL
          AND a.mp_card_id IS NOT NULL
          AND cl.id IS NULL
    ");
    $st->execute([$hoje, $hoje]);
    $mensalidades = $st->fetchAll(PDO::FETCH_ASSOC);

    $sucesso       = 0;
    $falha         = 0;
    $detalhesFalha = [];

    $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

    foreach ($mensalidades as $m) {
        $valor = (float) $m['valor'];
        $total = $m['status'] === 'atrasado'
            ? mpCalcularMultaJuros($valor, $m['vencimento'], $hoje)['total']
            : $valor;

        $isAvulso = ($m['tipo'] ?? 'mensalidade') === 'avulso';
        if ($isAvulso) {
            $refLabel = $m['descricao'] ?? 'Cobrança extra';
        } else {
            [$refAno, $refMes] = explode('-', $m['referencia']);
            $refLabel = ($meses[$refMes] ?? $refMes) . '/' . $refAno;
        }

        $tokenResult = mpGerarTokenCartaoSalvo($accessToken, $m['mp_card_id'], $m['mp_customer_id']);

        if (!$tokenResult['token']) {
            $motivoToken = 'Token do cartão: ' . $tokenResult['erro'];
            mpLogCobrancaAutomatica($pdo, $m['aluno_id'], $m['mensalidade_id'], $hoje, 'falha', $motivoToken);
            $detalhesFalha[] = ['aluno' => $m['nome'], 'motivo' => $motivoToken];
            $falha++;
            continue;
        }
        $cardToken = $tokenResult['token'];

        $paymentData = [
            'transaction_amount' => $total,
            'token'              => $cardToken,
            'description'        => 'MPG Academy — Mensalidade ' . $refLabel,
            'installments'       => 1,
            'payer'              => [
                'type' => 'customer',
                'id'   => $m['mp_customer_id'],
            ],
            'metadata' => ['mensalidade_id' => $m['mensalidade_id'], 'origem' => 'cobranca_automatica'],
        ];

        $result      = mpCriarPagamento($accessToken, $paymentData);
        $body        = $result['body'];
        $status      = $body['status'] ?? '';
        $mpPaymentId = $body['id'] ?? null;

        if ($status === 'approved') {
            // $total pode incluir multa/juros quando a fatura está atrasada — passado como
            // valorCobrado pra refletir no lançamento de receita e no cálculo da taxa do MP,
            // sem sobrescrever mensalidades.valor (que continua sendo o valor "limpo" da fatura).
            mpMarcarMensalidadePaga($pdo, $m['mensalidade_id'], (string) $mpPaymentId, $body, $total);

            mpLogCobrancaAutomatica($pdo, $m['aluno_id'], $m['mensalidade_id'], $hoje, 'sucesso', null, $mpPaymentId);
            $sucesso++;
        } else {
            $statusDetail = $body['status_detail'] ?? null;
            $motivo = 'status: ' . ($status ?: 'sem resposta') . ' | ' . mpExtrairErroApi($body, $statusDetail);
            mpLogCobrancaAutomatica($pdo, $m['aluno_id'], $m['mensalidade_id'], $hoje, 'falha', $motivo, $mpPaymentId);

            // Também entra em admin/erros-pagamento: a cobrança automática falha sem ninguém
            // olhando, então é justamente onde o admin mais precisa ser avisado.
            mpRegistrarErroPagamento($pdo, [
                'aluno_id'         => (int) $m['aluno_id'],
                'aluno_nome'       => $m['nome'] ?? null,
                'contexto'         => 'mensalidade',
                'referencia_id'    => (int) $m['mensalidade_id'],
                'referencia_label' => 'Cobrança automática (cartão salvo)',
                'valor'            => $total,
                'metodo'           => 'cartao_salvo',
                'origem'           => 'cron',
                'mp_payment_id'    => $mpPaymentId,
                'mp_status'        => $status ?: 'sem resposta',
                'mp_status_detail' => $statusDetail,
                'mensagem'         => mpMotivoPt($statusDetail, $body['message'] ?? null),
                'detalhe_tecnico'  => $motivo,
            ]);

            $detalhesFalha[] = ['aluno' => $m['nome'], 'motivo' => $motivo];
            $falha++;
        }
    }

    return ['sucesso' => $sucesso, 'falha' => $falha, 'detalhes_falha' => $detalhesFalha];
}

function mpLogCobrancaAutomatica(PDO $pdo, int $alunoId, int $mensalidadeId, string $hoje, string $status, ?string $motivo = null, ?string $mpPaymentId = null): void
{
    // motivo_falha é varchar(255) — trunca pra nunca estourar a coluna (o motivo agora pode
    // incluir cause[] da API do MP, que às vezes é longo). E envolve em try/catch: uma falha
    // ao GRAVAR o log nunca pode interromper o loop de cobrança dos próximos alunos.
    if ($motivo !== null && strlen($motivo) > 255) {
        $motivo = substr($motivo, 0, 252) . '...';
    }

    // uk_mensalidade_dia (mensalidade_id, data_tentativa) permite só 1 linha por dia — em vez
    // de ignorar uma segunda tentativa no mesmo dia (ex.: rodada da tarde após falha de manhã),
    // atualiza a linha existente pra refletir o resultado mais recente daquele dia.
    try {
        $pdo->prepare("
            INSERT INTO cobranca_automatica_log (aluno_id, mensalidade_id, data_tentativa, status, motivo_falha, mp_payment_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status        = VALUES(status),
                motivo_falha  = VALUES(motivo_falha),
                mp_payment_id = VALUES(mp_payment_id),
                criado_em     = NOW()
        ")->execute([$alunoId, $mensalidadeId, $hoje, $status, $motivo, $mpPaymentId]);
    } catch (PDOException $e) {
        error_log('[mpg-cobranca-log] falha ao gravar log (mensalidade ' . $mensalidadeId . '): ' . $e->getMessage());
    }
}
