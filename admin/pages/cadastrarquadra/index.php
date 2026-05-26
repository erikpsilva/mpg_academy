<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
$editId    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editData  = null;

if ($editId > 0) {
    require_once ROOT . '/config/database.php';
    $pdo  = getDbConnection();

    $stmt = $pdo->prepare("SELECT * FROM quadras WHERE id = ?");
    $stmt->execute([$editId]);
    $editData = $stmt->fetch();

    if (!$editData) {
        header('Location: ' . BASE_URL . '/admin/quadras');
        exit;
    }

    $hStmt = $pdo->prepare("SELECT id, dia_semana, hora_inicio, hora_fim FROM quadra_horarios WHERE quadra_id = ? ORDER BY dia_semana, hora_inicio");
    $hStmt->execute([$editId]);
    $editData['horarios'] = $hStmt->fetchAll();

    $tStmt = $pdo->prepare("
        SELECT t.id, t.nome, t.genero, t.nivel, t.valor_mensalidade, t.promo_valor, t.promo_meses, t.max_alunos, GROUP_CONCAT(th.horario_id ORDER BY th.horario_id SEPARATOR ',') AS horario_ids
        FROM turmas t
        LEFT JOIN turma_horarios th ON th.turma_id = t.id
        WHERE t.quadra_id = ?
        GROUP BY t.id
    ");
    $tStmt->execute([$editId]);
    $rawTurmas = $tStmt->fetchAll();

    $editData['turmas'] = array_map(function($t) {
        $t['horario_ids'] = $t['horario_ids'] ? array_map('intval', explode(',', $t['horario_ids'])) : [];
        return $t;
    }, $rawTurmas);
}

$modoEdicao = $editData !== null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - <?= $modoEdicao ? 'Editar' : 'Cadastrar' ?> Quadra</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="cadastrarQuadra">

            <div class="row cadastrarQuadra__pageHeader">
                <div class="col-md-12">
                    <?php if ($modoEdicao): ?>
                    <a href="<?= BASE_URL ?>/admin/quadras?id=<?= $editId ?>" class="quadras__back">&#8592; Voltar para a Quadra</a>
                    <h2>Editar <span>Quadra</span></h2>
                    <p>Atualize os dados do local contratado para treinos.</p>
                    <?php else: ?>
                    <a href="<?= BASE_URL ?>/admin/quadras" class="quadras__back">&#8592; Voltar para Quadras</a>
                    <h2>Cadastrar <span>Quadra</span></h2>
                    <p>Preencha os dados do local contratado para treinos.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── 1. DADOS DA QUADRA ──────────────────────────────── -->
            <div class="formGroup">
                <div class="row">
                    <div class="col-md-12 formGroup__divisor">
                        <h3>Dados <span>da Quadra</span></h3>
                    </div>
                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label for="nome">Nome *</label>
                            <input class="input" type="text" id="nome" placeholder="Ex: Esporte Clube Orion">
                            <span class="errorText">Digite o nome da quadra</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label for="telefone">Telefone *</label>
                            <input class="input" type="text" id="telefone" placeholder="(11) 99999-9999">
                            <span class="errorText">Digite o telefone</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label for="email">E-mail</label>
                            <input class="input" type="email" id="email" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label for="instagram">Instagram</label>
                            <input class="input" type="text" id="instagram" placeholder="Ex: https://instagram.com/...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 2. ENDEREÇO ─────────────────────────────────────── -->
            <div class="formGroup">
                <div class="row">
                    <div class="col-md-12 formGroup__divisor">
                        <h3>Endereço</h3>
                    </div>
                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label for="cep">CEP *</label>
                            <input class="input" type="text" id="cep" placeholder="00000-000" maxlength="9">
                            <span class="errorText">CEP inválido</span>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="formGroup__item">
                            <label for="rua">Rua *</label>
                            <input class="input" type="text" id="rua" placeholder="Preenchido pelo CEP">
                            <span class="errorText">Digite a rua</span>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="formGroup__item">
                            <label for="numero">Número *</label>
                            <input class="input" type="text" id="numero" placeholder="Ex: 40">
                            <span class="errorText">Digite o número</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label for="bairro">Bairro *</label>
                            <input class="input" type="text" id="bairro" placeholder="Preenchido pelo CEP">
                            <span class="errorText">Digite o bairro</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label for="complemento">Complemento</label>
                            <input class="input" type="text" id="complemento" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label for="cidade">Cidade *</label>
                            <input class="input" type="text" id="cidade" placeholder="Preenchida pelo CEP" readonly>
                            <span class="errorText">Cidade inválida</span>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="formGroup__item">
                            <label for="estado">UF *</label>
                            <input class="input" type="text" id="estado" placeholder="UF" maxlength="2" readonly>
                            <span class="errorText">UF inválida</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 3. FINANCEIRO ───────────────────────────────────── -->
            <div class="formGroup">
                <div class="row">
                    <div class="col-md-12 formGroup__divisor">
                        <h3>Financeiro</h3>
                    </div>
                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label for="valorMensal">Valor mensal (R$) *</label>
                            <input class="input" type="number" id="valorMensal" placeholder="Ex: 1400" min="0" step="0.01">
                            <span class="errorText">Digite o valor mensal</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label for="diaPagamento">Dia de pagamento *</label>
                            <input class="input" type="number" id="diaPagamento" placeholder="Ex: 10" min="1" max="31" value="10">
                            <span class="errorText">Dia inválido (1–31)</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label for="dataInicioContrato">Início do contrato</label>
                            <input class="input" type="date" id="dataInicioContrato">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 4. HORÁRIOS CONTRATADOS ─────────────────────────── -->
            <div class="formGroup">
                <div class="row">
                    <div class="col-md-12 formGroup__divisor">
                        <h3>Horários <span>Contratados</span></h3>
                    </div>
                    <div class="col-md-12">
                        <p class="cadastrarQuadra__hint">Adicione os dias e horários que você contratou neste local.</p>
                        <div id="horariosLista" class="cadastrarQuadra__horariosList"></div>
                        <button type="button" class="btn btn--gray" id="btnAddHorario">+ Adicionar horário</button>
                    </div>
                </div>
            </div>

            <!-- ── 5. TURMAS ───────────────────────────────────────── -->
            <div class="formGroup">
                <div class="row">
                    <div class="col-md-12 formGroup__divisor">
                        <h3>Turmas</h3>
                    </div>
                    <div class="col-md-12">
                        <p class="cadastrarQuadra__hint">Agrupe os horários em turmas. Ex: Segunda + Quarta formam uma turma, Terça + Quinta outra.</p>
                        <div id="turmasLista" class="cadastrarQuadra__turmasList"></div>
                        <button type="button" class="btn btn--gray" id="btnAddTurma">+ Adicionar turma</button>
                    </div>
                </div>
            </div>

            <!-- ── SUBMIT ──────────────────────────────────────────── -->
            <div class="row cadastrarQuadra__submit">
                <div class="col-md-12">
                    <button type="button" class="btn btn--primary" id="btnSalvarQuadra">
                        <?= $modoEdicao ? 'Salvar Alterações' : 'Cadastrar Quadra' ?>
                    </button>
                </div>
            </div>

        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL  = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL        = "<?= BASE_URL ?>";
    var QUADRA_EDIT     = <?= $editData ? json_encode($editData) : 'null' ?>;
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/cadastrarquadra/cadastrarquadra.js?v=' . $version . '"></script>';
?>

</body>
</html>
