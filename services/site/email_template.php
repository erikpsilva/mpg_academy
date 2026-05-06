<?php

require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/mail.php';

const MPG_MAIL_FROM      = 'contato@mpgacademy.com.br';
const MPG_INSTAGRAM_URL  = 'https://www.instagram.com/mpgacademy/';
const MPG_WHATSAPP_URL   = 'https://wa.me/55119972330097';
const MPG_PHONE_LABEL    = '11 997233-0097';

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
