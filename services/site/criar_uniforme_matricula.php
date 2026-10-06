<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['aluno']['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';
require_once dirname(__FILE__, 3) . '/config/mensalidades.php';
$pdo = getDbConnection();
$alunoId = (int) $_SESSION['aluno']['id'];
$genero = trim($_POST['genero'] ?? '');
$modelo = trim($_POST['modelo'] ?? '');
$nome = uniformeNormalizarNome($_POST['nome_camisa'] ?? '');
$turmaId=(int)($_POST['turma_id']??0);$numero=(int)($_POST['numero']??0);

if (!in_array($genero, UNIFORME_GENEROS, true) || !in_array($modelo, UNIFORME_MODELOS, true) || $nome === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha os dados do uniforme.']);
    exit;
}

$tam = uniformeValidarTamanhos('completo', $genero, [
    'camisa' => $_POST['tamanho_camisa'] ?? '',
    'shorts' => $_POST['tamanho_shorts'] ?? '',
]);
if (!$tam['ok']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $tam['message']]);
    exit;
}

try {
    $pdo->beginTransaction();
    $turmas=uniformeTurmasDoAluno($pdo,$alunoId);$turmaIds=array_map(fn($t)=>(int)$t['id'],$turmas);
    if($turmaIds){if(!in_array($turmaId,$turmaIds,true)||$numero<UNIFORME_NUMERO_MIN||$numero>UNIFORME_NUMERO_MAX)throw new RuntimeException('Escolha um número válido da sua turma.');$lock=$pdo->prepare("SELECT aluno_id FROM pedidos_uniforme WHERE turma_id=? AND genero=? AND numero=? AND ".UNIFORME_SQL_SEGURA_NUMERO." FOR UPDATE");$lock->execute([$turmaId,$genero,$numero]);foreach($lock->fetchAll(PDO::FETCH_COLUMN) as $dono)if((int)$dono!==$alunoId)throw new RuntimeException('Esse número acabou de ser escolhido. Escolha outro.');}else{$turmaId=null;$numero=null;}
    $check = $pdo->prepare("SELECT id FROM pedidos_uniforme WHERE aluno_id = ? AND origem_cobranca = 'matricula' LIMIT 1 FOR UPDATE");
    $check->execute([$alunoId]);
    if ($check->fetchColumn()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'O uniforme da matrícula já foi solicitado.']);
        exit;
    }

    $valorUniforme = mensalidadeValorUniformeMatricula($pdo);
    $fat=$pdo->prepare("SELECT id FROM mensalidades WHERE aluno_id=? AND matricula_uniforme_valor>0 ORDER BY id DESC LIMIT 1");$fat->execute([$alunoId]);$mensalidadeId=$fat->fetchColumn()?:null;
    $pdo->prepare("INSERT INTO pedidos_uniforme
        (pessoa_tipo, pessoa_id, tipo_uniforme, aluno_id, turma_id, genero, modelo, nome_camisa, numero,
         tamanho_camisa, tamanho_shorts, valor, origem_cobranca, mensalidade_id, status_pagamento, status_pedido, reserva_expira_em)
        VALUES ('aluno', ?, 'completo', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'matricula', ?, 'aguardando', 'pendente', NULL)")
        ->execute([$alunoId,$alunoId,$turmaId,$genero,$modelo,$nome,$numero,$tam['camisa'],$tam['shorts'],$valorUniforme,$mensalidadeId]);
    $pdo->commit();
    $redirect = $mensalidadeId
        ? BASE_URL . '/pagamento?mensalidade_id=' . (int) $mensalidadeId
        : BASE_URL . '/mensalidades';
    echo json_encode(['success' => true, 'redirect' => $redirect]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[uniforme-matricula] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar o uniforme.']);
}
