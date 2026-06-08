<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'professor') {
    header('Location: ' . ADMIN_BASE_URL . '/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
$pdo    = getDbConnection();
$profId = (int) $_SESSION['usuario']['professor_id'];

$st = $pdo->prepare("
    SELECT pc.id, pc.token, pc.arquivo, pc.criado_em,
           pc.assinado_nome, pc.assinado_cpf, pc.assinado_em,
           pc.assinado_empresa_nome, pc.assinado_empresa_em
    FROM professor_contratos pc
    WHERE pc.professor_id = ?
    ORDER BY pc.criado_em DESC
    LIMIT 1
");
$st->execute([$profId]);
$contrato = $st->fetch(PDO::FETCH_ASSOC);

$assinado        = $contrato && !empty($contrato['assinado_em']);
$assinadoEmpresa = $contrato && !empty($contrato['assinado_empresa_em']);
$sigUrl          = BASE_URL . '/contrato?token=' . ($contrato['token'] ?? '');

function fmtDtContratoPr(?string $d): string {
    if (!$d) return '—';
    return (new DateTime($d))->format('d/m/Y \à\s H:i');
}
function maskCpfContratoPr(?string $cpf): string {
    if (!$cpf) return '—';
    $d = preg_replace('/\D/', '', $cpf);
    return strlen($d) === 11 ? substr($d,0,3) . '.***.***-' . substr($d,9,2) : $cpf;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy — Meu Contrato</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <div class="areaProfessor__welcome">
            <div>
                <h1 class="areaProfessor__title">Meu <span>Contrato</span></h1>
                <p class="areaProfessor__sub">Contrato de prestação de serviços</p>
            </div>
            <span class="areaProfessor__badge">Professor</span>
        </div>

        <?php if (!$contrato): ?>

        <div class="meuContrato__vazio">
            <span class="meuContrato__vazioIcon">📄</span>
            <p>Nenhum contrato foi enviado para você ainda.</p>
            <p class="meuContrato__vazioSub">Quando seu contrato estiver disponível, você poderá visualizá-lo e assiná-lo aqui.</p>
        </div>

        <?php elseif ($assinado): ?>

        <div class="meuContrato__statusCard meuContrato__statusCard--assinado">
            <span class="meuContrato__statusIcon">✅</span>
            <div class="meuContrato__statusInfo">
                <div class="meuContrato__statusLabel">Contrato assinado digitalmente</div>
                <div class="meuContrato__statusSub">Assinado em <?= fmtDtContratoPr($contrato['assinado_em']) ?></div>
            </div>
        </div>

        <div class="meuContrato__card">
            <div class="meuContrato__cardHeader">
                <span>Dados da assinatura</span>
            </div>
            <div class="meuContrato__cardBody">
                <div class="meuContrato__row">
                    <span class="meuContrato__rowLabel">Assinado por</span>
                    <span class="meuContrato__rowVal meuContrato__rowVal--cursive"><?= htmlspecialchars($contrato['assinado_nome']) ?></span>
                </div>
                <div class="meuContrato__row">
                    <span class="meuContrato__rowLabel">CPF</span>
                    <span class="meuContrato__rowVal"><?= maskCpfContratoPr($contrato['assinado_cpf']) ?></span>
                </div>
                <div class="meuContrato__row">
                    <span class="meuContrato__rowLabel">Data/hora</span>
                    <span class="meuContrato__rowVal"><?= fmtDtContratoPr($contrato['assinado_em']) ?></span>
                </div>
                <div class="meuContrato__row">
                    <span class="meuContrato__rowLabel">Contrato enviado em</span>
                    <span class="meuContrato__rowVal"><?= fmtDtContratoPr($contrato['criado_em']) ?></span>
                </div>
            </div>
            <div class="meuContrato__cardFooter">
                <a href="<?= htmlspecialchars($sigUrl) ?>" target="_blank" class="btn btn--primary">
                    Ver contrato assinado
                </a>
            </div>
        </div>

        <!-- Assinatura da empresa -->
        <?php if ($assinadoEmpresa): ?>
        <div class="meuContrato__statusCard meuContrato__statusCard--assinado">
            <span class="meuContrato__statusIcon">🏢</span>
            <div class="meuContrato__statusInfo">
                <div class="meuContrato__statusLabel">MPG Academy assinou: <?= htmlspecialchars($contrato['assinado_empresa_nome']) ?></div>
                <div class="meuContrato__statusSub">Em <?= fmtDtContratoPr($contrato['assinado_empresa_em']) ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="meuContrato__statusCard meuContrato__statusCard--pendente">
            <span class="meuContrato__statusIcon">🏢</span>
            <div class="meuContrato__statusInfo">
                <div class="meuContrato__statusLabel">Aguardando assinatura da MPG Academy</div>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>

        <div class="meuContrato__statusCard meuContrato__statusCard--pendente">
            <span class="meuContrato__statusIcon">✍️</span>
            <div class="meuContrato__statusInfo">
                <div class="meuContrato__statusLabel">Aguardando sua assinatura</div>
                <div class="meuContrato__statusSub">Seu contrato está disponível para leitura e assinatura digital</div>
            </div>
        </div>

        <div class="meuContrato__card">
            <div class="meuContrato__cardHeader">
                <span>Contrato disponível</span>
            </div>
            <div class="meuContrato__cardBody">
                <div class="meuContrato__row">
                    <span class="meuContrato__rowLabel">Enviado em</span>
                    <span class="meuContrato__rowVal"><?= fmtDtContratoPr($contrato['criado_em']) ?></span>
                </div>
                <div class="meuContrato__row">
                    <span class="meuContrato__rowLabel">Status</span>
                    <span class="meuContrato__rowVal">
                        <span class="meuContrato__badge meuContrato__badge--pendente">Aguardando assinatura</span>
                    </span>
                </div>
            </div>
            <div class="meuContrato__cardFooter">
                <a href="<?= htmlspecialchars($sigUrl) ?>" target="_blank" class="btn btn--primary">
                    Ler e assinar contrato
                </a>
            </div>
        </div>

        <?php endif; ?>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";</script>
</body>
</html>
