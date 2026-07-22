<?php

/**
 * Envia aviso de cobrança de mensalidade via WhatsApp, disparado manualmente pelo admin
 * (botão individual "Cobrar" ou "Avisar Todos" na tela Mensalidades dos Alunos).
 * A mensagem varia conforme a proximidade do vencimento: dias restantes, vence hoje, ou atrasada.
 *
 * @param array $m aluno_id, mensalidade_id, nome, celular, valor, vencimento (Y-m-d), referencia (YYYY-MM)
 */
function wppMensalidadeCobranca(array $m): bool {
    require_once __DIR__ . '/zapi.php';

    if (empty($m['celular'])) return false;

    $MESES = ['','janeiro','fevereiro','março','abril','maio','junho','julho',
              'agosto','setembro','outubro','novembro','dezembro'];

    $nomePrimeiro = explode(' ', trim($m['nome']))[0];
    $valorFmt     = 'R$ ' . number_format((float) $m['valor'], 2, ',', '.');
    $vencDt       = new DateTime(substr($m['vencimento'], 0, 10));
    $vencFmt      = $vencDt->format('d/m/Y');
    [$refAno, $refMes] = explode('-', $m['referencia']);
    $mesRefFmt    = $MESES[(int) $refMes] . '/' . $refAno;

    $hoje = new DateTime(date('Y-m-d'));
    $diff = (int) $hoje->diff($vencDt)->format('%r%a'); // positivo = dias até vencer; negativo = dias em atraso

    if ($diff > 0) {
        $msg  = "Olá, *{$nomePrimeiro}*! 👋\n\n";
        $msg .= "Passando para lembrar que sua mensalidade da *MPG Academy* referente a *{$mesRefFmt}* vence em *{$diff} dia" . ($diff > 1 ? 's' : '') . "*, no dia *{$vencFmt}*.\n\n";
        $msg .= "💰 Valor: *{$valorFmt}*\n\n";
        $msg .= "Para manter tudo em dia, garanta o pagamento até a data de vencimento. Qualquer dúvida, estamos à disposição! 😊";
    } elseif ($diff === 0) {
        $msg  = "Olá, *{$nomePrimeiro}*! 🔔\n\n";
        $msg .= "Sua mensalidade da *MPG Academy* referente a *{$mesRefFmt}* vence *hoje*, dia *{$vencFmt}*.\n\n";
        $msg .= "💰 Valor: *{$valorFmt}*\n\n";
        $msg .= "Para evitar multa e juros por atraso, realize o pagamento ainda hoje. Estamos à disposição para ajudar no que precisar! 🙏";
    } else {
        $diasAtraso = abs($diff);
        $msg  = "Olá, *{$nomePrimeiro}*! ⚠️\n\n";
        $msg .= "Identificamos que sua mensalidade da *MPG Academy* referente a *{$mesRefFmt}* está em *atraso há {$diasAtraso} dia" . ($diasAtraso > 1 ? 's' : '') . "* (vencimento em {$vencFmt}).\n\n";
        $msg .= "💰 Valor: *{$valorFmt}*\n\n";
        $msg .= "Pedimos a gentileza de regularizar o pagamento o quanto antes, para evitar a suspensão das atividades. Se você já efetuou o pagamento, por favor desconsidere esta mensagem. Estamos à disposição! 😊";
    }

    return sendWhatsApp(formatPhoneZapi($m['celular']), $msg);
}
