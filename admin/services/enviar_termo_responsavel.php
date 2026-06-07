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

$aulaId = (int)($_POST['aula_id'] ?? 0);
if (!$aulaId) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/mail.php';

$pdo = getDbConnection();

// Busca dados da aula + aluno + responsável
$stmt = $pdo->prepare("
    SELECT ae.id, at.nome, at.responsavel_nome, at.responsavel_email, t.nome AS turma_nome
    FROM aulas_experimentais ae
    JOIN alunos_teste at ON at.id = ae.aluno_teste_id
    JOIN turmas t ON t.id = ae.turma_id
    WHERE ae.id = ? LIMIT 1
");
$stmt->execute([$aulaId]);
$aula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aula) {
    echo json_encode(['success' => false, 'message' => 'Aula não encontrada.']);
    exit;
}
if (empty($aula['responsavel_email'])) {
    echo json_encode(['success' => false, 'message' => 'Responsável sem e-mail cadastrado.']);
    exit;
}

// Garante que existe um registro de termo com token
$stmtT = $pdo->prepare("SELECT id, token FROM termo_assinaturas WHERE aula_experimental_id = ? LIMIT 1");
$stmtT->execute([$aulaId]);
$termo = $stmtT->fetch(PDO::FETCH_ASSOC);

if (!$termo) {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO termo_assinaturas (aula_experimental_id, token) VALUES (?, ?)")
        ->execute([$aulaId, $token]);
} else {
    $token = $termo['token'];
}

$termoUrl   = appBaseUrl() . '/termo?token=' . $token;
$logoUrl    = appBaseUrl() . '/images/logo.png';
$nomeAluno  = htmlspecialchars($aula['nome'], ENT_QUOTES, 'UTF-8');
$nomeResp   = htmlspecialchars($aula['responsavel_nome'] ?? 'Responsável', ENT_QUOTES, 'UTF-8');
$primeiroNomeResp = htmlspecialchars(explode(' ', $aula['responsavel_nome'] ?? 'Responsável')[0], ENT_QUOTES, 'UTF-8');
$turma      = htmlspecialchars($aula['turma_nome'], ENT_QUOTES, 'UTF-8');

$body = '<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0d0d0f;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0d0d0f;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#111113;border-radius:16px;overflow:hidden;border:1px solid #222;">

  <!-- Header -->
  <tr><td style="background:#0b0d0f;padding:24px 32px;border-bottom:3px solid #ffd500;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td><img src="' . $logoUrl . '" alt="MPG Academy" style="height:48px;width:auto;border:0;"></td>
        <td align="right" style="color:#ffd500;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Termo de Responsabilidade</td>
      </tr>
    </table>
  </td></tr>

  <!-- Body -->
  <tr><td style="padding:32px;">
    <p style="margin:0 0 16px;color:#e0e0e0;font-size:16px;">Olá, <strong style="color:#ffd500;">' . $primeiroNomeResp . '</strong>!</p>
    <p style="margin:0 0 20px;color:#b0b0b0;font-size:14px;line-height:1.7;">
      A <strong style="color:#fff;">MPG Academy</strong> convidou <strong style="color:#fff;">' . $nomeAluno . '</strong>
      para uma aula experimental na turma <strong style="color:#fff;">' . $turma . '</strong>.
    </p>
    <p style="margin:0 0 24px;color:#b0b0b0;font-size:14px;line-height:1.7;">
      Por se tratar de um menor de idade, é necessária a assinatura do <strong style="color:#fff;">Termo de Autorização e Responsabilidade</strong>.
      Clique no botão abaixo para ler o documento e assinar eletronicamente.
    </p>

    <!-- CTA -->
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

  <!-- Footer -->
  <tr><td style="background:#0b0d0f;padding:16px 32px;border-top:1px solid #222;">
    <p style="margin:0;color:#444;font-size:11px;text-align:center;">MPG Academy · Escola de Vôlei · São Paulo, SP</p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>';

// Envio
$mailCfg   = getMpgMailConfig();
$destEmail = $aula['responsavel_email'];
$destNome  = $aula['responsavel_nome'] ?? 'Responsável';
$subject   = 'MPG Academy – Termo de Autorização para ' . $aula['nome'];

if (APP_IS_LOCAL) {
    // Salva localmente
    $dir  = dirname(__FILE__, 3) . '/storage/emails_teste';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $slug = date('Y-m-d_H-i-s') . '_termo_' . preg_replace('/[^a-z0-9]/', '_', strtolower($destEmail));
    file_put_contents($dir . '/' . $slug . '.html', $body);
    echo json_encode(['success' => true, 'local' => true, 'token' => $token]);
    exit;
}

// PHPMailer (produção)
$mailerPath = dirname(__FILE__, 3) . '/vendor/autoload.php';
if (!file_exists($mailerPath)) {
    echo json_encode(['success' => false, 'message' => 'PHPMailer não configurado.']);
    exit;
}
require_once $mailerPath;
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
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
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->send();
    echo json_encode(['success' => true, 'token' => $token]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Falha ao enviar e-mail: ' . $mail->ErrorInfo]);
}
