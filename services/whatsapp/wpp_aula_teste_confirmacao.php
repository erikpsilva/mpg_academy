<?php

/**
 * Envia mensagem de confirmação de aula teste via WhatsApp.
 * Chamado diretamente após o INSERT em add_aluno_teste.php.
 *
 * @param array $aluno   ['nome', 'celular', 'is_menor', 'responsavel_nome', 'responsavel_celular']
 * @param array $turma   ['nome', 'rua', 'numero', 'bairro', 'complemento', 'cidade', 'estado']
 * @param string $dataFmt   Data formatada: "15 de junho de 2026 (segunda-feira)"
 * @param string $horarioFmt Horário: "das 08h00 às 09h30"
 * @param string $termoUrl  URL do termo (só para menores)
 */
function wppAulaTesteConfirmacao(array $aluno, array $turma, string $dataFmt, string $horarioFmt, string $termoUrl = ''): void {
    require_once __DIR__ . '/zapi.php';
    require_once dirname(__FILE__, 3) . '/config/app.php';

    $nomePrimeiro = explode(' ', trim($aluno['nome']))[0];
    $endereco     = _montaEndereco($turma);

    // Mensagem para o aluno
    if (!empty($aluno['celular'])) {
        $msg = "Olá, *{$nomePrimeiro}*! 🎾\n\n";
        $msg .= "Sua aula experimental na *MPG Academy* está confirmada!\n\n";
        $msg .= "📅 *Data:* {$dataFmt}\n";
        $msg .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msg .= "📍 *Local:* {$endereco}\n";
        $msg .= "\nQualquer dúvida é só chamar. Te esperamos! 😊";

        sendWhatsApp(formatPhoneZapi($aluno['celular']), $msg);
    }

    // Se for menor: notifica também o responsável e envia link do termo
    if (!empty($aluno['is_menor']) && !empty($aluno['responsavel_celular'])) {
        $nomeAluno = trim($aluno['nome']);
        $nomeResp  = explode(' ', trim($aluno['responsavel_nome'] ?? 'Responsável'))[0];

        $msgResp = "Olá, *{$nomeResp}*! 🎾\n\n";
        $msgResp .= "A aula experimental de *{$nomeAluno}* na *MPG Academy* está confirmada!\n\n";
        $msgResp .= "📅 *Data:* {$dataFmt}\n";
        $msgResp .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msgResp .= "📍 *Local:* {$endereco}\n";
        $msgResp .= "\nQualquer dúvida estamos à disposição!";

        sendWhatsApp(formatPhoneZapi($aluno['responsavel_celular']), $msgResp);

        // Envia link do termo separado para não poluir a mensagem principal
        if ($termoUrl) {
            $msgTermo = "Para seu filho(a) participar da MPG Academy, precisamos da sua assinatura no *Termo de Responsabilidade*.\n\n";
            $msgTermo .= "Acesse o link abaixo para ler e assinar:\n🔗 {$termoUrl}";
            sendWhatsApp(formatPhoneZapi($aluno['responsavel_celular']), $msgTermo);
        }
    }
}

function _montaEndereco(array $turma): string {
    if (empty($turma['rua'])) return '';
    $end = $turma['rua'] . ', ' . ($turma['numero'] ?? 's/n');
    if (!empty($turma['complemento'])) $end .= ' - ' . $turma['complemento'];
    $end .= ' - ' . ($turma['bairro'] ?? '') . ', ' . ($turma['cidade'] ?? '') . '/' . ($turma['estado'] ?? '');
    return $end;
}
