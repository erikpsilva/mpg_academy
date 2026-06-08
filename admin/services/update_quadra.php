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

$dados = json_decode($_POST['dados'] ?? '{}', true);
if (!$dados || !is_array($dados)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$id                 = (int)   ($dados['id']                ?? 0);
$nome               = trim($dados['nome']                   ?? '');
$telefone           = trim($dados['telefone']               ?? '');
$email              = trim($dados['email']                  ?? '') ?: null;
$instagram          = trim($dados['instagram']              ?? '') ?: null;
$cep                = preg_replace('/\D/', '', $dados['cep'] ?? '');
$rua                = trim($dados['rua']                    ?? '');
$numero             = trim($dados['numero']                 ?? '');
$bairro             = trim($dados['bairro']                 ?? '');
$complemento        = trim($dados['complemento']            ?? '') ?: null;
$cidade             = trim($dados['cidade']                 ?? '');
$estado             = trim($dados['estado']                 ?? '');
$valorMensal        = (float) ($dados['valor_mensal']       ?? 0);
$diaPagamento       = (int)   ($dados['dia_pagamento']      ?? 10);
$dataInicioContrato = !empty($dados['data_inicio_contrato']) ? $dados['data_inicio_contrato'] : null;
$horarios           = $dados['horarios'] ?? [];
$turmas             = $dados['turmas']   ?? [];

if ($id <= 0 || !$nome || !$telefone || !$cep || !$rua || !$numero || !$bairro || !$cidade || !$estado) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
    exit;
}
if ($diaPagamento < 1 || $diaPagamento > 31) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dia de pagamento inválido (1–31).']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    // ── 1. Atualiza dados da quadra ───────────────────────────────────────────
    $pdo->prepare("
        UPDATE quadras
        SET nome=?, telefone=?, email=?, instagram=?, cep=?, rua=?, numero=?, bairro=?,
            complemento=?, cidade=?, estado=?, valor_mensal=?, dia_pagamento=?,
            data_inicio_contrato=?, updated_at=NOW()
        WHERE id=?
    ")->execute([
        $nome, $telefone, $email, $instagram, $cep, $rua, $numero, $bairro,
        $complemento, $cidade, $estado, $valorMensal, $diaPagamento, $dataInicioContrato, $id,
    ]);

    // ── 2. Sync de horários (mantém existentes que coincidem, insere novos, remove só os que saíram) ──
    $stCurr = $pdo->prepare("SELECT id, dia_semana, hora_inicio, hora_fim FROM quadra_horarios WHERE quadra_id = ?");
    $stCurr->execute([$id]);
    $currentHorarios = $stCurr->fetchAll();

    $horarioIds   = [];   // idx → db id (para linkagem de turmas)
    $usedCurrIds  = [];   // ids de horarios existentes que foram reutilizados

    foreach ($horarios as $idx => $h) {
        $dia    = max(0, min(6, (int)($h['dia_semana']  ?? 0)));
        $inicio = substr($h['hora_inicio'] ?? '00:00', 0, 5);
        $fim    = substr($h['hora_fim']    ?? '00:00', 0, 5);

        $matched = null;
        foreach ($currentHorarios as $cur) {
            if (in_array((int)$cur['id'], $usedCurrIds)) continue;
            if ((int)$cur['dia_semana'] === $dia
                && substr($cur['hora_inicio'], 0, 5) === $inicio
                && substr($cur['hora_fim'],    0, 5) === $fim) {
                $matched = $cur;
                break;
            }
        }

        if ($matched) {
            $horarioIds[$idx] = (int)$matched['id'];
            $usedCurrIds[]    = (int)$matched['id'];
        } else {
            $s = $pdo->prepare("INSERT INTO quadra_horarios (quadra_id, dia_semana, hora_inicio, hora_fim) VALUES (?, ?, ?, ?)");
            $s->execute([$id, $dia, $inicio, $fim]);
            $horarioIds[$idx] = (int)$pdo->lastInsertId();
        }
    }

    // Remove horários que saíram do cadastro (cascade remove apenas turma_horarios, não as turmas)
    $stDel = $pdo->prepare("DELETE FROM quadra_horarios WHERE id = ?");
    foreach ($currentHorarios as $cur) {
        if (!in_array((int)$cur['id'], $usedCurrIds)) {
            $stDel->execute([$cur['id']]);
        }
    }

    // ── 3. Sync de turmas — NUNCA deleta, apenas cria/atualiza ───────────────
    $updatedTurmaIds = [];

    foreach ($turmas as $t) {
        $turmaNome        = trim($t['nome'] ?? '');
        if (!$turmaNome) continue;

        $dbTurmaId        = isset($t['id']) && (int)$t['id'] > 0 ? (int)$t['id'] : 0;
        $valorMensalidade = isset($t['valor_mensalidade']) && $t['valor_mensalidade'] !== null ? (float)$t['valor_mensalidade'] : null;
        $genero           = in_array($t['genero'] ?? '', ['masculino','feminino','misto']) ? $t['genero'] : 'misto';
        $nivel            = in_array($t['nivel']  ?? '', ['iniciante','intermediario','avancado']) ? $t['nivel'] : 'iniciante';
        $promoValor       = isset($t['promo_valor']) && $t['promo_valor'] !== null ? (float)$t['promo_valor'] : null;
        $promoMeses       = isset($t['promo_meses']) && $t['promo_meses'] !== null ? (int)$t['promo_meses'] : null;
        $maxAlunos        = isset($t['max_alunos'])  && $t['max_alunos']  !== null ? (int)$t['max_alunos'] : null;

        if ($dbTurmaId > 0) {
            // Turma existente → apenas atualiza dados, garante que está ativa
            $pdo->prepare("
                UPDATE turmas
                SET nome=?, genero=?, nivel=?, valor_mensalidade=?, promo_valor=?, promo_meses=?, max_alunos=?, status='ativa'
                WHERE id=? AND quadra_id=?
            ")->execute([$turmaNome, $genero, $nivel, $valorMensalidade, $promoValor, $promoMeses, $maxAlunos, $dbTurmaId, $id]);
            $turmaId = $dbTurmaId;
        } else {
            // Turma nova → insere
            $s = $pdo->prepare("INSERT INTO turmas (quadra_id, nome, genero, nivel, valor_mensalidade, promo_valor, promo_meses, max_alunos) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $s->execute([$id, $turmaNome, $genero, $nivel, $valorMensalidade, $promoValor, $promoMeses, $maxAlunos]);
            $turmaId = (int)$pdo->lastInsertId();
        }
        $updatedTurmaIds[] = $turmaId;

        // Resync de turma_horarios (seguro: só remove/adiciona links, não toca dados)
        $pdo->prepare("DELETE FROM turma_horarios WHERE turma_id = ?")->execute([$turmaId]);
        foreach ($t['horario_indices'] ?? [] as $idx) {
            $idx = (int)$idx;
            if (isset($horarioIds[$idx])) {
                $pdo->prepare("INSERT INTO turma_horarios (turma_id, horario_id) VALUES (?, ?)")
                    ->execute([$turmaId, $horarioIds[$idx]]);
            }
        }
    }

    // Turmas desta quadra que não vieram no form → marca como inativa (NÃO deleta)
    if (!empty($updatedTurmaIds)) {
        $placeholders = implode(',', array_fill(0, count($updatedTurmaIds), '?'));
        $stInactive = $pdo->prepare("
            UPDATE turmas SET status = 'inativa'
            WHERE quadra_id = ? AND id NOT IN ($placeholders)
        ");
        $stInactive->execute(array_merge([$id], $updatedTurmaIds));
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Quadra atualizada com sucesso!', 'id' => $id]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[update_quadra] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao atualizar quadra.']);
}
