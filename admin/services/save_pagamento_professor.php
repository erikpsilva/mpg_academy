<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); exit; }

if (($_SESSION['usuario']['nivel_acesso'] ?? '') === 'professor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$profId      = (int)   trim($_POST['professor_id']  ?? '');
$valorRaw    =         trim($_POST['valor']          ?? '');
$dataPgto    =         trim($_POST['data_pagamento'] ?? '');
$competencia =         trim($_POST['competencia']    ?? '');
$referencia  =         trim($_POST['referencia']     ?? '') ?: null;
$obs         =         trim($_POST['observacao']     ?? '') ?: null;

$valor = (float) str_replace(['.', ','], ['', '.'], $valorRaw);

if ($profId <= 0 || $valor <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPgto) || !preg_match('/^\d{4}-\d{2}$/', $competencia)) {
    echo json_encode(['success' => false, 'message' => 'Preencha professor, valor, data e mês de referência corretamente.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

try {
    // Comprovantes agora ficam em professor_pagamento_anexos (múltiplos arquivos), anexados
    // pelo front-end logo após esse insert, usando o id retornado abaixo.
    $pdo->prepare("
        INSERT INTO professor_pagamentos (professor_id, competencia, valor, data_pagamento, referencia, observacao)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$profId, $competencia, $valor, $dataPgto, $referencia, $obs]);
    $pagamentoId = (int) $pdo->lastInsertId();

    // Registra no caixa (livro-caixa) — mesmo padrão de save_patrocinador.php. Competência =
    // mês trabalhado (não o mês em que o pagamento foi feito), mesmo princípio já aplicado em
    // mensalidades/lancamentos_financeiros no resto do sistema.
    try {
        $stProf = $pdo->prepare("SELECT nome, sobrenome FROM professores WHERE id = ?");
        $stProf->execute([$profId]);
        $prof = $stProf->fetch(PDO::FETCH_ASSOC);
        $descLanc = 'Pagamento professor — ' . trim(($prof['nome'] ?? '') . ' ' . ($prof['sobrenome'] ?? ''));

        $pdo->prepare("
            INSERT INTO lancamentos_financeiros
                (competencia, data, tipo, categoria, descricao, valor, origem, referencia_tipo, referencia_id)
            VALUES (?, ?, 'despesa', 'salario', ?, ?, 'manual', 'professor_pagamento', ?)
        ")->execute([$competencia, $dataPgto, $descLanc, $valor, $pagamentoId]);
    } catch (PDOException $e) {}

    echo json_encode(['success' => true, 'id' => $pagamentoId]);
} catch (PDOException $e) {
    error_log('[save_pagamento_professor] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar pagamento.']);
}
