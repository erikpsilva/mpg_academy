<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/admin/login');
    exit;
}

// ── Geração automática de mensalidades (uma vez por dia por sessão) ────────────
$_hoje = date('Y-m-d');
if (empty($_SESSION['_mens_auto']) || $_SESSION['_mens_auto'] !== $_hoje) {
    try {
        require_once ROOT . '/config/database.php';
        $__pdo = getDbConnection();

        $__ref  = date('Y-m');
        $__vencMes = new DateTime('first day of next month');
        $__venc = $__vencMes->format('Y-m') . '-05';

        $__ativos = $__pdo->query("
            SELECT ta.aluno_id, ta.turma_id,
                   ta.desconto, ta.desconto_tipo, ta.desconto_inicio, ta.desconto_fim, ta.desconto_vitalicio,
                   t.valor_mensalidade
            FROM turma_alunos ta
            JOIN turmas t ON t.id = ta.turma_id
            WHERE ta.status = 'ativo' AND t.status = 'ativa' AND t.valor_mensalidade IS NOT NULL
        ")->fetchAll();

        $__chk = $__pdo->prepare(
            "SELECT id FROM mensalidades WHERE aluno_id = ? AND turma_id = ? AND referencia = ?"
        );
        $__ins = $__pdo->prepare(
            "INSERT INTO mensalidades (aluno_id, turma_id, referencia, valor, vencimento, status)
             VALUES (?, ?, ?, ?, ?, 'pendente')"
        );

        foreach ($__ativos as $__ta) {
            $__chk->execute([$__ta['aluno_id'], $__ta['turma_id'], $__ref]);
            if ($__chk->fetchColumn()) continue;

            $__base = (float) $__ta['valor_mensalidade'];
            $__descAtivo = $__ta['desconto'] !== null && $__ta['desconto'] > 0 && (
                $__ta['desconto_vitalicio'] ||
                ($__ta['desconto_inicio'] === null && $__ta['desconto_fim'] === null) ||
                ($__ta['desconto_inicio'] <= $_hoje && $__ta['desconto_fim'] >= $_hoje)
            );
            $__valor = $__descAtivo
                ? ($__ta['desconto_tipo'] === 'percentual'
                    ? round($__base * (1 - $__ta['desconto'] / 100), 2)
                    : max(0, round($__base - (float) $__ta['desconto'], 2)))
                : $__base;

            try { $__ins->execute([$__ta['aluno_id'], $__ta['turma_id'], $__ref, $__valor, $__venc]); }
            catch (PDOException) { /* ignora duplicatas */ }
        }

        $_SESSION['_mens_auto'] = $_hoje;
    } catch (Throwable $__e) {
        error_log('[mpg-auto-mens] ' . $__e->getMessage());
    }
}
