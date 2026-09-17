<?php

/**
 * Mensagem pós-aula para todos os agendados de uma data numa turma de aula experimental.
 *
 * Disparo manual: o admin clica no botão do cabeçalho da data e escolhe o texto. Por isso
 * não passa pelos toggles de Configurações > Avisos e notificações — se ele clicou, quer
 * enviar.
 *
 * Cada envio fica em `lembrete_teste_log` com tipo 'pos_aula'. Quem já recebeu é pulado,
 * então um segundo clique (ou uma requisição que caiu no meio) não manda de novo para quem
 * já tinha recebido.
 */

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

// Texto padrão. {nome} vira o primeiro nome de cada aluno na hora do envio — o admin só
// clica, não digita nada. O campo `mensagem` continua aceito caso um dia a tela ofereça
// editar o texto.
const POS_AULA_MENSAGEM_PADRAO = 'Oi {nome}, conseguiu comparecer ao treino? Gostou da aula?';

$turmaId  = (int) ($_POST['turma_id'] ?? 0);
$data     = trim($_POST['data'] ?? '');
$template = trim($_POST['mensagem'] ?? '') ?: POS_AULA_MENSAGEM_PADRAO;

$dt = DateTime::createFromFormat('Y-m-d', $data);
if (!$turmaId || !$dt || $dt->format('Y-m-d') !== $data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Turma ou data inválida.']);
    exit;
}

// A pergunta é "conseguiu comparecer?": libera a partir do próprio dia da aula. Antes
// disso soaria como se o aluno tivesse faltado a algo que ainda nem aconteceu.
$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
if ($data > $hoje) {
    $libera = $dt->format('d/m');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "A mensagem pós-aula dessa data só libera em {$libera}, no dia da aula."]);
    exit;
}

if (mb_strlen($template) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mensagem muito longa: use no máximo 1000 caracteres.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/services/whatsapp/zapi.php';

$pdo = getDbConnection();

// Cancelados ficam de fora: desmarcaram, perguntar se gostaram da aula seria estranho.
$stmt = $pdo->prepare("
    SELECT ae.id, at.nome, at.celular, at.is_menor, at.responsavel_celular
    FROM aulas_experimentais ae
    JOIN alunos_teste at ON at.id = ae.aluno_teste_id
    WHERE ae.turma_id = ?
      AND DATE(ae.data_agendada) = ?
      AND ae.status IN ('agendada', 'realizada')
    ORDER BY at.nome
");
$stmt->execute([$turmaId, $data]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo json_encode(['success' => true, 'enviados' => 0, 'message' => 'Ninguém agendado nessa data para receber a mensagem.']);
    exit;
}

// Várias chamadas à Z-API em sequência podem passar do limite padrão do PHP. O @ é porque
// alguns servidores bloqueiam set_time_limit — nesse caso segue com o limite que houver,
// e o log impede reenvio se precisar clicar de novo para completar.
@set_time_limit(0);

$jaRecebeu = $pdo->prepare("SELECT 1 FROM lembrete_teste_log WHERE aula_experimental_id = ? AND tipo = 'pos_aula' LIMIT 1");
$registra  = $pdo->prepare("INSERT INTO lembrete_teste_log (aula_experimental_id, tipo) VALUES (?, 'pos_aula')");

$enviados = 0;
$pulados  = 0;
$semFone  = 0;
$falhas   = [];

foreach ($rows as $r) {
    $jaRecebeu->execute([$r['id']]);
    if ($jaRecebeu->fetchColumn()) {
        $pulados++;
        continue;
    }

    // Mesmo critério do lembrete de aula experimental: o celular cadastrado e, se for
    // menor com responsável informado, também o responsável — sem mandar duas vezes
    // quando os dois números são o mesmo.
    $fones = [];
    if (!empty($r['celular'])) {
        $fones[] = formatPhoneZapi($r['celular']);
    }
    if (!empty($r['is_menor']) && !empty($r['responsavel_celular'])) {
        $fones[] = formatPhoneZapi($r['responsavel_celular']);
    }
    $fones = array_values(array_unique($fones));

    if (!$fones) {
        $semFone++;
        continue;
    }

    $primeiroNome = explode(' ', trim($r['nome']))[0];
    $mensagem     = str_replace('{nome}', $primeiroNome, $template);

    $algumEnviou = false;
    foreach ($fones as $fone) {
        if (sendWhatsApp($fone, $mensagem)) {
            $algumEnviou = true;
        }
    }

    if ($algumEnviou) {
        $registra->execute([$r['id']]);
        $enviados++;
    } else {
        $falhas[] = trim($r['nome']);
    }
}

$partes = [$enviados . ' mensagem(ns) enviada(s).'];
if ($pulados) $partes[] = $pulados . ' já tinha(m) recebido e foi(ram) pulado(s).';
if ($semFone) $partes[] = $semFone . ' sem celular cadastrado.';
if ($falhas)  $partes[] = 'Não enviou para: ' . implode(', ', $falhas) . '. Clique de novo para tentar só esses.';

echo json_encode([
    'success'  => true,
    'enviados' => $enviados,
    'pulados'  => $pulados,
    'sem_fone' => $semFone,
    'falhas'   => count($falhas),
    'message'  => implode(' ', $partes),
]);
