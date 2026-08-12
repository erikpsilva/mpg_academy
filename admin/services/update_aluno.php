<?php

/**
 * Edição dos dados cadastrais do aluno pelo admin.
 *
 * Não mexe em senha, status, turmas nem matrícula — cada um desses tem seu próprio fluxo
 * na tela (Alterar Senha, Desativar, Turmas e Valores, Isentar matrícula). Aqui é só o
 * cadastro: identificação, contato, endereço e, quando menor, os dados do responsável.
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

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (empty($_SESSION['usuario']) || !in_array($nivel, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$pdo     = getDbConnection();
$alunoId = (int) ($_POST['aluno_id'] ?? 0);

if ($alunoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aluno inválido.']);
    exit;
}

$stAtual = $pdo->prepare("SELECT id, is_menor FROM alunos WHERE id = ?");
$stAtual->execute([$alunoId]);
$atual = $stAtual->fetch();

if (!$atual) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}

// ── Entrada ───────────────────────────────────────────────────────────────────
$nome       = trim($_POST['nome']        ?? '');
$email      = trim($_POST['email']       ?? '');
$cpf        = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$nascimento = trim($_POST['nascimento']  ?? '');
$sexo       = trim($_POST['sexo']        ?? '');
$celular    = trim($_POST['celular']     ?? '');
$cep        = trim($_POST['cep']         ?? '');
$rua        = trim($_POST['rua']         ?? '');
$numero     = trim($_POST['numero']      ?? '');
$complemento= trim($_POST['complemento'] ?? '');
$bairro     = trim($_POST['bairro']      ?? '');
$cidade     = trim($_POST['cidade']      ?? '');
$estado     = strtoupper(trim($_POST['estado'] ?? ''));

$respNome       = trim($_POST['responsavel_nome']        ?? '');
$respParentesco = trim($_POST['responsavel_parentesco']  ?? '');
$respCpf        = preg_replace('/\D/', '', $_POST['responsavel_cpf'] ?? '');
$respCelular    = trim($_POST['responsavel_celular']     ?? '');

// ── Validação ─────────────────────────────────────────────────────────────────
$erro = null;

if ($nome === '')                                     $erro = 'Informe o nome do aluno.';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))   $erro = 'E-mail inválido.';
elseif (strlen($cpf) !== 11)                          $erro = 'CPF deve ter 11 dígitos.';
elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nascimento)) $erro = 'Data de nascimento inválida.';
elseif (!in_array($sexo, ['masculino', 'feminino', 'outro'], true)) $erro = 'Selecione o sexo.';
elseif ($celular === '')                              $erro = 'Informe o celular.';
elseif ($estado !== '' && strlen($estado) !== 2)      $erro = 'UF deve ter 2 letras.';

if ($erro) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $erro]);
    exit;
}

// Menor de idade é recalculado pela data de nascimento — não vem do formulário, senão
// daria pra salvar um menor sem responsável só desmarcando um campo.
$idade = (new DateTime($nascimento))->diff(new DateTime('today'))->y;
$isMenor = $idade < 18;

if ($isMenor && ($respNome === '' || strlen($respCpf) !== 11 || $respCelular === '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'O aluno é menor de idade — preencha nome, CPF e celular do responsável.']);
    exit;
}

if (!in_array($respParentesco, ['pai', 'mae', 'responsavel_legal'], true)) {
    $respParentesco = $isMenor ? 'responsavel_legal' : null;
}

// ── Duplicidade ───────────────────────────────────────────────────────────────
// CPF é sempre único. E-mail só precisa ser único entre MAIORES: menor usa o e-mail do
// responsável, que pode ser aluno também (ver services/site/register_student.php).
$stCpf = $pdo->prepare("SELECT id FROM alunos WHERE cpf = ? AND id != ? LIMIT 1");
$stCpf->execute([$cpf, $alunoId]);
if ($stCpf->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este CPF já está cadastrado em outro aluno.']);
    exit;
}

if (!$isMenor) {
    $stEmail = $pdo->prepare("SELECT id FROM alunos WHERE email = ? AND is_menor = 0 AND id != ? LIMIT 1");
    $stEmail->execute([$email, $alunoId]);
    if ($stEmail->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado em outro aluno.']);
        exit;
    }
}

// ── Gravação ──────────────────────────────────────────────────────────────────
try {
    $pdo->prepare("
        UPDATE alunos SET
            nome = ?, email = ?, cpf = ?, nascimento = ?, sexo = ?,
            celular = ?, whatsapp = ?, cep = ?, rua = ?, numero = ?, complemento = ?,
            bairro = ?, cidade = ?, estado = ?,
            is_menor = ?,
            responsavel_nome = ?, responsavel_parentesco = ?, responsavel_cpf = ?, responsavel_celular = ?,
            atualizado_em = NOW()
        WHERE id = ?
    ")->execute([
        $nome, $email, $cpf, $nascimento, $sexo,
        // Um número só no cadastro: a coluna `whatsapp` espelha o celular. Ela continua
        // existindo (NOT NULL, com dados antigos), mas os disparos leem `alunos.celular`.
        $celular, $celular, $cep, $rua, $numero, $complemento ?: null,
        $bairro, $cidade, $estado,
        $isMenor ? 1 : 0,
        $isMenor ? $respNome : null,
        $isMenor ? $respParentesco : null,
        $isMenor ? $respCpf : null,
        $isMenor ? $respCelular : null,
        $alunoId,
    ]);

    echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso.', 'is_menor' => $isMenor]);

} catch (PDOException $e) {
    error_log('[update-aluno] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar os dados.']);
}
