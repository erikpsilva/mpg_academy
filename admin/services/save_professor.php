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

// Salário: converte "2.500,00" → 2500.00
$salarioRaw = trim($_POST['salario'] ?? '');
$salario = null;
if ($salarioRaw !== '') {
    $salario = (float) str_replace(['.', ','], ['', '.'], $salarioRaw);
}

// Data de nascimento: "DD/MM/AAAA" → "AAAA-MM-DD"
$dataNasc = null;
$nascRaw  = trim($_POST['data_nascimento'] ?? '');
if ($nascRaw !== '') {
    $parts = explode('/', $nascRaw);
    if (count($parts) === 3) {
        $dataNasc = sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
        if (!checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2])) $dataNasc = null;
    }
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
    if ($id > 0) {
        // Edição: senha só atualiza se informada
        if ($senha !== '') {
            $st = $pdo->prepare("
                UPDATE professores
                SET nome=?, sobrenome=?, email=?, senha=?, cpf=?, celular=?,
                    data_nascimento=?, salario=?, dia_pagamento=?, status=?
                WHERE id=?
            ");
            $st->execute([
                $nome, $sobrenome, $email, password_hash($senha, PASSWORD_DEFAULT),
                $cpf ?: null, $celular ?: null, $dataNasc,
                $salario, $diaPgto ?: null, $status, $id
            ]);
        } else {
            $st = $pdo->prepare("
                UPDATE professores
                SET nome=?, sobrenome=?, email=?, cpf=?, celular=?,
                    data_nascimento=?, salario=?, dia_pagamento=?, status=?
                WHERE id=?
            ");
            $st->execute([
                $nome, $sobrenome, $email,
                $cpf ?: null, $celular ?: null, $dataNasc,
                $salario, $diaPgto ?: null, $status, $id
            ]);
        }
    } else {
        $st = $pdo->prepare("
            INSERT INTO professores
                (nome, sobrenome, email, senha, cpf, celular, data_nascimento, salario, dia_pagamento, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $nome, $sobrenome, $email, password_hash($senha, PASSWORD_DEFAULT),
            $cpf ?: null, $celular ?: null, $dataNasc,
            $salario, $diaPgto ?: null, $status
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    echo json_encode(['success' => true, 'id' => $id]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar.']);
    }
}
