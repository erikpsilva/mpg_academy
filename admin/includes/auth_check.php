<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/admin/login');
    exit;
}

// ── Controle de acesso por perfil ──────────────────────────────────────────
$_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_naAreaProfessor = (
    strpos($_uri, '/area-professor')   !== false ||
    strpos($_uri, '/meus-pagamentos')  !== false ||
    strpos($_uri, '/prof-turmas')      !== false ||
    strpos($_uri, '/meu-contrato')     !== false ||
    strpos($_uri, '/minhas-aulas')     !== false ||
    strpos($_uri, '/minha-frequencia') !== false
);

if ($_SESSION['usuario']['nivel_acesso'] === 'professor') {
    // Professor só pode ver a própria área
    if (!$_naAreaProfessor) {
        header('Location: ' . BASE_URL . '/admin/area-professor');
        exit;
    }
} else {
    // Admin/editor/leitor não acessam área do professor
    if ($_naAreaProfessor) {
        header('Location: ' . BASE_URL . '/admin/inicio');
        exit;
    }
}

// ── Geração automática de mensalidades (uma vez por dia por sessão) ────────────
// Não roda para professores
if ($_SESSION['usuario']['nivel_acesso'] === 'professor') return;

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
            catch (PDOException $__dup) { /* ignora duplicatas */ }
        }

        $_SESSION['_mens_auto'] = $_hoje;
    } catch (Throwable $__e) {
        error_log('[mpg-auto-mens] ' . $__e->getMessage());
    }
}
