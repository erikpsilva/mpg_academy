<?php

/**
 * Envia lembrete de aula experimental via WhatsApp (3 dias antes ou no dia da aula).
 * Extraído dos crons (cron/wpp_lembrete_3dias.php, cron/wpp_lembrete_dia_aula.php)
 * pra ser reaproveitado também pelo disparo manual (admin/services/disparar_lembrete_teste.php).
 *
 * @param array  $r    Linha do SELECT: nome, celular, is_menor, responsavel_nome,
 *                      responsavel_celular, data_agendada, hora_inicio, hora_fim,
 *                      rua, numero, complemento, bairro, cidade, estado
 * @param string $tipo '3dias' ou 'dia_aula'
 */
function wppAulaTesteLembrete(array $r, string $tipo): void {
    require_once __DIR__ . '/zapi.php';

    $endereco     = _montaEnderecoLembrete($r);
    $nomePrimeiro = explode(' ', trim($r['nome']))[0];
    $nomeAluno    = trim($r['nome']);
    $nomeResp     = explode(' ', trim($r['responsavel_nome'] ?? 'Responsável'))[0];

    if ($tipo === '3dias') {
        $meses      = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
        $diasSemana = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
        $dt         = new DateTime($r['data_agendada']);
        $dataFmt    = $dt->format('d') . ' de ' . $meses[(int) $dt->format('n') - 1] . ' de ' . $dt->format('Y')
                    . ' (' . $diasSemana[(int) $dt->format('w')] . ')';
        $horarioFmt = $r['hora_inicio']
            ? 'das ' . substr($r['hora_inicio'], 0, 5) . 'h às ' . substr($r['hora_fim'], 0, 5) . 'h'
            : 'a confirmar';

        $msg  = "Olá, *{$nomePrimeiro}*! 🎾\n\n";
        $msg .= "Lembrando que sua aula experimental na *MPG Academy* é em 3 dias!\n\n";
        $msg .= "📅 *Data:* {$dataFmt}\n";
        $msg .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msg .= "📍 *Local:* {$endereco}\n";
        $msg .= "\nQualquer dúvida é só chamar. Te esperamos!";

        $msgResp  = "Olá, *{$nomeResp}*! 🎾\n\n";
        $msgResp .= "Lembrando que a aula experimental de *{$nomeAluno}* na *MPG Academy* é em 3 dias!\n\n";
        $msgResp .= "📅 *Data:* {$dataFmt}\n";
        $msgResp .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msgResp .= "📍 *Local:* {$endereco}";
    } else {
        $horarioFmt = $r['hora_inicio'] ? 'às ' . substr($r['hora_inicio'], 0, 5) . 'h' : 'hoje';

        $msg  = "Olá, *{$nomePrimeiro}*! 🎾\n\n";
        $msg .= "Hoje é o dia da sua aula experimental na *MPG Academy*!\n\n";
        $msg .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msg .= "📍 *Local:* {$endereco}\n";
        $msg .= "\nTe esperamos! 😊";

        $msgResp  = "Olá, *{$nomeResp}*! 🎾\n\n";
        $msgResp .= "Hoje é o dia da aula experimental de *{$nomeAluno}* na *MPG Academy*!\n\n";
        $msgResp .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msgResp .= "📍 *Local:* {$endereco}";
    }

    if (!empty($r['celular'])) {
        sendWhatsApp(formatPhoneZapi($r['celular']), $msg);
    }

    if (!empty($r['is_menor']) && !empty($r['responsavel_celular'])) {
        sendWhatsApp(formatPhoneZapi($r['responsavel_celular']), $msgResp);
    }
}

function _montaEnderecoLembrete(array $r): string {
    if (empty($r['rua'])) return '';
    $end = $r['rua'] . ', ' . ($r['numero'] ?? 's/n');
    if (!empty($r['complemento'])) $end .= ' - ' . $r['complemento'];
    $end .= ' - ' . ($r['bairro'] ?? '') . ', ' . ($r['cidade'] ?? '') . '/' . ($r['estado'] ?? '');
    return $end;
}
