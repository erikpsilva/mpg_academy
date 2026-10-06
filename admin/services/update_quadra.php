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
// Opcional: sem ele, googleMapsLink() cai na busca pelo endereço (ver services/whatsapp/zapi.php).
$mapsLink           = trim($dados['maps_link']               ?? '') ?: null;
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
require_once dirname(__FILE__, 3) . '/config/mensalidades.php';   // ajustarDescontosDaPromo()
$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    // ── 1. Atualiza dados da quadra ───────────────────────────────────────────
    $pdo->prepare("
        UPDATE quadras
        SET nome=?, telefone=?, email=?, instagram=?, cep=?, rua=?, numero=?, bairro=?,
            complemento=?, cidade=?, estado=?, maps_link=?, valor_mensal=?, dia_pagamento=?,
            data_inicio_contrato=?, updated_at=NOW()
        WHERE id=?
    ")->execute([
        $nome, $telefone, $email, $instagram, $cep, $rua, $numero, $bairro,
        $complemento, $cidade, $estado, $mapsLink, $valorMensal, $diaPagamento, $dataInicioContrato, $id,
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

    // Quantos descontos e faturas em aberto foram realinhados por mudança de preço — vira
    // aviso na resposta, pra quem mexeu no valor saber que isso aconteceu.
    $realinhados = ['descontos' => 0, 'faturas' => 0];

    foreach ($turmas as $t) {
        $turmaNome        = trim($t['nome'] ?? '');
        if (!$turmaNome) continue;

        $dbTurmaId        = isset($t['id']) && (int)$t['id'] > 0 ? (int)$t['id'] : 0;
        $valorMensalidade = isset($t['valor_mensalidade']) && $t['valor_mensalidade'] !== null ? (float)$t['valor_mensalidade'] : null;
        $genero           = in_array($t['genero'] ?? '', ['masculino','feminino','misto']) ? $t['genero'] : 'misto';
        $nivel            = in_array($t['nivel']  ?? '', ['iniciante','intermediario','avancado']) ? $t['nivel'] : 'iniciante';
        $faixaEtaria      = in_array($t['faixa_etaria'] ?? '', ['adulto','adolescente','infantil']) ? $t['faixa_etaria'] : 'adulto';
        $promoValor       = isset($t['promo_valor']) && $t['promo_valor'] !== null ? (float)$t['promo_valor'] : null;
        $promoMeses       = isset($t['promo_meses']) && $t['promo_meses'] !== null ? (int)$t['promo_meses'] : null;
        $maxAlunos        = isset($t['max_alunos'])  && $t['max_alunos']  !== null ? (int)$t['max_alunos'] : null;

        if ($dbTurmaId > 0) {
            // Antes de gravar: como estava, pra saber se preço ou promoção mudaram.
            $stAntes = $pdo->prepare("SELECT valor_mensalidade, promo_valor FROM turmas WHERE id = ? AND quadra_id = ?");
            $stAntes->execute([$dbTurmaId, $id]);
            $antes = $stAntes->fetch();

            // Turma existente → apenas atualiza dados, garante que está ativa
            $pdo->prepare("
                UPDATE turmas
                SET nome=?, genero=?, nivel=?, faixa_etaria=?, valor_mensalidade=?, promo_valor=?, promo_meses=?, max_alunos=?, status='ativa'
                WHERE id=? AND quadra_id=?
            ")->execute([$turmaNome, $genero, $nivel, $faixaEtaria, $valorMensalidade, $promoValor, $promoMeses, $maxAlunos, $dbTurmaId, $id]);
            $turmaId = $dbTurmaId;

            // O desconto do aluno é gravado em REAIS (valor da turma − valor promocional) e
            // fica congelado na matrícula. Então mexer no preço da turma aqui mudava, sem
            // avisar, quanto cada aluno em promoção paga: baixar a mensalidade de 119,90 pra
            // 109,90 transformou a promoção de 99,99 em 89,99 pra treze alunos.
            //
            // Quem está na promoção PADRÃO da turma é recalculado junto. Desconto negociado
            // caso a caso (valor diferente do padrão) fica como está — é combinação com
            // aquele aluno, não consequência do preço da turma.
            $ajuste = ajustarDescontosDaPromo($pdo, $turmaId, $antes, $valorMensalidade, $promoValor);
            $realinhados['descontos'] += $ajuste['descontos'];
            $realinhados['faturas']   += $ajuste['faturas'];
        } else {
            // Turma nova → insere
            $s = $pdo->prepare("INSERT INTO turmas (quadra_id, nome, genero, nivel, faixa_etaria, valor_mensalidade, promo_valor, promo_meses, max_alunos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $s->execute([$id, $turmaNome, $genero, $nivel, $faixaEtaria, $valorMensalidade, $promoValor, $promoMeses, $maxAlunos]);
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

    // Mudar preço mexe no bolso de quem já está matriculado: a resposta diz o que foi
    // realinhado, em vez de deixar a descoberta pro aluno na hora de pagar.
    $mensagem = 'Quadra atualizada com sucesso!';
    if ($realinhados['descontos'] || $realinhados['faturas']) {
        $partes = [];
        if ($realinhados['descontos']) $partes[] = $realinhados['descontos'] . ' desconto(s) de promoção';
        if ($realinhados['faturas'])   $partes[] = $realinhados['faturas'] . ' fatura(s) em aberto';
        $mensagem .= ' Ajustei ' . implode(' e ', $partes) . ' para o preço novo.';
    }

    echo json_encode([
        'success'     => true,
        'message'     => $mensagem,
        'id'          => $id,
        'realinhados' => $realinhados,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[update_quadra] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao atualizar quadra.']);
}
