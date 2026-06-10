<?php
/**
 * Notifica o responsável sobre o termo de responsabilidade.
 * Envia email + WhatsApp com o link do termo.
 * Usado em: add_aluno_teste.php e enviar_termo_responsavel.php
 *
 * @param array  $dados   ['responsavel_email', 'responsavel_nome', 'responsavel_celular', 'aluno_nome', 'turma_nome']
 * @param string $termoUrl URL pública do termo
 */
function notificarTermoResponsavel(array $dados, string $termoUrl, bool $soEmail = false): void {
    $responsavelEmail   = $dados['responsavel_email']   ?? '';
    $responsavelNome    = $dados['responsavel_nome']    ?? 'Responsável';
    $responsavelCelular = $dados['responsavel_celular'] ?? '';
    $nomeAluno          = $dados['aluno_nome']          ?? '';
    $turma              = $dados['turma_nome']          ?? '';

    $primeiroNome = explode(' ', trim($responsavelNome))[0];

    // ── E-mail ────────────────────────────────────────────────────────────────
    if ($responsavelEmail) {
        _enviarEmailTermo($responsavelEmail, $responsavelNome, $primeiroNome, $nomeAluno, $turma, $termoUrl);
    }

    // ── WhatsApp ──────────────────────────────────────────────────────────────
    if (!$soEmail && $responsavelCelular) {
        require_once __DIR__ . '/../whatsapp/zapi.php';

        $msg  = "Olá, *{$primeiroNome}*! 👋\n\n";
        $msg .= "Para que *{$nomeAluno}* participe da aula experimental na *MPG Academy*, ";
        $msg .= "precisamos da sua assinatura no *Termo de Autorização e Responsabilidade*.\n\n";
        $msg .= "Acesse o link abaixo para ler e assinar:\n";
        $msg .= "🔗 {$termoUrl}";

        sendWhatsApp(formatPhoneZapi($responsavelCelular), $msg);
    }
}

function _enviarEmailTermo(
    string $destEmail, string $destNome, string $primeiroNome,
    string $nomeAluno, string $turma, string $termoUrl
): void {
    $root = dirname(__FILE__, 3);

    if (!defined('APP_IS_LOCAL')) {
        require_once $root . '/config/app.php';
    }
    require_once $root . '/config/mail.php';

    $logoUrl          = appBaseUrl() . '/images/logo.png';
    $nomeAlunoHtml    = htmlspecialchars($nomeAluno,     ENT_QUOTES, 'UTF-8');
    $primeiroNomeHtml = htmlspecialchars($primeiroNome,  ENT_QUOTES, 'UTF-8');
    $turmaHtml        = htmlspecialchars($turma,         ENT_QUOTES, 'UTF-8');

    $body = '<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0d0d0f;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0d0d0f;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0"
       style="max-width:600px;background:#111113;border-radius:16px;overflow:hidden;border:1px solid #222;">
  <tr><td style="background:#0b0d0f;padding:24px 32px;border-bottom:3px solid #ffd500;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td><img src="' . $logoUrl . '" alt="MPG Academy" style="height:48px;width:auto;border:0;"></td>
        <td align="right" style="color:#ffd500;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Termo de Responsabilidade</td>
      </tr>
    </table>
  </td></tr>
  <tr><td style="padding:32px;">
    <p style="margin:0 0 16px;color:#e0e0e0;font-size:16px;">Olá, <strong style="color:#ffd500;">' . $primeiroNomeHtml . '</strong>!</p>
    <p style="margin:0 0 20px;color:#b0b0b0;font-size:14px;line-height:1.7;">
      A <strong style="color:#fff;">MPG Academy</strong> convidou <strong style="color:#fff;">' . $nomeAlunoHtml . '</strong>
      para uma aula experimental na turma <strong style="color:#fff;">' . $turmaHtml . '</strong>.
    </p>
    <p style="margin:0 0 24px;color:#b0b0b0;font-size:14px;line-height:1.7;">
      Por se tratar de um menor de idade, é necessária a assinatura do
      <strong style="color:#fff;">Termo de Autorização e Responsabilidade</strong>.
      Clique no botão abaixo para ler o documento e assinar eletronicamente.
    </p>
    <table cellpadding="0" cellspacing="0" border="0" style="margin:28px 0;">
      <tr>
        <td style="background:#ffd500;border-radius:10px;padding:14px 32px;">
          <a href="' . $termoUrl . '" style="color:#0d0d0f;font-size:15px;font-weight:700;text-decoration:none;display:block;">
            ✍ Ler e Assinar o Termo
          </a>
        </td>
      </tr>
    </table>
    <p style="margin:0 0 8px;color:#666;font-size:12px;">Ou acesse pelo link:</p>
    <a href="' . $termoUrl . '" style="color:#ffd500;font-size:12px;word-break:break-all;">' . $termoUrl . '</a>
    <div style="margin-top:28px;padding-top:20px;border-top:1px solid #222;">
      <p style="margin:0;color:#555;font-size:12px;">Após assinado, o documento ficará disponível neste mesmo link para consulta a qualquer momento.</p>
    </div>
  </td></tr>
  <tr><td style="background:#0b0d0f;padding:16px 32px;border-top:1px solid #222;">
    <p style="margin:0;color:#444;font-size:11px;text-align:center;">MPG Academy · Escola de Vôlei · São Paulo, SP</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>';

    if (defined('APP_IS_LOCAL') && APP_IS_LOCAL) {
        $dir = $root . '/storage/emails_teste';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $slug = date('Y-m-d_H-i-s') . '_termo_' . preg_replace('/[^a-z0-9]/', '_', strtolower($destEmail));
        file_put_contents($dir . '/' . $slug . '.html', $body);
        return;
    }

    $mailerPath = $root . '/vendor/autoload.php';
    if (!file_exists($mailerPath)) return;

    require_once $mailerPath;

    $mailCfg = getMpgMailConfig();
    $mail    = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $mailCfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailCfg['smtp_user'];
        $mail->Password   = $mailCfg['smtp_pass'];
        $mail->SMTPSecure = $mailCfg['smtp_enc'];
        $mail->Port       = $mailCfg['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($mailCfg['from_addr'], $mailCfg['from_name']);
        $mail->addAddress($destEmail, $destNome);
        $mail->isHTML(true);
        $mail->Subject = 'MPG Academy – Termo de Autorização para ' . $nomeAluno;
        $mail->Body    = $body;
        $mail->send();
    } catch (\Exception $e) {
        // falha silenciosa — o WhatsApp já foi enviado
    }
}
