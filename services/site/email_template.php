<?php

require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/mail.php';

const MPG_MAIL_FROM      = 'contato@mpgacademy.com.br';
const MPG_INSTAGRAM_URL  = 'https://www.instagram.com/mpgacademy/';
const MPG_WHATSAPP_URL   = 'https://wa.me/5511972330097';
const MPG_PHONE_LABEL    = '11 97233-0097';

function mpgLogoUrl(): string {
    return (APP_IS_LOCAL ? appBaseUrl() : 'https://www.mpgacademy.com.br') . '/images/logo.png';
}

function buildMpgSignupEmail(string $nome): string {
    $safeName = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');

    return '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro confirmado - MPG Academy</title>
</head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;color:#ffffff;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#050505;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#111113;border:1px solid #262626;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:34px 32px 20px;text-align:center;background:#050505;">
                            <img src="' . mpgLogoUrl() . '" alt="MPG Academy" width="240" style="display:block;margin:0 auto;max-width:100%;height:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 32px 12px;">
                            <p style="margin:0 0 12px;color:#ffd500;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Inscricao confirmada</p>
                            <h1 style="margin:0 0 18px;color:#ffffff;font-size:30px;line-height:1.15;font-weight:900;">Obrigado pelo cadastro, ' . $safeName . '!</h1>
                            <p style="margin:0 0 18px;color:#d4d4d8;font-size:16px;line-height:1.7;">
                                Recebemos sua inscricao na lista de interesse da MPG Academy. Em breve entraremos em contato com novidades sobre turmas, horarios, quadras parceiras e a abertura oficial das inscricoes.
                            </p>
                            <p style="margin:0;color:#d4d4d8;font-size:16px;line-height:1.7;">
                                Enquanto isso, acompanhe nossos canais e fale com a gente se tiver alguma duvida.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 32px 34px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding:10px 0;">
                                        <a href="' . MPG_INSTAGRAM_URL . '" style="display:block;background:#ffd500;color:#050505;text-decoration:none;text-align:center;font-size:15px;font-weight:900;text-transform:uppercase;border-radius:8px;padding:15px 18px;">Seguir no Instagram</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0 0;">
                                        <a href="' . MPG_WHATSAPP_URL . '" style="display:block;background:#1f1f23;color:#ffffff;text-decoration:none;text-align:center;font-size:15px;font-weight:900;text-transform:uppercase;border-radius:8px;padding:15px 18px;border:1px solid #34343a;">Falar no WhatsApp: ' . MPG_PHONE_LABEL . '</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#0a0a0b;border-top:1px solid #262626;text-align:center;">
                            <p style="margin:0;color:#85858c;font-size:13px;line-height:1.6;">
                                MPG Academy - Escola de Volei<br>
                                Instagram: @mpgacademy | WhatsApp: ' . MPG_PHONE_LABEL . '
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

function sendMpgSignupConfirmation(string $to, string $nome): bool {
    $config  = getMpgMailConfig();
    $subject = 'Cadastro confirmado - MPG Academy';
    $body    = buildMpgSignupEmail($nome);

    // Modo local: salva em arquivo HTML em vez de enviar
    $host    = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
    if ($isLocal) {
        $dir = dirname(__FILE__, 3) . '/storage/emails_teste';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = $dir . '/' . date('Y-m-d_H-i-s') . '_signup_' . preg_replace('/[^a-z0-9]/i', '_', $to) . '.html';
        $meta  = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px 16px;font-family:monospace;font-size:12px;margin-bottom:16px;">'
               . '<strong>[TESTE LOCAL]</strong> Para: <b>' . htmlspecialchars($to) . '</b> | '
               . 'Assunto: <b>' . htmlspecialchars($subject) . '</b> | '
               . date('d/m/Y H:i:s') . '</div>';
        file_put_contents($fname, $meta . $body);
        error_log('[mpg-email-local] Salvo em: ' . $fname);
        return true;
    }

    // SMTP via PHPMailer (quando ativo e configurado)
    if ($config['smtp_active'] && $config['smtp_host'] && $config['smtp_user'] && $config['smtp_pass']) {
        $autoload = dirname(__FILE__, 3) . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            error_log('[mpg-email] vendor/autoload.php nao encontrado — rode composer install');
            return _mpgMailNative($to, $subject, $body, $config);
        }
        require_once $autoload;

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host     = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            $mail->Port     = (int) $config['smtp_port'];

            $enc = strtolower($config['smtp_enc'] ?? 'tls');
            if ($enc === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure  = false;
            }

            $mail->setFrom($config['from_addr'], $config['from_name']);
            $mail->addReplyTo($config['from_addr'], $config['from_name']);
            $mail->addAddress($to);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('[mpg-email] PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback: mail() nativo do PHP
    return _mpgMailNative($to, $subject, $body, $config);
}

// ── Email: confirmação de aula experimental ───────────────────────────────────

function buildMpgTesteEmail(string $nome, string $turma, string $dataFormatada, string $horario, string $endereco = ''): string {
    $safeName     = htmlspecialchars($nome,          ENT_QUOTES, 'UTF-8');
    $safeTurma    = htmlspecialchars($turma,         ENT_QUOTES, 'UTF-8');
    $safeData     = htmlspecialchars($dataFormatada, ENT_QUOTES, 'UTF-8');
    $safeHora     = htmlspecialchars($horario,       ENT_QUOTES, 'UTF-8');
    $safeEndereco = htmlspecialchars($endereco ?: 'a confirmar', ENT_QUOTES, 'UTF-8');

    return '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aula experimental confirmada - MPG Academy</title>
</head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;color:#ffffff;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#050505;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#111113;border:1px solid #262626;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:34px 32px 20px;text-align:center;background:#050505;">
                            <img src="' . mpgLogoUrl() . '" alt="MPG Academy" width="240" style="display:block;margin:0 auto;max-width:100%;height:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 32px 12px;">
                            <p style="margin:0 0 12px;color:#ffd500;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Aula experimental</p>
                            <h1 style="margin:0 0 18px;color:#ffffff;font-size:28px;line-height:1.15;font-weight:900;">Sua aula esta confirmada, ' . $safeName . '!</h1>
                            <p style="margin:0 0 24px;color:#d4d4d8;font-size:16px;line-height:1.7;">
                                Ficamos felizes em te receber na MPG Academy. Sua aula experimental esta agendada com os detalhes abaixo:
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#1a1a1e;border:1px solid #2e2e34;border-radius:8px;overflow:hidden;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:18px 22px;border-bottom:1px solid #2e2e34;">
                                        <p style="margin:0 0 4px;color:#ffd500;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;">Turma</p>
                                        <p style="margin:0;color:#ffffff;font-size:16px;font-weight:700;">' . $safeTurma . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 22px;border-bottom:1px solid #2e2e34;">
                                        <p style="margin:0 0 4px;color:#ffd500;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;">Data</p>
                                        <p style="margin:0;color:#ffffff;font-size:16px;font-weight:700;">' . $safeData . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 22px;border-bottom:1px solid #2e2e34;">
                                        <p style="margin:0 0 4px;color:#ffd500;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;">Horario</p>
                                        <p style="margin:0;color:#ffffff;font-size:16px;font-weight:700;">' . $safeHora . '</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 22px;">
                                        <p style="margin:0 0 4px;color:#ffd500;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;">Local</p>
                                        <p style="margin:0;color:#ffffff;font-size:16px;font-weight:700;">' . $safeEndereco . '</p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;color:#d4d4d8;font-size:15px;line-height:1.7;">
                                Lembre-se de chegar alguns minutos antes. Caso precise remarcar ou tenha alguma duvida, fale com a gente pelo WhatsApp.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 32px 34px;">
                            <a href="' . MPG_WHATSAPP_URL . '" style="display:block;background:#ffd500;color:#050505;text-decoration:none;text-align:center;font-size:15px;font-weight:900;text-transform:uppercase;border-radius:8px;padding:15px 18px;">Falar no WhatsApp: ' . MPG_PHONE_LABEL . '</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#0a0a0b;border-top:1px solid #262626;text-align:center;">
                            <p style="margin:0;color:#85858c;font-size:13px;line-height:1.6;">
                                MPG Academy - Escola de Volei<br>
                                Instagram: @mpgacademy | WhatsApp: ' . MPG_PHONE_LABEL . '
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

function sendMpgTesteConfirmation(string $to, string $nome, string $turma, string $dataFormatada, string $horario, string $endereco = ''): bool {
    $config  = getMpgMailConfig();
    $subject = 'Aula experimental confirmada - MPG Academy';
    $body    = buildMpgTesteEmail($nome, $turma, $dataFormatada, $horario, $endereco);

    $host    = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
    if ($isLocal) {
        $dir = dirname(__FILE__, 3) . '/storage/emails_teste';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = $dir . '/' . date('Y-m-d_H-i-s') . '_teste_' . preg_replace('/[^a-z0-9]/i', '_', $to) . '.html';
        $meta  = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px 16px;font-family:monospace;font-size:12px;margin-bottom:16px;">'
               . '<strong>[TESTE LOCAL]</strong> Para: <b>' . htmlspecialchars($to) . '</b> | '
               . 'Assunto: <b>' . htmlspecialchars($subject) . '</b> | '
               . date('d/m/Y H:i:s') . '</div>';
        file_put_contents($fname, $meta . $body);
        error_log('[mpg-email-local] Salvo em: ' . $fname);
        return true;
    }

    if ($config['smtp_active'] && $config['smtp_host'] && $config['smtp_user'] && $config['smtp_pass']) {
        $autoload = dirname(__FILE__, 3) . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            error_log('[mpg-email] vendor/autoload.php nao encontrado — rode composer install');
            return _mpgMailNative($to, $subject, $body, $config);
        }
        require_once $autoload;

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host     = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            $mail->Port     = (int) $config['smtp_port'];

            $enc = strtolower($config['smtp_enc'] ?? 'tls');
            if ($enc === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure  = false;
            }

            $mail->setFrom($config['from_addr'], $config['from_name']);
            $mail->addReplyTo($config['from_addr'], $config['from_name']);
            $mail->addAddress($to);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('[mpg-email] PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    return _mpgMailNative($to, $subject, $body, $config);
}

// ── Email: notificação de mensalidade em atraso (para o aluno) ────────────────

function buildMpgAtrasoAlunoEmail(string $nome, string $refLabel, int $dias, int $diasParaBloqueio): string
{
    $safeName  = htmlspecialchars($nome,     ENT_QUOTES, 'UTF-8');
    $safeRef   = htmlspecialchars($refLabel, ENT_QUOTES, 'UTF-8');
    $urgente   = $diasParaBloqueio <= 5;
    $corAlerta = $urgente ? '#ff4444' : '#ffd500';
    $msgUrgencia = $urgente
        ? "⚠️ <strong>ATENÇÃO:</strong> faltam apenas <strong>{$diasParaBloqueio} dia(s)</strong> para o bloqueio do seu acesso às aulas."
        : "Você tem <strong>{$diasParaBloqueio} dias</strong> para regularizar antes do bloqueio do acesso às aulas.";

    return '<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mensalidade em atraso - MPG Academy</title></head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;color:#fff;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#050505;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#111113;border:1px solid #262626;border-radius:8px;overflow:hidden;">
  <tr><td style="padding:34px 32px 20px;text-align:center;background:#050505;">
    <img src="' . mpgLogoUrl() . '" alt="MPG Academy" width="220" style="display:block;margin:0 auto;max-width:100%;height:auto;">
  </td></tr>
  <tr><td style="padding:8px 0;background:' . $corAlerta . ';text-align:center;">
    <p style="margin:0;color:#050505;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:1px;">Mensalidade em Atraso</p>
  </td></tr>
  <tr><td style="padding:32px 32px 16px;">
    <h1 style="margin:0 0 16px;color:#fff;font-size:26px;font-weight:900;line-height:1.2;">Ol&aacute;, ' . $safeName . '</h1>
    <p style="margin:0 0 18px;color:#d4d4d8;font-size:15px;line-height:1.7;">
      Identificamos que sua mensalidade referente a <strong style="color:#fff;">' . $safeRef . '</strong>
      est&aacute; em atraso h&aacute; <strong style="color:' . $corAlerta . ';">' . $dias . ' dia(s)</strong>.
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#1a1a1e;border:2px solid ' . $corAlerta . ';border-radius:8px;margin-bottom:22px;">
      <tr><td style="padding:18px 22px;">
        <p style="margin:0;color:#d4d4d8;font-size:14px;line-height:1.7;">' . $msgUrgencia . '</p>
      </td></tr>
    </table>
    <p style="margin:0 0 8px;color:#d4d4d8;font-size:15px;line-height:1.7;">
      Para evitar o bloqueio, acesse sua &aacute;rea do aluno e regularize sua situa&ccedil;&atilde;o o quanto antes.
    </p>
  </td></tr>
  <tr><td style="padding:4px 32px 32px;">
    <a href="' . appBaseUrl() . '/mensalidades" style="display:block;background:#ffd500;color:#050505;text-decoration:none;text-align:center;font-size:15px;font-weight:900;text-transform:uppercase;border-radius:8px;padding:15px 18px;">Pagar mensalidade agora</a>
  </td></tr>
  <tr><td style="padding:12px 32px 28px;">
    <a href="' . MPG_WHATSAPP_URL . '" style="display:block;background:#1f1f23;color:#fff;text-decoration:none;text-align:center;font-size:15px;font-weight:900;text-transform:uppercase;border-radius:8px;padding:15px 18px;border:1px solid #34343a;">D&uacute;vidas? Falar no WhatsApp: ' . MPG_PHONE_LABEL . '</a>
  </td></tr>
  <tr><td style="padding:20px 32px;background:#0a0a0b;border-top:1px solid #262626;text-align:center;">
    <p style="margin:0;color:#85858c;font-size:13px;line-height:1.6;">MPG Academy — Escola de Volei<br>Instagram: @mpgacademy | WhatsApp: ' . MPG_PHONE_LABEL . '</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';
}

function buildMpgAtrasoAdminEmail(string $alunoNome, string $alunoEmail, string $refLabel, int $dias, int $diasParaBloqueio): string
{
    $safeName  = htmlspecialchars($alunoNome,  ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($alunoEmail, ENT_QUOTES, 'UTF-8');
    $safeRef   = htmlspecialchars($refLabel,   ENT_QUOTES, 'UTF-8');
    $cor = $diasParaBloqueio <= 5 ? '#ff4444' : '#ffd500';

    return '<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><title>Aluno em atraso - MPG Academy</title></head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;color:#fff;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#050505;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#111113;border:1px solid #262626;border-radius:8px;overflow:hidden;">
  <tr><td style="padding:28px 32px 20px;text-align:center;background:#050505;">
    <img src="' . mpgLogoUrl() . '" alt="MPG Academy" width="200" style="display:block;margin:0 auto;max-width:100%;height:auto;">
  </td></tr>
  <tr><td style="padding:8px 0;background:' . $cor . ';text-align:center;">
    <p style="margin:0;color:#050505;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:1px;">Aviso Interno — Aluno em Atraso</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="margin:0 0 18px;color:#d4d4d8;font-size:15px;line-height:1.7;">
      O aluno abaixo est&aacute; com mensalidade em atraso e foi notificado por e-mail.
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#1a1a1e;border:1px solid #2e2e34;border-radius:8px;margin-bottom:22px;">
      <tr><td style="padding:14px 20px;border-bottom:1px solid #2e2e34;">
        <p style="margin:0 0 3px;color:' . $cor . ';font-size:11px;font-weight:700;text-transform:uppercase;">Aluno</p>
        <p style="margin:0;color:#fff;font-size:16px;font-weight:700;">' . $safeName . '</p>
      </td></tr>
      <tr><td style="padding:14px 20px;border-bottom:1px solid #2e2e34;">
        <p style="margin:0 0 3px;color:' . $cor . ';font-size:11px;font-weight:700;text-transform:uppercase;">E-mail</p>
        <p style="margin:0;color:#fff;font-size:15px;">' . $safeEmail . '</p>
      </td></tr>
      <tr><td style="padding:14px 20px;border-bottom:1px solid #2e2e34;">
        <p style="margin:0 0 3px;color:' . $cor . ';font-size:11px;font-weight:700;text-transform:uppercase;">Mensalidade</p>
        <p style="margin:0;color:#fff;font-size:15px;">' . $safeRef . '</p>
      </td></tr>
      <tr><td style="padding:14px 20px;">
        <p style="margin:0 0 3px;color:' . $cor . ';font-size:11px;font-weight:700;text-transform:uppercase;">Dias em atraso</p>
        <p style="margin:0;color:' . $cor . ';font-size:18px;font-weight:900;">' . $dias . ' dia(s) — bloqueio em ' . $diasParaBloqueio . ' dia(s)</p>
      </td></tr>
    </table>
    <a href="' . appBaseUrl() . '/admin/alunos" style="display:block;background:#ffd500;color:#050505;text-decoration:none;text-align:center;font-size:14px;font-weight:900;text-transform:uppercase;border-radius:8px;padding:13px 18px;">Ver painel de alunos</a>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#0a0a0b;border-top:1px solid #262626;text-align:center;">
    <p style="margin:0;color:#85858c;font-size:12px;">MPG Academy — Notifica&ccedil;&atilde;o interna autom&aacute;tica</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';
}

function mpgEnviarEmail(string $to, string $toName, string $subject, string $body): bool
{
    $config  = getMpgMailConfig();
    $isLocal = APP_IS_LOCAL;

    if ($isLocal) {
        $dir   = dirname(__FILE__, 3) . '/storage/emails_teste';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = $dir . '/' . date('Y-m-d_H-i-s') . '_' . preg_replace('/[^a-z0-9]/i', '_', $to) . '.html';
        $meta  = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px 16px;font-family:monospace;font-size:12px;margin-bottom:16px;">'
               . '<strong>[TESTE LOCAL]</strong> Para: <b>' . htmlspecialchars($to) . '</b> | '
               . 'Assunto: <b>' . htmlspecialchars($subject) . '</b> | ' . date('d/m/Y H:i:s') . '</div>';
        file_put_contents($fname, $meta . $body);
        return true;
    }

    if ($config['smtp_active'] && $config['smtp_host'] && $config['smtp_user'] && $config['smtp_pass']) {
        $autoload = dirname(__FILE__, 3) . '/vendor/autoload.php';
        if (!file_exists($autoload)) return _mpgMailNative($to, $subject, $body, $config);
        require_once $autoload;
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host     = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            $mail->Port     = (int) $config['smtp_port'];
            $enc = strtolower($config['smtp_enc'] ?? 'tls');
            if ($enc === 'ssl')       { $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; }
            elseif ($enc === 'tls')   { $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
            else                      { $mail->SMTPAutoTLS = false; $mail->SMTPSecure = false; }
            $mail->setFrom($config['from_addr'], $config['from_name']);
            $mail->addReplyTo($config['from_addr'], $config['from_name']);
            $mail->addAddress($to, $toName);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('[mpg-email] PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }
    return _mpgMailNative($to, $subject, $body, $config);
}

function mpgEnviarNotificacaoAtraso(PDO $pdo, array $aluno, array $mens): bool
{
    $dias            = (int) $mens['dias_atraso'];
    $diasParaBloqueio = max(0, 30 - $dias);
    $refLabel        = $mens['ref_label'];

    // Email para o aluno
    $bodyAluno = buildMpgAtrasoAlunoEmail($aluno['nome'], $refLabel, $dias, $diasParaBloqueio);
    mpgEnviarEmail($aluno['email'], $aluno['nome'], 'Mensalidade em atraso - MPG Academy', $bodyAluno);

    // Email para o grupo de notificação
    $bodyAdmin = buildMpgAtrasoAdminEmail($aluno['nome'], $aluno['email'], $refLabel, $dias, $diasParaBloqueio);
    $stEmails  = $pdo->query("SELECT email, nome FROM emails_notificacao WHERE ativo = 1");
    foreach ($stEmails->fetchAll() as $en) {
        mpgEnviarEmail($en['email'], $en['nome'] ?: 'MPG Admin', '[MPG Academy] Aluno com mensalidade em atraso', $bodyAdmin);
    }

    // Registra no log
    $stLog = $pdo->prepare("
        INSERT IGNORE INTO notificacoes_log (aluno_id, mensalidade_id, tipo)
        VALUES (?, ?, 'atraso_25dias')
    ");
    $stLog->execute([$aluno['id'], $mens['id']]);

    return true;
}

// ── Email: _mpgMailNative (fallback) ──────────────────────────────────────────

function _mpgMailNative(string $to, string $subject, string $body, array $config): bool {
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $config['from_name'] . ' <' . $config['from_addr'] . '>',
        'Reply-To: ' . $config['from_name'] . ' <' . $config['from_addr'] . '>',
        'X-Mailer: PHP/' . phpversion(),
    ]);

    error_clear_last();
    $ok = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    if (!$ok) {
        $err = error_get_last();
        error_log('[mpg-email] mail() falhou para ' . $to . ': ' . ($err['message'] ?? 'sem detalhe'));
    }
    return $ok;
}
