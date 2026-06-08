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

$id        = (int) ($_POST['id'] ?? 0);
$nome      = trim($_POST['nome']      ?? '');
$sobrenome = trim($_POST['sobrenome'] ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$senha     = $_POST['senha'] ?? '';
$cpf       = trim($_POST['cpf']       ?? '');
$celular   = trim($_POST['celular']   ?? '');
$status    = in_array($_POST['status'] ?? '', ['ativo','inativo']) ? $_POST['status'] : 'ativo';
$diaPgto   = (int) ($_POST['dia_pagamento'] ?? 0);

function parseBrl(string $v): ?float {
    $v = trim($v);
    if ($v === '') return null;
    return (float) str_replace(['.', ','], ['', '.'], $v);
}

$valor90  = parseBrl($_POST['valor_aula_90min']  ?? '');
$valor120 = parseBrl($_POST['valor_aula_120min'] ?? '');

$dataNasc = null;
$nascRaw  = trim($_POST['data_nascimento'] ?? '');
if ($nascRaw !== '') {
    $parts = explode('/', $nascRaw);
    if (count($parts) === 3) {
        $dataNasc = sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
        if (!checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2])) $dataNasc = null;
    }
}

// Turmas vinculadas + data de início por turma (turma_data_inicio[ID])
$turmaIds = array_filter(array_map('intval', $_POST['turma_ids'] ?? []));

$dataInicioMap = [];
foreach (($_POST['turma_data_inicio'] ?? []) as $tid => $data) {
    $tid  = (int) $tid;
    $data = trim($data);
    $dataInicioMap[$tid] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? $data : date('Y-m-d');
}

if ($nome === '' || $sobrenome === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Nome, sobrenome e e-mail são obrigatórios.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}
if ($id === 0 && $senha === '') {
    echo json_encode(['success' => false, 'message' => 'Senha obrigatória para novo professor.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    if ($id > 0) {
        if ($senha !== '') {
            $st = $pdo->prepare("
                UPDATE professores
                SET nome=?, sobrenome=?, email=?, senha=?, cpf=?, celular=?,
                    data_nascimento=?, valor_aula_90min=?, valor_aula_120min=?,
                    dia_pagamento=?, status=?
                WHERE id=?
            ");
            $st->execute([
                $nome, $sobrenome, $email, password_hash($senha, PASSWORD_DEFAULT),
                $cpf ?: null, $celular ?: null, $dataNasc,
                $valor90, $valor120, $diaPgto ?: null, $status, $id
            ]);
        } else {
            $st = $pdo->prepare("
                UPDATE professores
                SET nome=?, sobrenome=?, email=?, cpf=?, celular=?,
                    data_nascimento=?, valor_aula_90min=?, valor_aula_120min=?,
                    dia_pagamento=?, status=?
                WHERE id=?
            ");
            $st->execute([
                $nome, $sobrenome, $email,
                $cpf ?: null, $celular ?: null, $dataNasc,
                $valor90, $valor120, $diaPgto ?: null, $status, $id
            ]);
        }
    } else {
        $st = $pdo->prepare("
            INSERT INTO professores
                (nome, sobrenome, email, senha, cpf, celular, data_nascimento,
                 valor_aula_90min, valor_aula_120min, dia_pagamento, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $nome, $sobrenome, $email, password_hash($senha, PASSWORD_DEFAULT),
            $cpf ?: null, $celular ?: null, $dataNasc,
            $valor90, $valor120, $diaPgto ?: null, $status
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    // ── Sincroniza vínculos com turmas ────────────────────────────────────────
    // Busca turmas atuais
    $stAtual = $pdo->prepare("SELECT turma_id FROM professor_turmas WHERE professor_id = ?");
    $stAtual->execute([$id]);
    $atuais = $stAtual->fetchAll(PDO::FETCH_COLUMN);

    // Remove desvinculadas
    $stDel = $pdo->prepare("DELETE FROM professor_turmas WHERE professor_id = ? AND turma_id = ?");
    foreach ($atuais as $tid) {
        if (!in_array($tid, $turmaIds)) {
            $stDel->execute([$id, $tid]);
        }
    }

    // Upsert turmas selecionadas (insere novas, atualiza data_inicio nas existentes)
    $stUpsert = $pdo->prepare("
        INSERT INTO professor_turmas (professor_id, turma_id, data_inicio)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE data_inicio = VALUES(data_inicio)
    ");
    foreach ($turmaIds as $tid) {
        $stUpsert->execute([$id, $tid, $dataInicioMap[$tid] ?? date('Y-m-d')]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $id]);

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar.']);
    }
}
