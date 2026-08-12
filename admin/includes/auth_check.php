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

$_naAreaBatebola = strpos($_uri, '/batebola') !== false || strpos($_uri, '/meusdados') !== false;

if ($_SESSION['usuario']['nivel_acesso'] === 'professor') {
    // Professor só pode ver a própria área
    if (!$_naAreaProfessor) {
        header('Location: ' . BASE_URL . '/admin/area-professor');
        exit;
    }
} elseif ($_SESSION['usuario']['nivel_acesso'] === 'batebola') {
    // Perfil Bate Bola só pode ver as páginas do Bate Bola (e os próprios dados)
    if (!$_naAreaBatebola) {
        header('Location: ' . BASE_URL . '/admin/batebola');
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
// Não roda para professores nem para o perfil Bate Bola
if (in_array($_SESSION['usuario']['nivel_acesso'], ['professor', 'batebola'], true)) return;

$_hoje = date('Y-m-d');
if (empty($_SESSION['_mens_auto']) || $_SESSION['_mens_auto'] !== $_hoje) {
    try {
        require_once ROOT . '/config/database.php';
        require_once ROOT . '/config/mensalidades.php';
        $__pdo = getDbConnection();

        // Fallback: garante que todo aluno ativo tenha fatura pro mês em que já estamos, caso
        // ele não tenha pago a fatura anterior (o que teria disparado a geração da próxima
        // automaticamente, ver mpMarcarMensalidadePaga() / update_mensalidade_status.php). Nunca
        // gera pro mês SEGUINTE aqui — isso evitaria ter duas faturas em aberto ao mesmo tempo
        // sem o aluno ter pago nenhuma delas.
        gerarMensalidadesMesAtual($__pdo);

        $_SESSION['_mens_auto'] = $_hoje;
    } catch (Throwable $__e) {
        error_log('[mpg-auto-mens] ' . $__e->getMessage());
    }
}

// ── Cobrança automática de mensalidades (uma vez por dia por sessão) ──────────
// Fallback caso o cron do cPanel (cron/cobranca_automatica.php) não esteja configurado
// ou não rode num dia — sem isso, um aluno com cartão salvo e vencimento hoje só seria
// cobrado quando o cron externo rodasse, o que depende de configuração fora do sistema.
// Nunca roda em ambiente local (APP_IS_LOCAL) — evita disparar cobrança real no Mercado
// Pago sempre que alguém navega o painel admin durante desenvolvimento/testes.
require_once ROOT . '/config/app.php';
if (!APP_IS_LOCAL && (empty($_SESSION['_cobranca_auto']) || $_SESSION['_cobranca_auto'] !== $_hoje)) {
    try {
        require_once ROOT . '/config/database.php';
        require_once ROOT . '/config/mercadopago.php';
        $__pdoCobranca = getDbConnection();
        mpExecutarCobrancaAutomatica($__pdoCobranca);

        $_SESSION['_cobranca_auto'] = $_hoje;
    } catch (Throwable $__e) {
        error_log('[mpg-auto-cobranca] ' . $__e->getMessage());
    }
}
