<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$acao      = $_GET['acao'] ?? 'lista';
$editId    = (int) ($_GET['id'] ?? 0);
$professor = null;

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM professores WHERE id = ?");
    $st->execute([$editId]);
    $professor = $st->fetch();
    if (!$professor) { header('Location: ' . ADMIN_BASE_URL . '/professores'); exit; }
    $acao = 'editar';
}

$professores = [];
if ($acao === 'lista') {
    $professores = $pdo->query("SELECT * FROM professores ORDER BY nome, sobrenome")->fetchAll();
}

// Turmas disponíveis (para o formulário)
$turmasDisponiveis = [];
if ($acao !== 'lista') {
    $turmasDisponiveis = $pdo->query("
        SELECT t.id, t.nome, t.status, t.data_inicio,
               GROUP_CONCAT(
                   CONCAT(qh.dia_semana, '|', qh.hora_inicio, '|', qh.hora_fim)
                   ORDER BY qh.dia_semana, qh.hora_inicio
                   SEPARATOR ';'
               ) AS horarios_raw
        FROM turmas t
        LEFT JOIN turma_horarios th ON th.turma_id = t.id
        LEFT JOIN quadra_horarios qh ON qh.id = th.horario_id
        WHERE t.status = 'ativa'
        GROUP BY t.id
        ORDER BY t.nome
    ")->fetchAll();

    // Turmas já vinculadas a este professor
    $professorTurmas = [];
    if ($editId > 0) {
        $stPT = $pdo->prepare("SELECT turma_id, data_inicio FROM professor_turmas WHERE professor_id = ?");
        $stPT->execute([$editId]);
        foreach ($stPT->fetchAll() as $row) {
            $professorTurmas[$row['turma_id']] = $row['data_inicio'];
        }
    }
}

$DIAS_PT = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

function fmtNasc(?string $d): string {
    if (!$d) return '';
    return date('d/m/Y', strtotime($d));
}
function fmtBrl(?float $v): string {
    if ($v === null) return '';
    return number_format($v, 2, ',', '.');
}
function fmtBrlLabel(?float $v): string {
    if ($v === null) return '—';
    return 'R$ ' . number_format($v, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Professores</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

    <?php if ($acao === 'lista'): ?>
    <!-- ── LISTA ──────────────────────────────────────────────────────────── -->
    <section class="alunos professores">
        <div class="row alunos__header">
            <div class="col-md-7">
                <h2>Profes<span>sores</span></h2>
                <p>Professores cadastrados na MPG Academy.</p>
            </div>
            <div class="col-md-5 professores__headerActions">
                <a href="<?= ADMIN_BASE_URL ?>/professores?acao=novo" class="btn btn--primary">+ Novo professor</a>
            </div>
        </div>

        <div class="professores__panel">
            <div class="profGrid profGrid--head">
                <span>Nome</span>
                <span>E-mail</span>
                <span>Aula 1h30</span>
                <span>Aula 2h00</span>
                <span>Dia pgto</span>
                <span>Ações</span>
            </div>

            <?php if (empty($professores)): ?>
            <div class="professores__empty">
                Nenhum professor cadastrado.
                <a href="<?= ADMIN_BASE_URL ?>/professores?acao=novo">Cadastrar agora →</a>
            </div>
            <?php endif; ?>

            <?php foreach ($professores as $p): ?>
            <div class="profGrid">
                <div>
                    <span class="profGrid__nome"><?= htmlspecialchars($p['nome'] . ' ' . $p['sobrenome']) ?></span>
                    <small class="profGrid__date"><?= date('d/m/Y', strtotime($p['criado_em'])) ?></small>
                </div>
                <span class="profGrid__email"><?= htmlspecialchars($p['email']) ?></span>
                <span><?= fmtBrlLabel($p['valor_aula_90min'] !== null ? (float)$p['valor_aula_90min'] : null) ?></span>
                <span><?= fmtBrlLabel($p['valor_aula_120min'] !== null ? (float)$p['valor_aula_120min'] : null) ?></span>
                <span><?= $p['dia_pagamento'] ? 'Dia ' . (int)$p['dia_pagamento'] : '—' ?></span>
                <div class="profGrid__actions">
                    <a href="<?= ADMIN_BASE_URL ?>/professores?id=<?= $p['id'] ?>" class="btn btn--sm btn--gray">Editar</a>
                    <button class="btn btn--sm btn--success btnPagarProf"
                            data-id="<?= $p['id'] ?>"
                            data-nome="<?= htmlspecialchars($p['nome'] . ' ' . $p['sobrenome']) ?>">
                        Pagar
                    </button>
                    <button class="btn btn--sm btn--primary btnFaltasProf"
                            data-id="<?= $p['id'] ?>"
                            data-nome="<?= htmlspecialchars($p['nome'] . ' ' . $p['sobrenome']) ?>">
                        Faltas
                    </button>
                    <a href="<?= ADMIN_BASE_URL ?>/frequencia-professor?prof_id=<?= $p['id'] ?>"
                       class="btn btn--sm btn--gray">Frequência</a>
                    <button class="btn btn--sm btn--error btnExcluirProf"
                            data-id="<?= $p['id'] ?>"
                            data-nome="<?= htmlspecialchars($p['nome'] . ' ' . $p['sobrenome']) ?>">
                        Excluir
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php else: ?>
    <!-- ── FORMULÁRIO ─────────────────────────────────────────────────────── -->
    <section class="alunos professores">
        <div class="row alunos__header">
            <div class="col-md-12">
                <a href="<?= ADMIN_BASE_URL ?>/professores" class="alunos__back">&#8592; Voltar para Professores</a>
                <h2><?= $acao === 'editar' ? 'Editar' : 'Novo' ?> <span>Professor</span></h2>
            </div>
        </div>

        <form id="formProfessor" class="profForm">
            <?php if ($professor): ?>
            <input type="hidden" name="id" value="<?= $professor['id'] ?>">
            <?php endif; ?>

            <!-- Nome + Sobrenome -->
            <div class="profForm__grid">
                <div class="profForm__field">
                    <label>Nome <span>*</span></label>
                    <input type="text" name="nome" class="input" placeholder="Nome" required
                           value="<?= htmlspecialchars($professor['nome'] ?? '') ?>">
                </div>
                <div class="profForm__field">
                    <label>Sobrenome <span>*</span></label>
                    <input type="text" name="sobrenome" class="input" placeholder="Sobrenome" required
                           value="<?= htmlspecialchars($professor['sobrenome'] ?? '') ?>">
                </div>
            </div>

            <!-- Email + Senha -->
            <div class="profForm__grid">
                <div class="profForm__field">
                    <label>E-mail <span>*</span></label>
                    <input type="email" name="email" class="input" placeholder="email@exemplo.com" required
                           value="<?= htmlspecialchars($professor['email'] ?? '') ?>">
                </div>
                <div class="profForm__field">
                    <label>Senha <?= $acao === 'editar' ? '' : '<span>*</span>' ?></label>
                    <input type="password" name="senha" class="input"
                           placeholder="<?= $acao === 'editar' ? 'Deixe em branco para não alterar' : 'Senha de acesso' ?>"
                           <?= $acao !== 'editar' ? 'required' : '' ?>>
                </div>
            </div>

            <!-- CPF + Celular + Nascimento -->
            <div class="profForm__grid profForm__grid--3">
                <div class="profForm__field">
                    <label>CPF</label>
                    <input type="text" name="cpf" id="inputCpf" class="input" placeholder="000.000.000-00" maxlength="14"
                           value="<?= htmlspecialchars($professor['cpf'] ?? '') ?>">
                </div>
                <div class="profForm__field">
                    <label>Celular</label>
                    <input type="text" name="celular" id="inputCelular" class="input" placeholder="(00) 00000-0000" maxlength="16"
                           value="<?= htmlspecialchars($professor['celular'] ?? '') ?>">
                </div>
                <div class="profForm__field">
                    <label>Data de Nascimento</label>
                    <input type="text" name="data_nascimento" id="inputNasc" class="input" placeholder="DD/MM/AAAA" maxlength="10"
                           value="<?= fmtNasc($professor['data_nascimento'] ?? null) ?>">
                </div>
            </div>

            <!-- Valor por aula + Dia pagamento + Status -->
            <div class="profForm__sectionLabel">Remuneração</div>
            <div class="profForm__grid profForm__grid--4">
                <div class="profForm__field">
                    <label>Valor aula 1h30 (R$)</label>
                    <input type="text" name="valor_aula_90min" id="inputVal90" class="input" placeholder="0,00"
                           value="<?= fmtBrl(isset($professor['valor_aula_90min']) && $professor['valor_aula_90min'] !== null ? (float)$professor['valor_aula_90min'] : null) ?>">
                </div>
                <div class="profForm__field">
                    <label>Valor aula 2h00 (R$)</label>
                    <input type="text" name="valor_aula_120min" id="inputVal120" class="input" placeholder="0,00"
                           value="<?= fmtBrl(isset($professor['valor_aula_120min']) && $professor['valor_aula_120min'] !== null ? (float)$professor['valor_aula_120min'] : null) ?>">
                </div>
                <div class="profForm__field">
                    <label>Dia de pagamento</label>
                    <select name="dia_pagamento" class="input">
                        <option value="">Selecionar...</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                        <option value="<?= $d ?>" <?= ($professor['dia_pagamento'] ?? '') == $d ? 'selected' : '' ?>>
                            Dia <?= $d ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="profForm__field">
                    <label>Status</label>
                    <select name="status" class="input">
                        <option value="ativo"   <?= ($professor['status'] ?? 'ativo') === 'ativo'   ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($professor['status'] ?? '')      === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
            </div>

            <!-- Turmas -->
            <div class="profForm__sectionLabel">
                Turmas que leciona
            </div>

            <?php if (empty($turmasDisponiveis)): ?>
            <p class="profForm__notice">Nenhuma turma ativa cadastrada.</p>
            <?php else: ?>
            <div class="profForm__turmasList">
                <?php foreach ($turmasDisponiveis as $t):
                    $checked     = isset($professorTurmas[$t['id']]);
                    $dataVinculo = $professorTurmas[$t['id']] ?? null;

                    $horariosStr = '';
                    if ($t['horarios_raw']) {
                        $slots = explode(';', $t['horarios_raw']);
                        $partes = [];
                        foreach ($slots as $s) {
                            list($dia, $hi, $hf) = explode('|', $s);
                            $nomeDia = $DIAS_PT[(int)$dia] ?? '';
                            list($hh, $mm)   = explode(':', $hi);
                            list($hh2, $mm2) = explode(':', $hf);
                            $dur = ((int)$hh2 * 60 + (int)$mm2) - ((int)$hh * 60 + (int)$mm);
                            $partes[] = $nomeDia . ' ' . substr($hi, 0, 5) . '–' . substr($hf, 0, 5) . ' (' . ($dur >= 110 ? '2h00' : '1h30') . ')';
                        }
                        $horariosStr = implode(' · ', $partes);
                    }
                ?>
                <div class="profForm__turmaItem <?= $checked ? 'profForm__turmaItem--checked' : '' ?>">
                    <label class="profForm__turmaLabel">
                        <input type="checkbox" name="turma_ids[]" value="<?= $t['id'] ?>"
                               class="profForm__turmaCheck"
                               <?= $checked ? 'checked' : '' ?>>
                        <div class="profForm__turmaInfo">
                            <span class="profForm__turmaNome"><?= htmlspecialchars($t['nome']) ?></span>
                            <span class="profForm__turmaHorarios"><?= $horariosStr ?: 'Sem horários cadastrados' ?></span>
                        </div>
                    </label>
                    <div class="profForm__turmaDataRow<?= $checked ? '' : ' profForm__turmaDataRow--hidden' ?>">
                        <span>Professor nesta turma desde:</span>
                        <input type="date" class="profForm__turmaDataInput"
                               name="turma_data_inicio[<?= $t['id'] ?>]"
                               value="<?= htmlspecialchars($dataVinculo ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="profForm__actions">
                <button type="submit" class="btn btn--primary" id="btnSalvarProf">
                    <?= $acao === 'editar' ? 'Salvar alterações' : 'Cadastrar professor' ?>
                </button>
                <a href="<?= ADMIN_BASE_URL ?>/professores" class="btn btn--gray">Cancelar</a>
            </div>
            <div id="formProfMsg" class="profForm__msg"></div>
        </form>

        <?php if ($editId > 0): ?>
        <?php
            // Carrega o contrato mais recente do professor
            $stContrato = $pdo->prepare("
                SELECT id, arquivo, token, criado_em,
                       assinado_nome, assinado_cpf, assinado_em,
                       assinado_empresa_nome, assinado_empresa_em
                FROM professor_contratos
                WHERE professor_id = ?
                ORDER BY criado_em DESC
                LIMIT 1
            ");
            $stContrato->execute([$editId]);
            $contrato = $stContrato->fetch(PDO::FETCH_ASSOC);
        ?>
        <div class="profForm" style="margin-top:16px">
            <div class="profForm__sectionLabel">Contrato</div>

            <input type="hidden" id="profIdContrato" value="<?= $editId ?>">

            <?php if (!$contrato): ?>
                <p class="profForm__notice" style="margin-bottom:14px">Nenhum contrato gerado ainda.</p>
                <div>
                    <button type="button" class="btn btn--primary" id="btnGerarContrato">Gerar e enviar contrato</button>
                    <div id="contratoMsg" class="profForm__msg" style="display:none;margin-top:10px"></div>
                </div>

            <?php elseif (empty($contrato['assinado_em'])): ?>
                <div class="profContrato profContrato--pendente">
                    <span class="profContrato__icon">⏳</span>
                    <div class="profContrato__info">
                        <strong>Aguardando assinatura do professor</strong>
                        <span>Gerado em <?= date('d/m/Y', strtotime($contrato['criado_em'])) ?></span>
                    </div>
                </div>
                <?php if (empty($contrato['assinado_empresa_em'])): ?>
                <div class="profContrato profContrato--pendente" style="margin-top:10px">
                    <span class="profContrato__icon">🏢</span>
                    <div class="profContrato__info"><strong>MPG Academy ainda não assinou</strong></div>
                </div>
                <div class="profContrato__actions">
                    <button type="button" class="btn btn--sm btn--primary btnAssinarEmpresa"
                            data-id="<?= $contrato['id'] ?>">Assinar como MPG Academy</button>
                </div>
                <?php else: ?>
                <div class="profContrato profContrato--assinado" style="margin-top:10px">
                    <span class="profContrato__icon">🏢</span>
                    <div class="profContrato__info">
                        <strong>MPG Academy: <?= htmlspecialchars($contrato['assinado_empresa_nome']) ?></strong>
                        <span>Em <?= date('d/m/Y \à\s H:i', strtotime($contrato['assinado_empresa_em'])) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <div class="profContrato__actions">
                    <button type="button" class="btn btn--sm btn--gray" id="btnCopiarLink"
                            data-link="<?= BASE_URL ?>/contrato?token=<?= htmlspecialchars($contrato['token']) ?>">
                        Copiar link de assinatura
                    </button>
                    <a href="<?= BASE_URL ?>/contrato?token=<?= htmlspecialchars($contrato['token']) ?>"
                       target="_blank" class="btn btn--sm btn--gray">Ver contrato</a>
                    <button type="button" class="btn btn--sm btn--primary" id="btnGerarContrato">
                        Regenerar contrato
                    </button>
                </div>
                <div id="contratoMsg" class="profForm__msg" style="display:none;margin-top:10px"></div>

            <?php else: ?>
                <div class="profContrato profContrato--assinado">
                    <span class="profContrato__icon">✅</span>
                    <div class="profContrato__info">
                        <strong>Professor: <?= htmlspecialchars($contrato['assinado_nome']) ?></strong>
                        <span>Em <?= date('d/m/Y \à\s H:i', strtotime($contrato['assinado_em'])) ?></span>
                    </div>
                </div>
                <?php if (empty($contrato['assinado_empresa_em'])): ?>
                <div class="profContrato profContrato--pendente" style="margin-top:10px">
                    <span class="profContrato__icon">🏢</span>
                    <div class="profContrato__info"><strong>MPG Academy ainda não assinou</strong></div>
                </div>
                <div class="profContrato__actions">
                    <button type="button" class="btn btn--sm btn--primary btnAssinarEmpresa"
                            data-id="<?= $contrato['id'] ?>">Assinar como MPG Academy</button>
                </div>
                <?php else: ?>
                <div class="profContrato profContrato--assinado" style="margin-top:10px">
                    <span class="profContrato__icon">🏢</span>
                    <div class="profContrato__info">
                        <strong>MPG Academy: <?= htmlspecialchars($contrato['assinado_empresa_nome']) ?></strong>
                        <span>Em <?= date('d/m/Y \à\s H:i', strtotime($contrato['assinado_empresa_em'])) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <div class="profContrato__actions">
                    <a href="<?= BASE_URL ?>/contrato?token=<?= htmlspecialchars($contrato['token']) ?>"
                       target="_blank" class="btn btn--sm btn--gray">Ver contrato assinado</a>
                    <button type="button" class="btn btn--sm btn--primary" id="btnGerarContrato">
                        Novo contrato
                    </button>
                </div>
                <div id="contratoMsg" class="profForm__msg" style="display:none;margin-top:10px"></div>

            <?php endif; ?>
        </div>
        <?php endif; ?>

    </section>
    <?php endif; ?>

    </main>
</div>

<!-- Modal de pagamento -->
<div class="pgtoModal" id="pgtoModal">
    <div class="pgtoModal__box">
        <div class="pgtoModal__header">
            <h3>Registrar Pagamento &mdash; <span id="pgtoProfNome"></span></h3>
            <button class="pgtoModal__close" id="pgtoModalClose" type="button">✕</button>
        </div>
        <form id="formPgto" autocomplete="off">
            <input type="hidden" name="professor_id" id="pgtoProfId">
            <div class="pgtoModal__grid">
                <div class="pgtoModal__field">
                    <label>Valor pago (R$) <span>*</span></label>
                    <input type="text" name="valor" id="pgtoValor" class="input" placeholder="0,00" required>
                </div>
                <div class="pgtoModal__field">
                    <label>Data do pagamento <span>*</span></label>
                    <input type="date" name="data_pagamento" id="pgtoData" class="input" required>
                </div>
            </div>
            <div class="pgtoModal__field">
                <label>Referência</label>
                <input type="text" name="referencia" class="input" placeholder="Ex: Junho 2026">
            </div>
            <div class="pgtoModal__field">
                <label>Observação</label>
                <input type="text" name="observacao" class="input" placeholder="Observação interna">
            </div>
            <div class="pgtoModal__field">
                <label>Comprovante <small>(PDF, JPG ou PNG — máx. 5&nbsp;MB)</small></label>
                <input type="file" name="comprovante" id="pgtoFile" class="pgtoModal__file" accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <div class="pgtoModal__actions">
                <button type="submit" class="btn btn--success" id="btnSalvarPgto">Registrar pagamento</button>
                <button type="button" class="btn btn--gray" id="pgtoModalClose2">Fechar</button>
            </div>
            <div id="pgtoMsg" class="pgtoModal__msg" style="display:none"></div>
        </form>
        <div class="pgtoModal__lista">
            <h4>Histórico de pagamentos</h4>
            <div id="pgtoLista"></div>
        </div>
    </div>
</div>

<!-- Modal de faltas -->
<div class="faltaModal" id="faltaModal">
    <div class="faltaModal__box">
        <div class="faltaModal__header">
            <h3>Registrar Falta &mdash; <span id="faltaProfNome"></span></h3>
            <button class="faltaModal__close" id="faltaModalClose" type="button">✕</button>
        </div>
        <form id="formFalta" autocomplete="off">
            <input type="hidden" name="professor_id" id="faltaProfId">
            <div class="faltaModal__field">
                <label>Turma</label>
                <select name="turma_id" id="faltaTurma" class="input" required>
                    <option value="">Selecione a turma</option>
                </select>
            </div>
            <div class="faltaModal__grid">
                <div class="faltaModal__field">
                    <label>Data da falta</label>
                    <input type="date" name="data" id="faltaData" class="input" required>
                </div>
                <div class="faltaModal__field">
                    <label>Tipo</label>
                    <select name="tipo" class="input">
                        <option value="planejada">Planejada (com aviso)</option>
                        <option value="sem_aviso">Sem aviso</option>
                    </select>
                </div>
            </div>
            <div class="faltaModal__field">
                <label>Observação (opcional)</label>
                <input type="text" name="observacao" class="input" placeholder="Ex: viagem, evento">
            </div>
            <div class="faltaModal__actions">
                <button type="submit" class="btn btn--primary" id="btnSalvarFalta">Registrar falta</button>
                <button type="button" class="btn btn--gray" id="faltaModalClose2">Fechar</button>
            </div>
            <div id="faltaMsg" class="faltaModal__msg" style="display:none"></div>
        </form>
        <div class="faltaModal__lista">
            <h4>Faltas registradas</h4>
            <div id="faltaLista"></div>
        </div>
    </div>
</div>

<!-- Modal: assinar contrato como MPG Academy -->
<div class="pgtoModal assinarEmpresaModal" id="assinarEmpresaModal">
    <div class="pgtoModal__box assinarEmpresaModal__box">
        <div class="pgtoModal__header">
            <h3>Assinar como MPG Academy</h3>
            <button class="pgtoModal__close" id="fecharAssinarEmpresaModal" type="button">&times;</button>
        </div>
        <div class="pgtoModal__body">
            <input type="hidden" id="assinarEmpresaContratoId">
            <div class="pgtoModal__field">
                <label>Nome completo do signatário *</label>
                <input type="text" id="assinarEmpresaNome" class="input" placeholder="Nome de quem assina pela MPG Academy">
            </div>
            <div class="pgtoModal__field">
                <label>CPF do signatário (opcional)</label>
                <input type="text" id="assinarEmpresaCpf" class="input" placeholder="000.000.000-00" maxlength="14">
            </div>
            <div id="assinarEmpresaMsg" class="pgtoModal__msg assinarEmpresaModal__msg"></div>
        </div>
        <div class="pgtoModal__actions assinarEmpresaModal__actions">
            <button class="btn btn--gray" id="cancelarAssinarEmpresa" type="button">Cancelar</button>
            <button class="btn btn--primary" id="confirmarAssinarEmpresa" type="button">Assinar contrato</button>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="confirmModal" id="confirmExcluirProfModal">
    <div class="confirmModal__box">
        <h3>Excluir Professor</h3>
        <p>Tem certeza que deseja excluir <strong id="confirmProfNome"></strong>?<br>Esta ação não pode ser desfeita.</p>
        <div class="confirmModal__actions">
            <button class="btn btn--gray" id="confirmProfCancelar">Cancelar</button>
            <button class="btn btn--error" id="confirmProfExcluir">Sim, excluir</button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

// ── Máscaras ──────────────────────────────────────────────────────────────────
function maskCpf(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        this.value = v;
    });
}
function maskCelular(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/^(\d{2})(\d)/, '($1) $2');
        v = v.replace(/(\d{5})(\d{1,4})$/, '$1-$2');
        this.value = v;
    });
}
function maskData(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 8);
        v = v.replace(/(\d{2})(\d)/, '$1/$2');
        v = v.replace(/(\d{2})(\d)/, '$1/$2');
        this.value = v;
    });
}
function maskValor(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '');
        if (!v) { this.value = ''; return; }
        v = (parseInt(v, 10) / 100).toFixed(2)
              .replace('.', ',')
              .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        this.value = v;
    });
}

if (document.getElementById('inputCpf'))     maskCpf(document.getElementById('inputCpf'));
if (document.getElementById('inputCelular')) maskCelular(document.getElementById('inputCelular'));
if (document.getElementById('inputNasc'))    maskData(document.getElementById('inputNasc'));
if (document.getElementById('inputVal90'))   maskValor(document.getElementById('inputVal90'));
if (document.getElementById('inputVal120'))  maskValor(document.getElementById('inputVal120'));

// ── Highlight ao marcar turma + exibe/oculta data ────────────────────────────
document.querySelectorAll('.profForm__turmaCheck').forEach(function (chk) {
    chk.addEventListener('change', function () {
        var item    = this.closest('.profForm__turmaItem');
        var dateRow = item.querySelector('.profForm__turmaDataRow');
        item.classList.toggle('profForm__turmaItem--checked', this.checked);
        if (dateRow) dateRow.classList.toggle('profForm__turmaDataRow--hidden', !this.checked);
    });
});

// ── Salvar professor ──────────────────────────────────────────────────────────
var form = document.getElementById('formProfessor');
if (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btnSalvarProf');
        var msg = document.getElementById('formProfMsg');
        btn.disabled    = true;
        btn.textContent = 'Salvando...';
        msg.style.display = 'none';

        var fd = new FormData(this);

        fetch(ADMIN_BASE_URL + '/services/save_professor.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.href = ADMIN_BASE_URL + '/professores';
            } else {
                msg.textContent   = data.message || 'Erro ao salvar.';
                msg.style.color   = '#cf7e7e';
                msg.style.display = '';
                btn.disabled      = false;
                btn.textContent   = 'Salvar';
            }
        })
        .catch(function () {
            msg.textContent   = 'Erro de comunicação.';
            msg.style.color   = '#cf7e7e';
            msg.style.display = '';
            btn.disabled      = false;
            btn.textContent   = 'Salvar';
        });
    });
}

// Máscara no valor do pagamento (mesmo helper definido acima para o formulário)
if (document.getElementById('pgtoValor')) maskValor(document.getElementById('pgtoValor'));

// ── Pagamentos do professor ───────────────────────────────────────────────────
var pgtoModal     = document.getElementById('pgtoModal');
var pgtoProfAtual = 0;

function carregarPagamentos(profId) {
    document.getElementById('pgtoLista').innerHTML = '<span class="pgtoModal__empty">Carregando...</span>';
    fetch(ADMIN_BASE_URL + '/services/get_pagamentos_professor.php?professor_id=' + profId, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            if (!data.pagamentos.length) {
                document.getElementById('pgtoLista').innerHTML = '<span class="pgtoModal__empty">Nenhum pagamento registrado.</span>';
                return;
            }
            var baseComp = ADMIN_BASE_URL.replace('/admin', '') + '/uploads/comprovantes/';
            document.getElementById('pgtoLista').innerHTML = data.pagamentos.map(function (p) {
                var valor = 'R$ ' + parseFloat(p.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                var comp  = p.comprovante
                    ? '<a href="' + baseComp + p.comprovante + '" target="_blank" class="pgtoItem__comp">Comprovante ↗</a>'
                    : '';
                return '<div class="pgtoItem">' +
                    '<div class="pgtoItem__info">' +
                        '<span class="pgtoItem__valor">' + valor + '</span>' +
                        '<div class="pgtoItem__meta">' +
                            '<span class="pgtoItem__data">' + formatDateBR(p.data_pagamento) + '</span>' +
                            (p.referencia ? '<span class="pgtoItem__ref">' + p.referencia + '</span>' : '') +
                            (p.observacao ? '<span class="pgtoItem__obs">' + p.observacao + '</span>' : '') +
                        '</div>' +
                        comp +
                    '</div>' +
                    '<button class="btn btn--sm btn--error btnDelPgto" data-id="' + p.id + '" type="button">✕</button>' +
                '</div>';
            }).join('');
        });
}

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnPagarProf');
    if (!btn) return;
    pgtoProfAtual = btn.dataset.id;
    document.getElementById('pgtoProfNome').textContent = btn.dataset.nome;
    document.getElementById('pgtoProfId').value         = pgtoProfAtual;
    document.getElementById('pgtoData').value           = new Date().toISOString().slice(0, 10);
    document.getElementById('pgtoMsg').style.display    = 'none';
    pgtoModal.classList.add('pgtoModal--open');
    carregarPagamentos(pgtoProfAtual);
});

function fecharPgtoModal() {
    pgtoModal.classList.remove('pgtoModal--open');
    pgtoProfAtual = 0;
}
document.getElementById('pgtoModalClose')?.addEventListener('click',  fecharPgtoModal);
document.getElementById('pgtoModalClose2')?.addEventListener('click', fecharPgtoModal);
pgtoModal?.addEventListener('click', function (e) { if (e.target === this) fecharPgtoModal(); });

document.getElementById('formPgto')?.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btnSalvarPgto');
    var msg = document.getElementById('pgtoMsg');
    btn.disabled    = true;
    btn.textContent = 'Registrando...';
    msg.style.display = 'none';

    fetch(ADMIN_BASE_URL + '/services/save_pagamento_professor.php', {
        method: 'POST', credentials: 'same-origin', body: new FormData(this),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            document.getElementById('formPgto').reset();
            document.getElementById('pgtoProfId').value = pgtoProfAtual;
            carregarPagamentos(pgtoProfAtual);
            msg.textContent   = 'Pagamento registrado com sucesso!';
            msg.style.color   = '#4ade80';
        } else {
            msg.textContent   = data.message || 'Erro ao registrar.';
            msg.style.color   = '#cf7e7e';
        }
        msg.style.display = '';
        btn.disabled      = false;
        btn.textContent   = 'Registrar pagamento';
    });
});

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnDelPgto');
    if (!btn) return;
    if (!confirm('Remover este pagamento?')) return;
    var fd = new FormData(); fd.append('id', btn.dataset.id);
    fetch(ADMIN_BASE_URL + '/services/delete_pagamento_professor.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) { if (data.success) carregarPagamentos(pgtoProfAtual); });
});

// ── Faltas do professor ───────────────────────────────────────────────────────
var faltaModal     = document.getElementById('faltaModal');
var faltaProfAtual = 0;

function formatDateBR(d) {
    if (!d) return '';
    var p = d.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}

function carregarFaltas(profId) {
    document.getElementById('faltaTurma').innerHTML = '<option value="">Carregando...</option>';
    document.getElementById('faltaLista').innerHTML  = '<span class="faltaModal__empty">Carregando...</span>';

    fetch(ADMIN_BASE_URL + '/services/get_faltas_professor.php?professor_id=' + profId, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;

            // Turmas
            var sel = '<option value="">Selecione a turma</option>';
            data.turmas.forEach(function (t) {
                sel += '<option value="' + t.turma_id + '">' + t.turma_nome + '</option>';
            });
            document.getElementById('faltaTurma').innerHTML = sel;

            // Lista de faltas
            if (!data.faltas.length) {
                document.getElementById('faltaLista').innerHTML = '<span class="faltaModal__empty">Nenhuma falta registrada.</span>';
                return;
            }
            document.getElementById('faltaLista').innerHTML = data.faltas.map(function (f) {
                var tipoLabel = f.tipo === 'planejada' ? 'Planejada' : 'Sem aviso';
                var tipoClass = f.tipo === 'planejada' ? 'faltaItem__tipo--plan' : 'faltaItem__tipo--sem';
                return '<div class="faltaItem">' +
                    '<div class="faltaItem__info">' +
                        '<span class="faltaItem__data">' + formatDateBR(f.data) + '</span>' +
                        '<span class="faltaItem__turma">' + f.turma_nome + '</span>' +
                        '<span class="faltaItem__tipo ' + tipoClass + '">' + tipoLabel + '</span>' +
                        (f.observacao ? '<span class="faltaItem__obs">' + f.observacao + '</span>' : '') +
                    '</div>' +
                    '<button class="btn btn--sm btn--error btnDelFalta" data-id="' + f.id + '" type="button">✕</button>' +
                '</div>';
            }).join('');
        });
}

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnFaltasProf');
    if (!btn) return;
    faltaProfAtual = btn.dataset.id;
    document.getElementById('faltaProfNome').textContent = btn.dataset.nome;
    document.getElementById('faltaProfId').value         = faltaProfAtual;
    document.getElementById('faltaData').value           = new Date().toISOString().slice(0, 10);
    document.getElementById('faltaMsg').style.display    = 'none';
    faltaModal.classList.add('faltaModal--open');
    carregarFaltas(faltaProfAtual);
});

function fecharFaltaModal() {
    faltaModal.classList.remove('faltaModal--open');
    faltaProfAtual = 0;
}
document.getElementById('faltaModalClose')?.addEventListener('click',  fecharFaltaModal);
document.getElementById('faltaModalClose2')?.addEventListener('click', fecharFaltaModal);
faltaModal?.addEventListener('click', function (e) { if (e.target === this) fecharFaltaModal(); });

document.getElementById('formFalta')?.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btnSalvarFalta');
    var msg = document.getElementById('faltaMsg');
    btn.disabled    = true;
    btn.textContent = 'Registrando...';
    msg.style.display = 'none';

    fetch(ADMIN_BASE_URL + '/services/save_falta_professor.php', {
        method: 'POST', credentials: 'same-origin', body: new FormData(this),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            document.getElementById('formFalta').reset();
            document.getElementById('faltaProfId').value = faltaProfAtual;
            carregarFaltas(faltaProfAtual);
            msg.textContent   = 'Falta registrada com sucesso!';
            msg.style.color   = '#22c55e';
        } else {
            msg.textContent   = data.message || 'Erro ao registrar falta.';
            msg.style.color   = '#cf7e7e';
        }
        msg.style.display = '';
        btn.disabled      = false;
        btn.textContent   = 'Registrar falta';
    });
});

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnDelFalta');
    if (!btn) return;
    if (!confirm('Remover esta falta?')) return;
    var fd = new FormData(); fd.append('id', btn.dataset.id);
    fetch(ADMIN_BASE_URL + '/services/delete_falta_professor.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) { if (data.success) carregarFaltas(faltaProfAtual); });
});

// ── Excluir professor ─────────────────────────────────────────────────────────
var excluirId = 0;
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnExcluirProf');
    if (!btn) return;
    excluirId = btn.dataset.id;
    document.getElementById('confirmProfNome').textContent = btn.dataset.nome;
    document.getElementById('confirmExcluirProfModal').classList.add('confirmModal--open');
});
// ── Contrato do professor ─────────────────────────────────────────────────────
document.getElementById('btnCopiarLink')?.addEventListener('click', function () {
    var link = this.dataset.link;
    navigator.clipboard.writeText(link).then(function () {
        var btn = document.getElementById('btnCopiarLink');
        if (!btn) return;
        var orig = btn.textContent;
        btn.textContent = '✓ Link copiado!';
        setTimeout(function () { btn.textContent = orig; }, 2500);
    });
});

document.getElementById('btnGerarContrato')?.addEventListener('click', function () {
    var profId = document.getElementById('profIdContrato')?.value;
    var msg    = document.getElementById('contratoMsg');
    var btn    = this;

    if (!confirm('Isso vai gerar (ou substituir) o contrato com o conteúdo atual do professor. Continuar?')) return;

    btn.disabled = true; btn.textContent = 'Gerando...';
    msg.style.display = 'none';

    var fd = new FormData();
    fd.append('professor_id', profId);

    fetch(ADMIN_BASE_URL + '/services/gerar_contrato_professor.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            window.location.reload();
        } else {
            msg.textContent   = data.message || 'Erro ao gerar contrato.';
            msg.style.color   = '#cf7e7e';
            msg.style.display = '';
            btn.disabled      = false;
            btn.textContent   = 'Gerar contrato';
        }
    });
});

// ── Assinar como MPG Academy ──────────────────────────────────────────────────
document.querySelectorAll('.btnAssinarEmpresa').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('assinarEmpresaContratoId').value = this.dataset.id;
        document.getElementById('assinarEmpresaNome').value = '';
        document.getElementById('assinarEmpresaCpf').value  = '';
        document.getElementById('assinarEmpresaMsg').style.display = 'none';
        document.getElementById('assinarEmpresaModal').classList.add('pgtoModal--open');
    });
});
['fecharAssinarEmpresaModal','cancelarAssinarEmpresa'].forEach(function(id) {
    document.getElementById(id)?.addEventListener('click', function() {
        document.getElementById('assinarEmpresaModal').classList.remove('pgtoModal--open');
    });
});
document.getElementById('assinarEmpresaModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('pgtoModal--open');
});
document.getElementById('assinarEmpresaCpf')?.addEventListener('input', function() {
    var v = this.value.replace(/\D/g,'').slice(0,11);
    v = v.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    this.value = v;
});
document.getElementById('confirmarAssinarEmpresa')?.addEventListener('click', function() {
    var contratoId = document.getElementById('assinarEmpresaContratoId').value;
    var nome       = document.getElementById('assinarEmpresaNome').value.trim();
    var cpf        = document.getElementById('assinarEmpresaCpf').value.trim();
    var msg        = document.getElementById('assinarEmpresaMsg');
    if (!nome) { msg.textContent='Informe o nome do signatário.'; msg.style.color='#cf7e7e'; msg.style.display=''; return; }
    this.disabled = true; this.textContent = 'Assinando...';
    msg.style.display = 'none';
    var fd = new FormData();
    fd.append('contrato_id', contratoId);
    fd.append('nome', nome);
    fd.append('cpf',  cpf);
    fetch(ADMIN_BASE_URL + '/services/assinar_contrato_empresa.php', { method:'POST', credentials:'same-origin', body:fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { window.location.reload(); }
            else { msg.textContent = data.message || 'Erro.'; msg.style.color='#cf7e7e'; msg.style.display=''; this.disabled=false; this.textContent='Assinar contrato'; }
        });
});

document.getElementById('confirmProfCancelar')?.addEventListener('click', function () {
    document.getElementById('confirmExcluirProfModal').classList.remove('confirmModal--open');
    excluirId = 0;
});
document.getElementById('confirmProfExcluir')?.addEventListener('click', function () {
    var btn = this;
    btn.disabled = true; btn.textContent = 'Excluindo...';
    var fd = new FormData(); fd.append('id', excluirId);
    fetch(ADMIN_BASE_URL + '/services/delete_professor.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) window.location.reload();
        else { alert(data.message || 'Erro.'); btn.disabled = false; btn.textContent = 'Sim, excluir'; }
    });
});
</script>
</body>
</html>
