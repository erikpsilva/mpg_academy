<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$aluno = null;

if ($id > 0) {
    require_once ROOT . '/config/database.php';
    $pdo   = getDbConnection();
    $stmt  = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
    $stmt->execute([$id]);
    $aluno = $stmt->fetch();

    if (!$aluno) {
        header('Location: ' . BASE_URL . '/admin/alunos');
        exit;
    }

    $turmasStmt = $pdo->prepare("
        SELECT t.id, t.nome, t.valor_mensalidade, t.promo_valor, t.promo_meses,
               q.nome AS quadra_nome, ta.data_entrada,
               ta.desconto, ta.desconto_tipo, ta.desconto_inicio, ta.desconto_fim, ta.desconto_vitalicio
        FROM turma_alunos ta
        JOIN turmas t ON t.id = ta.turma_id
        JOIN quadras q ON q.id = t.quadra_id
        WHERE ta.aluno_id = ? AND ta.status = 'ativo'
        ORDER BY t.nome
    ");
    $turmasStmt->execute([$id]);
    $turmasDoAluno = $turmasStmt->fetchAll();

    // Busca termo de responsabilidade (caso tenha feito aula teste como menor de idade)
    $termoStmt = $pdo->prepare("
        SELECT ts.token,
               ts.assinante_escola_nome, ts.assinado_escola_em,
               ts.responsavel_nome_assinado, ts.assinado_responsavel_em,
               at.responsavel_nome, at.responsavel_email, at.responsavel_cpf,
               at.data_nascimento,
               t.nome AS turma_teste_nome
        FROM alunos a
        JOIN alunos_teste at ON at.email = a.email AND at.is_menor = 1
        JOIN aulas_experimentais ae ON ae.aluno_teste_id = at.id
        JOIN turmas t ON t.id = ae.turma_id
        LEFT JOIN termo_assinaturas ts ON ts.aula_experimental_id = ae.id
        WHERE a.id = ?
        ORDER BY ae.criado_em DESC
        LIMIT 1
    ");
    $termoStmt->execute([$id]);
    $termoAluno = $termoStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($termoAluno) {
        $escSigned  = !empty($termoAluno['assinado_escola_em']);
        $respSigned = !empty($termoAluno['assinado_responsavel_em']);
        if ($escSigned && $respSigned)   $termoAluno['status_label'] = 'Concluído';
        elseif ($escSigned)              $termoAluno['status_label'] = 'Aguardando responsável';
        elseif ($respSigned)             $termoAluno['status_label'] = 'Aguardando escola';
        elseif ($termoAluno['token'])    $termoAluno['status_label'] = 'Gerado / Pendente';
        else                             $termoAluno['status_label'] = 'Não gerado';

        $termoAluno['status_class'] = ($escSigned && $respSigned) ? 'ok'
            : (($escSigned || $respSigned) ? 'meio' : 'pendente');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Alunos</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <?php if ($aluno): ?>

        <!-- ── DETALHE DO ALUNO ──────────────────────────────── -->
        <section class="alunos">

            <div class="row alunos__header">
                <div class="col-md-8">
                    <a href="<?= BASE_URL ?>/admin/alunos" class="alunos__back">&#8592; Voltar para Alunos</a>
                    <h2>Detalhe do <span>Aluno</span></h2>
                    <p>Informações completas do aluno cadastrado.</p>
                </div>
                <?php if ($_SESSION['usuario']['nivel_acesso'] === 'admin'): ?>
                <div class="col-md-4 alunos__headerActions">
                    <button class="btn btn--error"
                            id="btnExcluir"
                            data-id="<?= $aluno['id'] ?>"
                            data-nome="<?= htmlspecialchars($aluno['nome']) ?>">
                        Excluir Aluno
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="alunos__detailLayout">
                <div class="alunos__detailMain">
                    <div class="alunos__detalheCard alunos__profileCard">
                        <div class="alunos__profileTop <?= empty($aluno['foto']) ? 'alunos__profileTop--noPhoto' : '' ?>">
                            <?php if (!empty($aluno['foto'])): ?>
                            <div class="alunos__fotoCard alunos__fotoCard--compact">
                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($aluno['foto']) ?>"
                                     alt="Foto de <?= htmlspecialchars($aluno['nome']) ?>"
                                     class="alunos__foto">
                            </div>
                            <?php endif; ?>
                            <div class="alunos__profileSummary">
                                <span class="alunos__profileLabel">Aluno</span>
                                <h3><?= htmlspecialchars($aluno['nome']) ?></h3>
                                <p><?= htmlspecialchars($aluno['email']) ?></p>
                                <dl>
                                    <div>
                                        <dt>Status</dt>
                                        <dd><span class="statusBadge statusBadge--<?= $aluno['status'] ?>"><?= strtoupper($aluno['status']) ?></span></dd>
                                    </div>
                                    <div>
                                        <dt>Origem</dt>
                                        <dd><?= htmlspecialchars($aluno['origem'] ?? '---') ?></dd>
                                    </div>
                                    <div>
                                        <dt>Cadastrado em</dt>
                                        <dd><?= date('d/m/Y H:i', strtotime($aluno['criado_em'])) ?></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="alunos__detailColumns">
                            <div class="alunos__detalheGrupo">
                                <h4>Dados Pessoais</h4>
                                <ul class="alunos__detalheList">
                                    <li><span>Nome</span><strong><?= htmlspecialchars($aluno['nome']) ?></strong></li>
                                    <li><span>E-mail</span><strong><?= htmlspecialchars($aluno['email']) ?></strong></li>
                                    <li><span>CPF</span><strong><?= htmlspecialchars($aluno['cpf']) ?></strong></li>
                                    <li><span>Nascimento</span><strong><?= $aluno['nascimento'] ? date('d/m/Y', strtotime($aluno['nascimento'])) : '---' ?></strong></li>
                                    <li><span>Sexo</span><strong><?= ucfirst($aluno['sexo']) ?></strong></li>
                                    <li><span>Atualizado em</span><strong><?= date('d/m/Y H:i', strtotime($aluno['atualizado_em'])) ?></strong></li>
                                </ul>
                            </div>
                            <div class="alunos__detalheGrupo">
                                <h4>Contato &amp; Endere&ccedil;o</h4>
                                <ul class="alunos__detalheList">
                                    <li><span>Celular</span><strong><?= htmlspecialchars($aluno['celular']) ?></strong></li>
                                    <li><span>WhatsApp</span><strong><?= htmlspecialchars($aluno['whatsapp']) ?></strong></li>
                                    <li><span>CEP</span><strong><?= htmlspecialchars($aluno['cep']) ?></strong></li>
                                    <li><span>Endere&ccedil;o</span><strong><?= htmlspecialchars($aluno['rua'] . ', ' . $aluno['numero']) ?><?= $aluno['complemento'] ? ' - ' . htmlspecialchars($aluno['complemento']) : '' ?></strong></li>
                                    <li><span>Bairro</span><strong><?= htmlspecialchars($aluno['bairro']) ?></strong></li>
                                    <li><span>Cidade / UF</span><strong><?= htmlspecialchars($aluno['cidade'] . ' / ' . $aluno['estado']) ?></strong></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="alunos__detailAside">

                    <?php if ($termoAluno): ?>
                    <div class="alunos__detalheCard alunos__termoCard">
                        <div class="alunos__termoHeader">
                            <h4>Termo de Responsabilidade</h4>
                            <span class="alunos__termoBadge alunos__termoBadge--<?= $termoAluno['status_class'] ?>">
                                <?= htmlspecialchars($termoAluno['status_label']) ?>
                            </span>
                        </div>
                        <ul class="alunos__detalheList">
                            <li><span>Responsável</span><strong><?= htmlspecialchars($termoAluno['responsavel_nome'] ?? '—') ?></strong></li>
                            <li><span>E-mail resp.</span><strong><?= htmlspecialchars($termoAluno['responsavel_email'] ?? '—') ?></strong></li>
                            <?php if ($termoAluno['responsavel_cpf']): ?>
                            <li><span>CPF resp.</span><strong><?= htmlspecialchars($termoAluno['responsavel_cpf']) ?></strong></li>
                            <?php endif; ?>
                            <?php if ($termoAluno['data_nascimento']): ?>
                            <li><span>Nasc. aluno</span><strong><?= date('d/m/Y', strtotime($termoAluno['data_nascimento'])) ?></strong></li>
                            <?php endif; ?>
                            <li><span>Turma do teste</span><strong><?= htmlspecialchars($termoAluno['turma_teste_nome'] ?? '—') ?></strong></li>
                        </ul>
                        <?php if ($termoAluno['assinante_escola_nome']): ?>
                        <div class="alunos__termoAssinatura">
                            <span>Ass. escola</span>
                            <strong><?= htmlspecialchars($termoAluno['assinante_escola_nome']) ?></strong>
                            <small><?= date('d/m/Y', strtotime($termoAluno['assinado_escola_em'])) ?></small>
                        </div>
                        <?php endif; ?>
                        <?php if ($termoAluno['responsavel_nome_assinado']): ?>
                        <div class="alunos__termoAssinatura">
                            <span>Ass. responsável</span>
                            <strong><?= htmlspecialchars($termoAluno['responsavel_nome_assinado']) ?></strong>
                            <small><?= date('d/m/Y', strtotime($termoAluno['assinado_responsavel_em'])) ?></small>
                        </div>
                        <?php endif; ?>
                        <?php if ($termoAluno['token']): ?>
                        <a class="alunos__termoLink" href="<?= BASE_URL ?>/termo?token=<?= htmlspecialchars($termoAluno['token']) ?>" target="_blank">
                            🔗 Ver / imprimir termo completo
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="alunos__detalheCard alunos__turmasCard">
                        <div class="alunos__turmasHeader">
                            <h4>Turmas e valores</h4>
                        </div>
                        <div id="turmasDoAluno">
                            <?php if (!empty($turmasDoAluno)): ?>
                            <?php foreach ($turmasDoAluno as $t):
                                $valorBase    = $t['valor_mensalidade'];
                                $hoje         = date('Y-m-d');
                                $temDesconto  = $t['desconto'] !== null && $t['desconto'] > 0;
                                $descontoAtivo = $temDesconto && (
                                    $t['desconto_vitalicio'] ||
                                    ($t['desconto_inicio'] === null && $t['desconto_fim'] === null) ||
                                    ($t['desconto_inicio'] <= $hoje && $t['desconto_fim'] >= $hoje)
                                );

                                // Fallback: promo da turma se sem desconto pessoal
                                $promoAtiva = false;
                                if (!$descontoAtivo && $valorBase !== null
                                    && $t['promo_valor'] !== null && $t['promo_meses'] !== null
                                    && (float)$t['promo_valor'] < (float)$valorBase) {
                                    $fimPromo = date('Y-m-d', strtotime($t['data_entrada'] . ' +' . $t['promo_meses'] . ' months'));
                                    $promoAtiva = $fimPromo >= $hoje;
                                }

                                if ($descontoAtivo && $valorBase !== null) {
                                    if ($t['desconto_tipo'] === 'percentual') {
                                        $valorEfetivo = $valorBase * (1 - $t['desconto'] / 100);
                                    } else {
                                        $valorEfetivo = max(0, $valorBase - $t['desconto']);
                                    }
                                } elseif ($promoAtiva) {
                                    $valorEfetivo = (float)$t['promo_valor'];
                                } else {
                                    $valorEfetivo = $valorBase;
                                }
                            ?>
                            <div class="alunos__turmaRow" data-turma-id="<?= $t['id'] ?>">
                                <div class="alunos__turmaInfo">
                                    <strong class="alunos__turmaNome"><?= htmlspecialchars($t['nome']) ?></strong>
                                    <span class="alunos__turmaQuadra"><?= htmlspecialchars($t['quadra_nome']) ?></span>
                                </div>
                                <?php if ($valorBase !== null): ?>
                                <div class="alunos__turmaValorWrap">
                                    <?php if (($descontoAtivo || $promoAtiva) && $valorEfetivo < $valorBase): ?>
                                    <span class="alunos__turmaValorOriginal">R$ <?= number_format($valorBase, 2, ',', '.') ?></span>
                                    <span class="alunos__turmaValor">R$ <?= number_format($valorEfetivo, 2, ',', '.') ?>/m&ecirc;s</span>
                                    <?php else: ?>
                                    <span class="alunos__turmaValor">R$ <?= number_format($valorBase, 2, ',', '.') ?>/m&ecirc;s</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <span class="alunos__turmaDesde">desde <?= date('d/m/Y', strtotime($t['data_entrada'])) ?></span>
                                <div class="alunos__turmaAcoes">
                                    <button type="button" class="btn btn--sm btn--outline alunos__btnDesconto"
                                            data-turma-id="<?= $t['id'] ?>"
                                            data-turma-nome="<?= htmlspecialchars($t['nome']) ?>"
                                            data-valor="<?= $valorBase !== null ? $valorBase : '' ?>"
                                            data-desconto="<?= $t['desconto'] ?? '' ?>"
                                            data-tipo="<?= htmlspecialchars($t['desconto_tipo'] ?? 'fixo') ?>"
                                            data-inicio="<?= htmlspecialchars($t['desconto_inicio'] ?? '') ?>"
                                            data-fim="<?= htmlspecialchars($t['desconto_fim'] ?? '') ?>"
                                            data-vitalicio="<?= $t['desconto_vitalicio'] ? '1' : '0' ?>">
                                        <?= $temDesconto ? 'Editar desconto' : 'Desconto' ?>
                                    </button>
                                    <button type="button" class="btn btn--sm btn--gray alunos__btnRemoverTurma" data-turma-id="<?= $t['id'] ?>">Remover</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="alunos__semTurma" id="semTurmaMsg">Este aluno n&atilde;o est&aacute; em nenhuma turma.</p>
                            <?php endif; ?>
                        </div>
                        <div class="alunos__addTurmaWrap">
                            <select class="input alunos__selectTurma" id="selectAddTurma">
                                <option value="">Selecionar turma para adicionar...</option>
                            </select>
                            <div class="alunos__addTurmaData">
                                <label for="dataInicioTurma">Início da mensalidade</label>
                                <input type="date" class="input" id="dataInicioTurma" value="<?= date('Y-m-d') ?>">
                            </div>
                            <button type="button" class="btn btn--primary" id="btnAddTurma">Adicionar</button>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <!-- Modal de desconto -->
        <div class="confirmModal" id="descontoModal">
            <div class="confirmModal__box descontoModal__box">
                <h3>Desconto na Mensalidade</h3>
                <p class="descontoModal__turmaLabel"><strong id="descontoTurmaNome"></strong></p>
                <input type="hidden" id="descontoTurmaId">

                <div class="descontoModal__form">
                    <div class="descontoModal__row">
                        <label class="descontoModal__label">
                            <span>Valor do desconto</span>
                            <input type="number" class="input descontoModal__input" id="descontoValor" min="0" step="0.01" placeholder="Ex: 50">
                        </label>
                        <label class="descontoModal__label descontoModal__label--tipo">
                            <span>Tipo</span>
                            <select class="input descontoModal__select" id="descontoTipo">
                                <option value="fixo">R$ fixo</option>
                                <option value="percentual">% percentual</option>
                            </select>
                        </label>
                    </div>
                    <div class="descontoModal__preview" id="descontoPreview"></div>
                    <label class="descontoModal__checkLabel">
                        <input type="checkbox" id="descontoVitalicio"> Desconto vitalício (sem prazo)
                    </label>
                    <div class="descontoModal__datas" id="descontoDatas">
                        <label class="descontoModal__label">
                            <span>De</span>
                            <input type="date" class="input descontoModal__input" id="descontoInicio">
                        </label>
                        <label class="descontoModal__label">
                            <span>Até</span>
                            <input type="date" class="input descontoModal__input" id="descontoFim">
                        </label>
                    </div>
                </div>

                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="descontoCancelar">Cancelar</button>
                    <button class="btn btn--gray" id="descontoRemover">Remover desconto</button>
                    <button class="btn btn--primary" id="descontoSalvar">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmação de exclusão -->
        <div class="confirmModal" id="confirmModal">
            <div class="confirmModal__box">
                <h3>Excluir Aluno</h3>
                <p>Tem certeza que deseja excluir <strong id="confirmNome"></strong>?<br>Esta ação não pode ser desfeita.</p>
                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="confirmCancelar">Cancelar</button>
                    <button class="btn btn--error" id="confirmExcluir">Sim, excluir</button>
                </div>
            </div>
        </div>

        <?php else: ?>

        <!-- ── LISTA DE ALUNOS ────────────────────────────────── -->
        <section class="alunos">

            <div class="row alunos__header">
                <div class="col-md-8">
                    <h2>Gerenciar <span>Alunos</span></h2>
                    <p>Alunos cadastrados na plataforma MPG Academy.</p>
                </div>
                <div class="col-md-4">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum" id="totalGeral">—</span>
                        <span class="interessados__totalLabel">Total de alunos</span>
                    </div>
                </div>
            </div>

            <div class="alunos__tools">
                <label class="alunos__searchWrap" for="buscaAlunos">
                    <span>Buscar aluno</span>
                    <input class="input alunos__search"
                           type="text"
                           id="buscaAlunos"
                           placeholder="Nome ou e-mail">
                </label>
                <label class="alunos__filtroWrap" for="filtraTurma">
                    <span>Filtrar por turma</span>
                    <select class="input alunos__filtroTurma" id="filtraTurma">
                        <option value="">Todas as turmas</option>
                    </select>
                </label>
                <div class="alunos__resultMeta">
                    <span id="resultCount"></span>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable alunos__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Turma</th>
                                    <th>Mensalidade</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="alunosTableBody">
                                <tr><td colspan="7" class="interessados__loading">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="alunos__pagination" id="paginacaoWrap">
                <span class="alunos__paginationLabel">Paginação</span>
                <div class="interessados__paginacaoInner alunos__paginationControls" id="paginacaoControles"></div>
            </div>

        </section>

        <?php endif; ?>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL       = "<?= BASE_URL ?>";
    var ALUNO_VIEW     = "<?= $aluno ? 'detalhe' : 'lista' ?>";
    var ALUNO_ID       = <?= $id ?>;
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/alunos/alunos.js?v=' . $version . '"></script>';
?>

</body>
</html>
