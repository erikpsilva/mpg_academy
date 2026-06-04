<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$nome     = trim($_POST['nome']     ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$mensagem = trim($_POST['mensagem'] ?? '');

if ($nome === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Nome e e-mail são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/mail.php';

$cadastroUrl = appBaseUrl() . '/cadastro';
$primeiroNome = explode(' ', $nome)[0];

// ── Monta o corpo do e-mail ───────────────────────────────────────────────────
$msgPersonalizada = '';
if ($mensagem !== '') {
    $msgPersonalizada = '
    <tr>
        <td style="padding:0 32px 24px;">
            <div style="background:#1a1a1e;border:1px solid #2e2e34;border-left:3px solid #ffd500;border-radius:8px;padding:16px 20px;">
                <p style="margin:0;color:#d4d4d8;font-size:15px;line-height:1.6;">'
                    . nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'))
                . '</p>
            </div>
        </td>
    </tr>';
}

$logoUrl = appBaseUrl() . '/images/logo.png';

$body = '<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Convite de Cadastro - MPG Academy</title></head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;color:#fff;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#050505;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#111113;border:1px solid #262626;border-radius:8px;overflow:hidden;">

    <tr>
        <td style="padding:34px 32px 20px;text-align:center;background:#050505;">
            <img src="' . $logoUrl . '" alt="MPG Academy" width="220" style="display:block;margin:0 auto;max-width:100%;height:auto;">
        </td>
    </tr>

    <tr>
        <td style="padding:8px 0;background:#ffd500;text-align:center;">
            <p style="margin:0;color:#050505;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:1px;">Convite de Cadastro</p>
        </td>
    </tr>

    <tr>
        <td style="padding:32px 32px 12px;">
            <h1 style="margin:0 0 16px;color:#fff;font-size:26px;font-weight:900;line-height:1.2;">
                Olá, ' . htmlspecialchars($primeiroNome, ENT_QUOTES, 'UTF-8') . '!
            </h1>
            <p style="margin:0 0 18px;color:#d4d4d8;font-size:15px;line-height:1.7;">
                Você foi convidado(a) a criar sua conta na <strong style="color:#ffd500;">MPG Academy</strong> — sua escola de vôlei adulto na Zona Norte de São Paulo.
            </p>
            <p style="margin:0;color:#d4d4d8;font-size:15px;line-height:1.7;">
                Clique no botão abaixo para fazer seu cadastro e acessar a área do aluno com sua agenda de treinos, mensalidades e comunicados.
            </p>
        </td>
    </tr>

    ' . $msgPersonalizada . '

    <tr>
        <td style="padding:8px 32px 32px;">
            <a href="' . $cadastroUrl . '" style="display:block;background:#ffd500;color:#050505;text-decoration:none;text-align:center;font-size:16px;font-weight:900;text-transform:uppercase;border-radius:8px;padding:18px 18px;">
                Fazer meu cadastro →
            </a>
        </td>
    </tr>

    <tr>
        <td style="padding:0 32px 28px;">
            <p style="margin:0;color:#555;font-size:12px;line-height:1.6;word-break:break-all;">
                Ou acesse diretamente: <a href="' . $cadastroUrl . '" style="color:#ffd500;">' . $cadastroUrl . '</a>
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:20px 32px;background:#0a0a0b;border-top:1px solid #262626;text-align:center;">
            <p style="margin:0;color:#85858c;font-size:12px;line-height:1.6;">
                MPG Academy — Escola de Vôlei &bull; Zona Norte, São Paulo/SP<br>
                Instagram: @mpgacademy &bull; WhatsApp: (11) 97233-0097
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body></html>';

// ── Envia o e-mail ────────────────────────────────────────────────────────────
$config  = getMpgMailConfig();
$subject = 'Seu cadastro na MPG Academy está esperando por você!';

$isLocal = APP_IS_LOCAL;

if ($isLocal) {
    // Local: salva em arquivo HTML
    $dir   = dirname(__FILE__, 3) . '/storage/emails_teste';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = $dir . '/' . date('Y-m-d_H-i-s') . '_cadastro_' . preg_replace('/[^a-z0-9]/i', '_', $email) . '.html';
    $meta  = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px 16px;font-family:monospace;font-size:12px;margin-bottom:16px;">'
           . '<strong>[TESTE LOCAL]</strong> Para: <b>' . htmlspecialchars($email) . '</b> | '
           . 'Nome: <b>' . htmlspecialchars($nome) . '</b> | '
           . date('d/m/Y H:i:s') . '</div>';
    file_put_contents($fname, $meta . $body);
    echo json_encode(['success' => true, 'message' => "E-mail simulado localmente para {$email}. Arquivo salvo em storage/emails_teste."]);
    exit;
}

// Produção: envia via SMTP
if ($config['smtp_active'] && $config['smtp_host'] && $config['smtp_user'] && $config['smtp_pass']) {
    $autoload = dirname(__FILE__, 3) . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        echo json_encode(['success' => false, 'message' => 'PHPMailer não encontrado.']);
        exit;
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
        if ($enc === 'ssl')       { $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; }
        elseif ($enc === 'tls')   { $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
        else                      { $mail->SMTPAutoTLS = false; $mail->SMTPSecure = false; }

        $mail->setFrom($config['from_addr'], $config['from_name']);
        $mail->addReplyTo($config['from_addr'], $config['from_name']);
        $mail->addAddress($email, $nome);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();

        echo json_encode(['success' => true, 'message' => "E-mail de cadastro enviado com sucesso para {$email}!"]);
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'SMTP não configurado.']);
