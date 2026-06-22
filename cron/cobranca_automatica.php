<?php
/**
 * CRON — Cobrança automática de mensalidades via cartão salvo
 * Configurar no cPanel: diariamente
 * Comando: php /home/SEU_USUARIO/public_html/mpg_academy/cron/cobranca_automatica.php
 *
 * Cobra alunos com auto_pagamento=1 e cartão salvo (mp_customer_id/mp_card_id),
 * para mensalidades pendentes/atrasadas já vencidas (vencimento <= hoje).
 * Roda todo dia (não só na janela 5-10) para também tentar novamente quem está
 * atrasado, até o pagamento ser confirmado. Nunca tenta a mesma mensalidade duas
 * vezes no mesmo dia (cobranca_automatica_log).
 */

define('CRON_RUN', true);
require_once dirname(__FILE__, 2) . '/config/app.php';
require_once dirname(__FILE__, 2) . '/config/database.php';
require_once dirname(__FILE__, 2) . '/config/mercadopago.php';

$pdo  = getDbConnection();
$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

$accessToken = mpAccessToken($pdo);

$st = $pdo->prepare("
    SELECT m.id AS mensalidade_id, m.referencia, m.tipo, m.descricao, m.valor, m.vencimento, m.status,
           a.id AS aluno_id, a.nome, a.mp_customer_id, a.mp_card_id
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
    LEFT JOIN cobranca_automatica_log cl
           ON cl.mensalidade_id = m.id AND cl.data_tentativa = ?
    WHERE m.status IN ('pendente', 'atrasado')
      AND DATE(m.vencimento) <= ?
      AND a.auto_pagamento = 1
      AND a.mp_customer_id IS NOT NULL
      AND a.mp_card_id IS NOT NULL
      AND cl.id IS NULL
");
$st->execute([$hoje, $hoje]);
$mensalidades = $st->fetchAll(PDO::FETCH_ASSOC);

$sucesso = 0;
$falha   = 0;

$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

foreach ($mensalidades as $m) {
    $valor  = (float) $m['valor'];
    $venc   = new DateTime($m['vencimento']);
    $hojeDt = new DateTime($hoje);

    if ($m['status'] === 'atrasado') {
        $dias  = (int) $venc->diff($hojeDt)->days;
        $multa = $valor * 0.05;
        $base  = $valor + $multa;
        $juros = $base * 0.005 * $dias;
        $total = round($base + $juros, 2);
    } else {
        $total = $valor;
    }

    $isAvulso = ($m['tipo'] ?? 'mensalidade') === 'avulso';
    if ($isAvulso) {
        $refLabel = $m['descricao'] ?? 'Cobrança extra';
    } else {
        [$refAno, $refMes] = explode('-', $m['referencia']);
        $refLabel = ($meses[$refMes] ?? $refMes) . '/' . $refAno;
    }

    $cardToken = mpGerarTokenCartaoSalvo($accessToken, $m['mp_card_id'], $m['mp_customer_id']);

    if (!$cardToken) {
        _logCobranca($pdo, $m['aluno_id'], $m['mensalidade_id'], $hoje, 'falha', 'Não foi possível gerar token do cartão salvo.');
        $falha++;
        continue;
    }

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
        $pdo->prepare("
            UPDATE mensalidades
            SET status = 'pago', data_pagamento = CURDATE(), mp_payment_id = ?, atualizado_em = NOW()
            WHERE id = ?
        ")->execute([$mpPaymentId, $m['mensalidade_id']]);

        $competencia = date('Y-m');
        $descLanc    = 'Mensalidade ' . $refLabel . ' — ' . $m['nome'] . ' (cobrança automática)';
        try {
            $pdo->prepare("
                INSERT IGNORE INTO lancamentos_financeiros
                    (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
                VALUES (?, CURDATE(), 'receita', 'mensalidade', ?, ?, 'auto', 'mensalidade', ?)
            ")->execute([$competencia, $descLanc, $total, $m['mensalidade_id']]);
        } catch (PDOException $e) {}

        _logCobranca($pdo, $m['aluno_id'], $m['mensalidade_id'], $hoje, 'sucesso', null, $mpPaymentId);
        $sucesso++;
    } else {
        $motivo = $body['status_detail'] ?? ($body['message'] ?? ('status: ' . $status));
        _logCobranca($pdo, $m['aluno_id'], $m['mensalidade_id'], $hoje, 'falha', $motivo, $mpPaymentId);
        $falha++;
    }
}

echo "Cobrança automática — sucesso: {$sucesso}, falha: {$falha}\n";

// ─── Helpers ──────────────────────────────────────────────────────────────────

function _logCobranca(PDO $pdo, int $alunoId, int $mensalidadeId, string $hoje, string $status, ?string $motivo = null, ?string $mpPaymentId = null): void {
    $pdo->prepare("
        INSERT IGNORE INTO cobranca_automatica_log (aluno_id, mensalidade_id, data_tentativa, status, motivo_falha, mp_payment_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$alunoId, $mensalidadeId, $hoje, $status, $motivo, $mpPaymentId]);
}
