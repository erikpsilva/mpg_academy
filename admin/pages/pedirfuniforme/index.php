<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (!in_array($nivel, ['admin', 'editor'], true)) {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/uniformes.php';

$pdo   = getDbConnection();
$valor = uniformeValor($pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Pedir Uniforme</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="pedirUniforme">

            <div class="row pedirUniforme__header">
                <div class="col-md-12">
                    <h2>Pedir <span>Uniforme</span></h2>
                    <p>Faça o pedido pelo aluno que teve dificuldade de usar o formulário sozinho — nesse caso o pagamento entra como <strong>já pago</strong>, então cobre por fora antes de registrar. Pedido de professor ou da equipe MPG é interno: não tem cobrança.</p>
                </div>
            </div>

            <form class="pedirUniforme__form" id="pedirUniformeForm" novalidate>

                <div class="pedirUniforme__block">
                    <h3><span>1</span> Para quem é o pedido</h3>

                    <div class="pedirUniforme__destino" id="destinoBox">
                        <button type="button" class="pedirUniforme__destinoOpt is-active" data-destino="aluno">
                            <strong>Aluno</strong>
                            <small>Uniforme completo, cobrado</small>
                        </button>
                        <button type="button" class="pedirUniforme__destinoOpt" data-destino="equipe">
                            <strong>Professor ou equipe MPG</strong>
                            <small>Interno, sem cobrança</small>
                        </button>
                    </div>
                </div>

                <!-- Professor / equipe MPG -->
                <div class="pedirUniforme__block" id="equipeBlock" style="display:none;">
                    <h3><span>2</span> Pessoa e tipo de uniforme</h3>

                    <div class="pedirUniforme__field">
                        <span>Pessoa</span>
                        <select id="equipePessoa">
                            <option value="">Carregando...</option>
                        </select>
                        <small>Professores e usuários do painel administrativo.</small>
                    </div>

                    <div class="pedirUniforme__field">
                        <span>Tipo de uniforme</span>
                        <select id="equipeTipo">
                            <option value="completo">Uniforme completo (camisa + calção)</option>
                            <option value="equipe_tecnica">Equipe técnica (só camisa)</option>
                        </select>
                    </div>

                    <div class="pedirUniforme__field" id="equipeCargoField" style="display:none;">
                        <span>Texto da camisa</span>
                        <select id="equipeCargo">
                            <option value="equipe_tecnica">Equipe Técnica</option>
                            <option value="tecnico">Técnico</option>
                        </select>
                        <small id="equipeCargoPreview">Vai estampado como: <strong>Equipe Técnica — NOME</strong></small>
                    </div>

                    <div class="pedirUniforme__equipeFoto" id="equipeFoto" style="display:none;">
                        <img src="<?= BASE_URL ?>/<?= UNIFORME_EQUIPE_IMAGEM ?>" alt="Camisa da equipe técnica" data-lightbox>
                        <small>Camisa da equipe técnica — clique para ampliar.</small>
                    </div>
                </div>

                <div class="pedirUniforme__block" id="alunoBlock">
                    <h3><span>2</span> Aluno</h3>

                    <div class="pedirUniforme__search">
                        <input type="text" id="buscaAluno" class="input" placeholder="Buscar aluno por nome ou e-mail..." autocomplete="off">
                        <div class="pedirUniforme__searchResults" id="buscaAlunoResults"></div>
                    </div>

                    <div class="pedirUniforme__alunoSelecionado" id="alunoSelecionadoBox" style="display:none;">
                        <div>
                            <strong id="alunoSelecionadoNome"></strong>
                            <small id="alunoSelecionadoEmail"></small>
                        </div>
                        <button type="button" class="btn btn--gray btn--sm" id="alunoTrocarBtn">Trocar</button>
                    </div>
                    <input type="hidden" id="alunoId" name="aluno_id" value="">

                    <div class="pedirUniforme__field" id="turmaField" style="display:none;">
                        <span>Turma</span>
                        <select id="turmaSelect" name="turma_id"></select>
                        <small>A disponibilidade dos números muda conforme a turma.</small>
                    </div>
                </div>

                <!--
                    Equipe técnica é UM produto só (a camisa do print acima), então aqui não
                    tem modelo a escolher — só o corte, que muda a grade de tamanho. Mostrar
                    os 4 modelos de uniforme completo junto misturaria duas coisas diferentes.
                -->
                <div class="pedirUniforme__block" id="corteBlock" style="display:none;">
                    <h3><span>3</span> Corte da camisa</h3>

                    <div class="pedirUniforme__destino">
                        <button type="button" class="pedirUniforme__destinoOpt is-active" data-corte="masculino">
                            <strong>Masculina</strong>
                            <small>Grade PP ao XG3</small>
                        </button>
                        <button type="button" class="pedirUniforme__destinoOpt" data-corte="feminino">
                            <strong>Feminina</strong>
                            <small>Grade PP ao XG</small>
                        </button>
                    </div>
                </div>

                <div class="pedirUniforme__block" id="modeloBlock">
                    <h3><span>3</span> Modelo do uniforme</h3>

                    <div class="pedirUniforme__models">
                        <?php
                        $modelos = [
                            ['genero' => 'masculino', 'modelo' => 'padrao', 'img' => 'uniformeMasculinoPadrao.jpg', 'tag' => 'Masculino', 'nome' => 'Modelo padrão'],
                            ['genero' => 'masculino', 'modelo' => 'libero', 'img' => 'uniformeMasculinoLibero.jpg', 'tag' => 'Masculino', 'nome' => 'Modelo líbero'],
                            ['genero' => 'feminino',  'modelo' => 'padrao', 'img' => 'uniformeFemininoPadrao.jpg',  'tag' => 'Feminino',  'nome' => 'Modelo padrão'],
                            ['genero' => 'feminino',  'modelo' => 'libero', 'img' => 'uniformeFemininoLibero.jpg',  'tag' => 'Feminino',  'nome' => 'Modelo líbero'],
                        ];
                        foreach ($modelos as $m):
                        ?>
                        <label class="pedirUniformeModel">
                            <input type="radio" name="modelo_completo" value="<?= $m['genero'] ?>|<?= $m['modelo'] ?>"
                                   data-genero="<?= $m['genero'] ?>" data-modelo="<?= $m['modelo'] ?>">
                            <span class="pedirUniformeModel__box">
                                <span class="pedirUniformeModel__tag"><?= $m['tag'] ?></span>
                                <img src="<?= BASE_URL ?>/images/uniformes/<?= $m['img'] ?>" alt="Uniforme <?= strtolower($m['tag']) ?> <?= $m['nome'] ?>">
                                <span class="pedirUniformeModel__name"><?= $m['nome'] ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pedirUniforme__block" id="detalhesBlock">
                    <h3><span>4</span> Personalização</h3>

                    <label class="pedirUniforme__field">
                        <span>Nome na camiseta</span>
                        <input type="text" id="nomeCamisa" name="nome_camisa" maxlength="<?= UNIFORME_NOME_MAX ?>"
                               class="input" placeholder="Ex.: MARIANA" autocomplete="off">
                        <small>Até <?= UNIFORME_NOME_MAX ?> caracteres. Vai estampado em caixa alta.</small>
                    </label>

                    <div class="pedirUniforme__field" data-so-completo>
                        <span>Número da camiseta</span>
                        <button type="button" class="pedirUniforme__numberPick" id="numberPick">
                            <em id="numberLabel">Escolher número</em>
                        </button>
                        <input type="hidden" id="numeroInput" name="numero" value="">
                        <small id="numeroHintAluno">De 1 a 99, entre os que ainda estão livres na turma/gênero escolhidos.</small>

                        <!--
                            O modal de números busca a disponibilidade pela TURMA do aluno.
                            Professor e equipe MPG não estão em turma nenhuma, então aqui o
                            número é digitado, com a lista de ocupados da equipe como apoio.
                        -->
                        <input type="number" id="numeroEquipe" class="input" min="<?= UNIFORME_NUMERO_MIN ?>"
                               max="<?= UNIFORME_NUMERO_MAX ?>" placeholder="Ex.: 10" style="display:none;">
                        <small id="numeroHintEquipe" style="display:none;"></small>
                    </div>

                    <div class="pedirUniforme__field">
                        <span id="labelTamCamisa">Tamanho da camisa</span>
                        <div class="pedirUniforme__sizes" id="sizesBoxCamisa"></div>
                        <input type="hidden" id="tamanhoCamisaInput" name="tamanho_camisa" value="">
                    </div>

                    <div class="pedirUniforme__field" data-so-completo>
                        <span id="labelTamShorts">Tamanho do shorts</span>
                        <div class="pedirUniforme__sizes" id="sizesBoxShorts"></div>
                        <input type="hidden" id="tamanhoShortsInput" name="tamanho_shorts" value="">
                        <small>Em dúvida? Confira a <button type="button" class="pedirUniforme__measureLink" id="adminMeasuresOpen">tabela de medidas</button>.</small>
                    </div>
                </div>

                <div class="pedirUniforme__block pedirUniforme__block--summary" id="resumoBlock">
                    <h3><span>5</span> Confirmar pedido</h3>

                    <div class="pedirUniforme__notice">
                        Esse pedido nasce com pagamento já <strong>confirmado</strong> (valor R$ <?= number_format($valor, 2, ',', '.') ?>), sem passar pela cobrança do sistema — use apenas quando o pagamento já foi coletado por fora.
                    </div>

                    <p class="pedirUniforme__error" id="pedirUniformeError" role="alert"></p>

                    <button type="submit" class="btn btn--primary" id="pedirUniformeSubmit">Registrar pedido como pago</button>
                </div>

            </form>

        </section>

        <!-- Modal de escolha do número -->
        <div class="uniformNumbers" id="uniformNumbersModal" aria-hidden="true" role="dialog" aria-modal="true">
            <button type="button" class="uniformNumbers__backdrop js-numbers-close" aria-label="Fechar"></button>
            <div class="uniformNumbers__dialog">
                <div class="uniformNumbers__head">
                    <div>
                        <h2>Escolha o número</h2>
                        <p id="uniformNumbersSub">Números disponíveis na turma do aluno.</p>
                    </div>
                    <button type="button" class="uniformNumbers__close js-numbers-close" aria-label="Fechar">&times;</button>
                </div>

                <ul class="uniformNumbers__legend">
                    <li><span class="uniformNumbers__chip uniformNumbers__chip--free"></span> Disponível</li>
                    <li><span class="uniformNumbers__chip uniformNumbers__chip--mine"></span> Já é do aluno</li>
                    <li><span class="uniformNumbers__chip uniformNumbers__chip--taken"></span> Indisponível</li>
                </ul>

                <div class="uniformNumbers__grid" id="uniformNumbersGrid">
                    <p class="uniformNumbers__loading">Carregando números...</p>
                </div>
            </div>
        </div>

        <!-- Modal da tabela de medidas -->
        <div class="adminUniformMeasures" id="adminMeasuresModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="adminMeasuresTitle">
            <button type="button" class="adminUniformMeasures__backdrop js-admin-measures-close" aria-label="Fechar tabela de medidas"></button>
            <div class="adminUniformMeasures__dialog">
                <div class="adminUniformMeasures__head">
                    <div>
                        <span>Uniformes MPG Academy</span>
                        <h2 id="adminMeasuresTitle">Tabela de medidas</h2>
                        <p>As quatro grades do fabricante — camisa e shorts, masculino e feminino.</p>
                    </div>
                    <button type="button" class="adminUniformMeasures__close js-admin-measures-close" aria-label="Fechar">&times;</button>
                </div>

                <?php
                // Mesmo componente da área do aluno: as medidas nunca divergem entre as telas.
                include ROOT . '/includes/uniforme_medidas.php';
                ?>
            </div>
        </div>

        <!-- Modal de sucesso -->
        <div class="confirmModal" id="sucessoModal">
            <div class="confirmModal__box">
                <h3>Pedido registrado!</h3>
                <p id="sucessoModalInfo"></p>
                <div class="confirmModal__actions">
                    <a class="btn btn--gray" href="<?= BASE_URL ?>/admin/pedirfuniforme">Fazer outro pedido</a>
                    <a class="btn btn--primary" href="<?= BASE_URL ?>/admin/uniformes">Ver na lista</a>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL    = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL          = "<?= BASE_URL ?>";
    var UNIFORME_MEDIDAS = <?= json_encode(UNIFORME_MEDIDAS, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php echo '<script src="' . ADMIN_BASE_URL . '/pages/pedirfuniforme/pedirfuniforme.js?v=' . time() . '"></script>'; ?>

<script>
/**
 * Pedido de uniforme para professor / equipe MPG.
 *
 * Convive com o fluxo de aluno (pedirfuniforme.js) sem mexer nele: o seletor do passo 1
 * troca qual dos dois está visível. O de aluno tem turma, número por turma e cobrança; o de
 * equipe não tem nada disso — a academia banca, e a camisa da equipe técnica não tem número.
 */
(function () {
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

    var blocoAluno  = document.getElementById('alunoBlock');
    var blocoEquipe = document.getElementById('equipeBlock');
    var blocoModelo = document.getElementById('modeloBlock');
    var form        = document.getElementById('pedirUniformeForm');
    if (!blocoEquipe || !form) return;

    var selPessoa = document.getElementById('equipePessoa');
    var selTipo   = document.getElementById('equipeTipo');
    var selCargo  = document.getElementById('equipeCargo');
    var campoCargo = document.getElementById('equipeCargoField');
    var fotoEquipe = document.getElementById('equipeFoto');
    var preview    = document.getElementById('equipeCargoPreview');

    var blocoCorte    = document.getElementById('corteBlock');
    var blocoDetalhes = document.getElementById('detalhesBlock');
    var blocoResumo   = document.getElementById('resumoBlock');
    var avisoPago     = blocoResumo ? blocoResumo.querySelector('.pedirUniforme__notice') : null;
    var avisoPagoHtml = avisoPago ? avisoPago.innerHTML : '';

    var destino = 'aluno';
    var dados   = null;
    var corte   = 'masculino'; // gênero da camisa da equipe técnica

    function escapar(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : t;
        return d.innerHTML;
    }

    // ── Passo 1: alternar entre aluno e equipe ────────────────────────────────
    document.querySelectorAll('[data-destino]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            destino = btn.getAttribute('data-destino');

            document.querySelectorAll('[data-destino]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });

            var ehEquipe = destino === 'equipe';
            blocoAluno.style.display  = ehEquipe ? 'none' : '';
            blocoEquipe.style.display = ehEquipe ? '' : 'none';

            document.body.setAttribute('data-destino-uniforme', destino);

            // O modal de números é do fluxo de aluno (busca pela turma); a equipe digita.
            document.getElementById('numberPick').style.display      = ehEquipe ? 'none' : '';
            document.getElementById('numeroHintAluno').style.display = ehEquipe ? 'none' : '';
            document.getElementById('numeroEquipe').style.display    = ehEquipe ? '' : 'none';
            document.getElementById('numeroHintEquipe').style.display = ehEquipe ? '' : 'none';

            if (ehEquipe) {
                aplicarTipo();
                if (!dados) carregarEquipe();
            } else {
                // Volta pro fluxo de aluno: modelo é dos 4 cards, corte não existe, e o
                // aviso volta a ser o de "pagamento já coletado por fora".
                blocoCorte.style.display    = 'none';
                blocoModelo.style.display   = '';
                blocoDetalhes.style.display = '';
                blocoResumo.style.display   = '';
                if (avisoPago) avisoPago.innerHTML = avisoPagoHtml;
            }
        });
    });

    /** Mostra quais números já estão tomados no balde da equipe. */
    function carregarOcupados() {
        var hint   = document.getElementById('numeroHintEquipe');
        var pessoa = selPessoa.value.split(':');
        var modeloSel = form.querySelector('[name="modelo_completo"]:checked');

        if (pessoa.length !== 2 || !modeloSel) {
            hint.textContent = 'Escolha a pessoa e o modelo pra ver os números livres.';
            return;
        }

        var url = ADMIN_BASE_URL + '/services/get_equipe_uniforme.php'
                + '?genero=' + encodeURIComponent(modeloSel.getAttribute('data-genero'))
                + '&pessoa_tipo=' + encodeURIComponent(pessoa[0])
                + '&pessoa_id=' + encodeURIComponent(pessoa[1]);

        hint.textContent = 'Verificando números...';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success || !d.ocupados) {
                    hint.textContent = 'De 1 a 99. O número é validado ao salvar.';
                    return;
                }
                hint.textContent = d.ocupados.length
                    ? 'Já em uso na equipe: ' + d.ocupados.join(', ')
                    : 'Nenhum número em uso na equipe ainda.';
            })
            .catch(function () {
                hint.textContent = 'De 1 a 99. O número é validado ao salvar.';
            });
    }

    /** Mostra os passos que fazem sentido pro tipo escolhido. Um OU outro, nunca os dois. */
    function aplicarTipo() {
        var ehTecnica = selTipo.value === 'equipe_tecnica';

        campoCargo.style.display  = ehTecnica ? '' : 'none';
        fotoEquipe.style.display  = ehTecnica ? '' : 'none';

        // Equipe técnica: um produto só, escolhe apenas o corte.
        // Completo: os 4 modelos (masculino/feminino × padrão/líbero).
        blocoCorte.style.display  = ehTecnica ? '' : 'none';
        blocoModelo.style.display = ehTecnica ? 'none' : '';

        // Personalização e confirmação são sempre necessárias. No fluxo de aluno quem libera
        // esses passos é o pedirfuniforme.js (só depois de escolher aluno e turma); no de
        // equipe não há essa dependência, então liberamos aqui — sem isso o botão de
        // finalizar simplesmente não aparecia.
        blocoDetalhes.style.display = '';
        blocoResumo.style.display   = '';

        // O aviso de "pagamento já confirmado" é do fluxo de aluno. Pedido de equipe é
        // interno: dizer que o valor foi coletado por fora seria mentira.
        if (avisoPago) {
            avisoPago.innerHTML = 'Pedido <strong>interno</strong> da equipe MPG — sem cobrança. '
                                + 'Entra direto na fila de produção.';
        }

        document.body.setAttribute('data-uniforme-tipo', selTipo.value);

        if (ehTecnica) aplicarCorte();
        atualizarPreview();
    }

    /**
     * Monta os botões de tamanho de uma peça.
     *
     * No fluxo de aluno isso é feito pelo pedirfuniforme.js, a partir da turma escolhida.
     * O fluxo de equipe não passa por turma nenhuma, então as caixas ficavam vazias — o
     * admin via "Tamanho da camisa" sem nenhuma opção pra clicar.
     */
    function montarTamanhos(idCaixa, idHidden, lista) {
        var caixa  = document.getElementById(idCaixa);
        var hidden = document.getElementById(idHidden);
        if (!caixa || !hidden || !lista) return;

        hidden.value = '';
        caixa.innerHTML = lista.map(function (t) {
            return '<button type="button" class="pedirUniforme__size" data-tam="' + t + '">' + t + '</button>';
        }).join('');

        caixa.querySelectorAll('[data-tam]').forEach(function (b) {
            b.addEventListener('click', function () {
                caixa.querySelectorAll('[data-tam]').forEach(function (o) { o.classList.remove('is-active'); });
                b.classList.add('is-active');
                hidden.value = b.getAttribute('data-tam');
            });
        });
    }

    /** Grade de tamanhos do gênero em uso — corte (equipe técnica) ou modelo (completo). */
    function aplicarTamanhos(genero) {
        if (!dados || !dados.tamanhos[genero]) return;

        montarTamanhos('sizesBoxCamisa', 'tamanhoCamisaInput', dados.tamanhos[genero].camisa);

        var labelCam = document.getElementById('labelTamCamisa');
        if (labelCam) {
            labelCam.textContent = 'Tamanho da camisa ' + (genero === 'feminino' ? 'feminina' : 'masculina');
        }

        // Equipe técnica é só camisa — o calção nem aparece (CSS data-so-completo).
        if (selTipo.value === 'completo') {
            montarTamanhos('sizesBoxShorts', 'tamanhoShortsInput', dados.tamanhos[genero].shorts);

            var labelSh = document.getElementById('labelTamShorts');
            if (labelSh) {
                labelSh.textContent = genero === 'feminino' ? 'Tamanho da bermuda' : 'Tamanho do calção';
            }
        }
    }

    function aplicarCorte() {
        aplicarTamanhos(corte);
    }

    document.querySelectorAll('[data-corte]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            corte = btn.getAttribute('data-corte');
            document.querySelectorAll('[data-corte]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            aplicarCorte();
        });
    });

    // No completo o gênero vem do modelo escolhido, e é ele que define as duas grades.
    form.querySelectorAll('[name="modelo_completo"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (destino !== 'equipe' || selTipo.value !== 'completo') return;
            aplicarTamanhos(radio.getAttribute('data-genero'));
            carregarOcupados();
        });
    });

    function carregarEquipe() {
        fetch(ADMIN_BASE_URL + '/services/get_equipe_uniforme.php', { credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (t) {
                    try { return JSON.parse(t); }
                    catch (e) {
                        console.error('Resposta não-JSON em get_equipe_uniforme:', t.slice(0, 500));
                        return { success: false, message: 'Erro ' + r.status + ' ao carregar a equipe.' };
                    }
                });
            })
            .then(function (d) {
                if (!d.success) {
                    selPessoa.innerHTML = '<option value="">' + escapar(d.message || 'Erro') + '</option>';
                    return;
                }
                dados = d;

                // Deixa explícito quem é professor e quem é da equipe MPG — os dois vêm de
                // tabelas diferentes e podem ter o mesmo id.
                selPessoa.innerHTML = '<option value="">Escolha a pessoa…</option>' +
                    d.pessoas.map(function (p) {
                        return '<option value="' + p.tipo + ':' + p.id + '" data-nome="' + escapar(p.nome) + '">'
                             + escapar(p.rotulo + ' · ' + p.nome) + '</option>';
                    }).join('');

                // A grade de tamanhos só existe depois desta resposta — sem isso o passo do
                // corte apareceria com a lista de tamanhos vazia.
                if (selTipo.value === 'equipe_tecnica') aplicarCorte();
            })
            .catch(function () {
                selPessoa.innerHTML = '<option value="">Erro de conexão</option>';
            });
    }

    function atualizarPreview() {
        var campo = document.getElementById('nomeCamisa');
        var txt   = selCargo.options[selCargo.selectedIndex].textContent;
        // O nome vem do campo de personalização (que o admin pode encurtar pra caber na
        // camisa), não do cadastro — era o que deixava o preview com um traço solto.
        var nome  = (campo && campo.value.trim()) ? campo.value.trim().toUpperCase() : '…';

        preview.innerHTML = 'Vai estampado como: <strong>' + escapar(txt + ' — ' + nome) + '</strong>';
    }

    selTipo.addEventListener('change', aplicarTipo);
    selCargo.addEventListener('change', atualizarPreview);

    // Ao escolher a pessoa, já sugere o primeiro nome dela na camisa — é o que quase sempre
    // vai bordado, e economiza digitação.
    selPessoa.addEventListener('change', function () {
        var opt   = selPessoa.options[selPessoa.selectedIndex];
        var nome  = opt ? (opt.getAttribute('data-nome') || '') : '';
        var campo = document.getElementById('nomeCamisa');

        if (campo && !campo.value.trim() && nome) {
            campo.value = nome.split(' ')[0].toUpperCase();
        }
        atualizarPreview();
        if (selTipo.value === 'completo') carregarOcupados();
    });

    var campoNome = document.getElementById('nomeCamisa');
    if (campoNome) campoNome.addEventListener('input', atualizarPreview);

    // ── Envio ─────────────────────────────────────────────────────────────────
    // Intercepta na fase de captura pra decidir antes do handler do fluxo de aluno.
    form.addEventListener('submit', function (e) {
        if (destino !== 'equipe') return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var erro = document.getElementById('pedirUniformeError');
        var btn  = document.getElementById('pedirUniformeSubmit');

        function falhar(msg) {
            erro.textContent = msg;
            erro.style.display = '';
            btn.disabled = false;
            btn.textContent = 'Registrar pedido';
        }

        var pessoa = selPessoa.value.split(':');
        if (pessoa.length !== 2) return falhar('Escolha a pessoa do pedido.');

        var ehTecnica = selTipo.value === 'equipe_tecnica';

        // O gênero vem do corte (equipe técnica) ou do modelo escolhido (completo) — nunca
        // dos dois, porque só um dos dois passos está visível.
        var genero, modelo;

        if (ehTecnica) {
            genero = corte;
            modelo = 'padrao'; // a camisa da equipe tem um desenho só
        } else {
            var modeloSel = form.querySelector('[name="modelo_completo"]:checked');
            if (!modeloSel) return falhar('Escolha o modelo do uniforme.');
            genero = modeloSel.getAttribute('data-genero');
            modelo = modeloSel.getAttribute('data-modelo');
        }

        if (!document.getElementById('nomeCamisa').value.trim()) {
            return falhar('Informe o nome que vai na camisa.');
        }

        if (!document.getElementById('tamanhoCamisaInput').value) {
            return falhar('Escolha o tamanho da camisa.');
        }

        if (!ehTecnica) {
            if (!document.getElementById('numeroEquipe').value) {
                return falhar('Informe o número da camiseta.');
            }
            if (!document.getElementById('tamanhoShortsInput').value) {
                return falhar(genero === 'feminino' ? 'Escolha o tamanho da bermuda.' : 'Escolha o tamanho do calção.');
            }
        }

        var body = new URLSearchParams({
            pessoa_tipo:    pessoa[0],
            pessoa_id:      pessoa[1],
            tipo_uniforme:  selTipo.value,
            genero:         genero,
            modelo:         modelo,
            nome_camisa:    document.getElementById('nomeCamisa').value,
            tamanho_camisa: document.getElementById('tamanhoCamisaInput').value,
            // O número vem do campo da equipe, não do modal do fluxo de aluno.
            numero:         ehTecnica ? '' : document.getElementById('numeroEquipe').value,
            tamanho_shorts: ehTecnica ? '' : document.getElementById('tamanhoShortsInput').value,
            equipe_cargo:   ehTecnica ? selCargo.value : ''
        });

        erro.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Registrando...';

        fetch(ADMIN_BASE_URL + '/services/criar_pedido_uniforme_equipe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function (r) {
            return r.text().then(function (t) {
                try { return JSON.parse(t); }
                catch (err) {
                    console.error('Resposta não-JSON ao criar pedido de equipe:', t.slice(0, 500));
                    return { success: false, message: 'O servidor respondeu com erro ' + r.status + '.' };
                }
            });
        })
        .then(function (d) {
            if (!d.success) return falhar(d.message || 'Não foi possível registrar.');

            document.getElementById('sucessoModalInfo').innerHTML =
                escapar(d.message) + '<br><small>' + escapar(d.texto_camisa) + '</small>';
            document.getElementById('sucessoModal').classList.add('confirmModal--open');
            btn.disabled = false;
            btn.textContent = 'Registrar pedido';
        })
        .catch(function () { falhar('Erro de conexão.'); });
    }, true);
}());
</script>

<?php include ROOT . '/includes/lightbox.php'; ?>

</body>
</html>
