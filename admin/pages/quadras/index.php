<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$quadra = null;

if ($id > 0) {
    require_once ROOT . '/config/database.php';
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT * FROM quadras WHERE id = ?");
    $stmt->execute([$id]);
    $quadra = $stmt->fetch();

    if (!$quadra) {
        header('Location: ' . BASE_URL . '/admin/quadras');
        exit;
    }

    $horarios = $pdo->prepare("SELECT * FROM quadra_horarios WHERE quadra_id = ? ORDER BY dia_semana, hora_inicio");
    $horarios->execute([$id]);
    $horarios = $horarios->fetchAll();

    $turmas = $pdo->prepare("
        SELECT t.id, t.nome, t.genero, t.nivel, t.valor_mensalidade, t.promo_valor, t.promo_meses,
               GROUP_CONCAT(th.horario_id ORDER BY qh.dia_semana, qh.hora_inicio SEPARATOR ',') AS horario_ids
        FROM turmas t
        LEFT JOIN turma_horarios th ON th.turma_id = t.id
        LEFT JOIN quadra_horarios qh ON qh.id = th.horario_id
        WHERE t.quadra_id = ?
        GROUP BY t.id
        ORDER BY t.id
    ");
    $turmas->execute([$id]);
    $turmas = $turmas->fetchAll();

    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $horarioMap = [];
    foreach ($horarios as $h) {
        $horarioMap[$h['id']] = $h;
    }

    $docsStmt = $pdo->prepare("SELECT * FROM quadra_documentos WHERE quadra_id = ? ORDER BY created_at DESC");
    $docsStmt->execute([$id]);
    $documentos = $docsStmt->fetchAll();
}

$dias = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$diasCurto = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Quadras</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <?php if ($quadra): ?>

        <!-- ── DETALHE DA QUADRA ─────────────────────────────── -->
        <section class="quadras">

            <div class="row quadras__header">
                <div class="col-md-8">
                    <a href="<?= BASE_URL ?>/admin/quadras" class="quadras__back">&#8592; Voltar para Quadras</a>
                    <h2><?= htmlspecialchars($quadra['nome']) ?></h2>
                    <p><?= htmlspecialchars($quadra['cidade']) ?> / <?= htmlspecialchars($quadra['estado']) ?></p>
                </div>
                <div class="col-md-4 quadras__headerActions">
                    <span class="statusBadge statusBadge--<?= $quadra['status'] ?>"><?= strtoupper($quadra['status']) ?></span>
                    <a href="<?= BASE_URL ?>/admin/cadastrarquadra?id=<?= $id ?>" class="btn btn--primary">Editar</a>
                    <button type="button" class="btn btn--error" id="btnDeletarQuadra">Excluir</button>
                </div>
            </div>

            <div class="row">

                <!-- Dados básicos -->
                <div class="col-md-6">
                    <div class="quadras__card">
                        <h4 class="quadras__cardTitle">Dados da Quadra</h4>
                        <ul class="quadras__infoList">
                            <li><span>Nome</span><strong><?= htmlspecialchars($quadra['nome']) ?></strong></li>
                            <li><span>Telefone</span><strong><?= htmlspecialchars($quadra['telefone']) ?></strong></li>
                            <li><span>E-mail</span><strong><?= $quadra['email'] ? htmlspecialchars($quadra['email']) : '—' ?></strong></li>
                            <li><span>Instagram</span><strong><?php if ($quadra['instagram']): ?><a href="<?= htmlspecialchars($quadra['instagram']) ?>" target="_blank"><?= htmlspecialchars($quadra['instagram']) ?></a><?php else: ?>—<?php endif; ?></strong></li>
                            <li><span>Endereço</span><strong><?= htmlspecialchars($quadra['rua'] . ', ' . $quadra['numero']) ?><?= $quadra['complemento'] ? ' — ' . htmlspecialchars($quadra['complemento']) : '' ?></strong></li>
                            <li><span>Bairro</span><strong><?= htmlspecialchars($quadra['bairro']) ?></strong></li>
                            <li><span>Cidade / UF</span><strong><?= htmlspecialchars($quadra['cidade'] . ' / ' . $quadra['estado']) ?></strong></li>
                            <li><span>CEP</span><strong><?= htmlspecialchars($quadra['cep']) ?></strong></li>
                        </ul>
                    </div>
                </div>

                <!-- Financeiro -->
                <div class="col-md-6">
                    <div class="quadras__card">
                        <h4 class="quadras__cardTitle">Financeiro</h4>
                        <ul class="quadras__infoList">
                            <li><span>Valor mensal</span><strong class="quadras__valor">R$ <?= number_format($quadra['valor_mensal'], 2, ',', '.') ?></strong></li>
                            <li><span>Vencimento</span><strong>Todo dia <?= $quadra['dia_pagamento'] ?></strong></li>
                            <?php if (!empty($quadra['data_inicio_contrato'])): ?>
                            <li><span>Início do contrato</span><strong><?= date('d/m/Y', strtotime($quadra['data_inicio_contrato'])) ?></strong></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Horários contratados -->
                    <div class="quadras__card">
                        <h4 class="quadras__cardTitle">Horários Contratados</h4>
                        <?php if ($horarios): ?>
                        <table class="dashTable quadras__horariosTable">
                            <thead>
                                <tr>
                                    <th>Dia</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($horarios as $h): ?>
                                <tr>
                                    <td><strong><?= $diasCurto[$h['dia_semana']] ?></strong></td>
                                    <td><?= substr($h['hora_inicio'], 0, 5) ?></td>
                                    <td><?= substr($h['hora_fim'], 0, 5) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="quadras__empty">Nenhum horário cadastrado.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Turmas -->
            <?php if ($turmas): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="quadras__card">
                        <h4 class="quadras__cardTitle">Turmas</h4>
                        <div class="quadras__turmasGrid">
                            <?php foreach ($turmas as $t):
                                $ids = $t['horario_ids'] ? explode(',', $t['horario_ids']) : [];
                            ?>
                            <div class="quadras__turmaItem">
                                <span class="quadras__turmaNome"><?= htmlspecialchars($t['nome']) ?></span>
                                <div class="quadras__turmaBadges">
                                    <?php
                                    $nivelMap  = ['iniciante' => 'quadras__nivelBadge--ini', 'intermediario' => 'quadras__nivelBadge--int', 'avancado' => 'quadras__nivelBadge--ava'];
                                    $generoMap = ['masculino' => 'quadras__generoBadge--m', 'feminino' => 'quadras__generoBadge--f', 'misto' => 'quadras__generoBadge--x'];
                                    $nivelLabel = ['iniciante' => 'Iniciante', 'intermediario' => 'Intermediário', 'avancado' => 'Avançado'];
                                    ?>
                                    <span class="quadras__nivelBadge <?= $nivelMap[$t['nivel']] ?? '' ?>"><?= $nivelLabel[$t['nivel']] ?? ucfirst($t['nivel']) ?></span>
                                    <span class="quadras__generoBadge <?= $generoMap[$t['genero']] ?? '' ?>"><?= ucfirst($t['genero']) ?></span>
                                </div>
                                <ul class="quadras__turmaHorarios">
                                    <?php foreach ($ids as $hid):
                                        $h = $horarioMap[$hid] ?? null;
                                        if (!$h) continue;
                                    ?>
                                    <li><?= $diasCurto[$h['dia_semana']] ?> &nbsp; <?= substr($h['hora_inicio'],0,5) ?>–<?= substr($h['hora_fim'],0,5) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($t['valor_mensalidade'] !== null): ?>
                                <span class="quadras__turmaValor">R$ <?= number_format($t['valor_mensalidade'], 2, ',', '.') ?>/mês</span>
                                <?php endif; ?>
                                <?php if ($t['promo_valor'] !== null && $t['promo_meses'] !== null): ?>
                                <div class="quadras__turmaPromo">
                                    <span class="quadras__turmaPromoTag">PROMO <?= $t['promo_meses'] ?>m</span>
                                    R$ <?= number_format($t['promo_valor'], 2, ',', '.') ?>/mês
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documentos -->
            <div class="row">
                <div class="col-md-12">
                    <div class="quadras__card">
                        <h4 class="quadras__cardTitle">Documentos / Anexos</h4>
                        <div id="documentosList">
                            <?php if (!empty($documentos)): ?>
                            <?php foreach ($documentos as $doc): ?>
                            <div class="quadras__docItem" data-id="<?= $doc['id'] ?>">
                                <span class="quadras__docIcon"><?php
                                    $m = $doc['tipo_mime'];
                                    if (str_starts_with($m,'image/')) echo '🖼';
                                    elseif ($m === 'application/pdf') echo '📄';
                                    elseif (str_contains($m,'word')) echo '📝';
                                    elseif (str_contains($m,'excel') || str_contains($m,'spreadsheet')) echo '📊';
                                    else echo '📎';
                                ?></span>
                                <div class="quadras__docInfo">
                                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars($doc['caminho']) ?>" target="_blank" class="quadras__docNome"><?= htmlspecialchars($doc['nome_original']) ?></a>
                                    <span class="quadras__docSize"><?php
                                        $b = (int)$doc['tamanho'];
                                        if ($b < 1024) echo $b . ' B';
                                        elseif ($b < 1048576) echo round($b/1024,1) . ' KB';
                                        else echo round($b/1048576,1) . ' MB';
                                    ?></span>
                                </div>
                                <button type="button" class="btn btn--gray quadras__docDelete" data-id="<?= $doc['id'] ?>">✕</button>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="quadras__empty" id="documentosEmpty">Nenhum documento anexado.</p>
                            <?php endif; ?>
                        </div>
                        <div class="quadras__uploadArea">
                            <label for="uploadDocumento" class="quadras__uploadLabel">
                                <span>+ Anexar documento</span>
                                <input type="file" id="uploadDocumento" class="quadras__uploadInput"
                                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt">
                            </label>
                            <div id="uploadProgress" class="quadras__uploadProgress" style="display:none"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal confirmar exclusão da quadra -->
            <div class="confirmModal" id="confirmDeleteModal">
                <div class="confirmModal__box">
                    <h3>Excluir quadra?</h3>
                    <p>Essa ação é irreversível. Todos os horários, turmas e documentos de <strong><?= htmlspecialchars($quadra['nome']) ?></strong> serão excluídos.</p>
                    <div class="confirmModal__actions">
                        <button type="button" class="btn btn--gray" id="btnCancelarDelete">Cancelar</button>
                        <button type="button" class="btn btn--error" id="btnConfirmarDelete">Excluir</button>
                    </div>
                </div>
            </div>

        </section>

        <?php else: ?>

        <!-- ── LISTA DE QUADRAS ───────────────────────────────── -->
        <section class="quadras">

            <div class="row quadras__header">
                <div class="col-md-7">
                    <h2>Quadras <span>Alugadas</span></h2>
                    <p>Locais contratados para treino do MPG Academy.</p>
                </div>
                <div class="col-md-5 quadras__headerActions">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum" id="totalGeral">—</span>
                        <span class="interessados__totalLabel">Total de quadras</span>
                    </div>
                    <a href="<?= BASE_URL ?>/admin/cadastrarquadra" class="btn btn--primary quadras__btnNova">+ Cadastrar quadra</a>
                </div>
            </div>

            <div class="quadras__tools">
                <label class="quadras__searchWrap" for="buscaQuadras">
                    <span>Buscar quadra</span>
                    <input class="input quadras__search" type="text" id="buscaQuadras" placeholder="Nome ou cidade">
                </label>
                <div class="quadras__resultMeta">
                    <span id="resultCount"></span>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable quadras__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th>Telefone</th>
                                    <th>Cidade</th>
                                    <th>Valor/mês</th>
                                    <th>Horários</th>
                                    <th>Turmas</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="quadrasTableBody">
                                <tr><td colspan="9" class="interessados__loading">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="quadras__pagination" id="paginacaoWrap">
                <span class="quadras__paginationLabel">Paginação</span>
                <div class="interessados__paginacaoInner quadras__paginationControls" id="paginacaoControles"></div>
            </div>

        </section>

        <?php endif; ?>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL    = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL          = "<?= BASE_URL ?>";
    var QUADRA_VIEW       = "<?= $quadra ? 'detalhe' : 'lista' ?>";
    var QUADRA_ID         = <?= $id ?>;
    var QUADRA_DOCUMENTOS = <?= isset($documentos) ? json_encode($documentos) : '[]' ?>;
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/quadras/quadras.js?v=' . $version . '"></script>';
?>

</body>
</html>
