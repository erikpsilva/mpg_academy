
const NIVEL_LABEL = { iniciante: 'Iniciante', intermediario: 'Intermediário', avancado: 'Avançado' };

const maskPhone = (v) => {
    v = v.replace(/\D/g, '').slice(0, 11);
    if (v.length === 11) return v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    if (v.length >= 7)   return v.replace(/^(\d{2})(\d{4,5})(\d{0,4})/, '($1) $2-$3');
    if (v.length >= 3)   return v.replace(/^(\d{2})(\d+)/, '($1) $2');
    if (v.length >= 1)   return '(' + v;
    return v;
};

const maskCpf = (v) => {
    v = v.replace(/\D/g, '').slice(0, 11);
    if (v.length > 9)      return v.replace(/^(\d{3})(\d{3})(\d{3})(\d+)/, '$1.$2.$3-$4');
    if (v.length > 6)      return v.replace(/^(\d{3})(\d{3})(\d+)/, '$1.$2.$3');
    if (v.length > 3)      return v.replace(/^(\d{3})(\d+)/, '$1.$2');
    return v;
};

let turmasParaTeste = [];

const fmtData = (str) => {
    if (!str) return '—';
    const [y, m, d] = str.split('-');
    return d + '/' + m + '/' + y;
};

const fmtDataCriado = (str) => {
    if (!str) return '—';
    const [y, m, d] = str.split('T')[0].split('-');
    return d + '/' + m + '/' + y;
};


// ── Badges de status do termo ────────────────────────────────────────────────

const termoBadge = (f) => {
    if (!f.menor && !f.responsavel_nome) return '';
    if (!f.termo_status) {
        return '<span class="adminTesteTermo adminTesteTermo--pendente">📋 Termo pendente</span>';
    }
    const map = {
        concluido:              '<span class="adminTesteTermo adminTesteTermo--ok">✅ Termo assinado</span>',
        aguardando_responsavel: '<span class="adminTesteTermo adminTesteTermo--meio">🕐 Aguard. responsável</span>',
        aguardando_escola:      '<span class="adminTesteTermo adminTesteTermo--meio">🕐 Aguard. escola</span>',
        pendente:               '<span class="adminTesteTermo adminTesteTermo--pendente">📋 Termo pendente</span>',
    };
    return map[f.termo_status] || '';
};

// ── Render principal ──────────────────────────────────────────────────────────

const renderItem = (f, tipo) => {
    const dtLabel = tipo === 'agendado'
        ? (f.data_agendada ? 'Aula: ' + fmtData(f.data_agendada) : 'Sem data definida')
        : 'Na fila desde ' + fmtDataCriado(f.criado_em);

    const acoes = tipo === 'agendado'
        ? '<button class="btn--testeAcao btn--realizar" data-id="' + f.id + '">Realizada</button>' +
          '<button class="btn--testeAcao btn--cancelarTeste is-danger" data-id="' + f.id + '">Cancelar</button>'
        : '<button class="btn--testeAcao btn--promoverTeste" data-id="' + f.id + '">Promover</button>' +
          '<button class="btn--testeAcao btn--cancelarTeste is-danger" data-id="' + f.id + '">Cancelar</button>';

    const numBadge = f.pos !== undefined
        ? '<span class="adminTesteItem__num">' + f.pos + '</span>'
        : '';

    const termoAcoes = buildTermoAcoes(f);

    return '<div class="adminTesteItem" data-id="' + f.id + '">' +
        numBadge +
        '<div class="adminTesteItem__info">' +
            '<strong>' + $('<span>').text(f.nome).html() + '</strong>' +
            (f.menor ? '<span class="adminTesteItem__menor">Menor de idade</span>' : '') +
            '<span>' +
                (f.email  ? $('<span>').text(f.email).html()   : '') +
                (f.email && f.celular ? ' &nbsp;·&nbsp; ' : '') +
                (f.celular ? $('<span>').text(f.celular).html() : '') +
                (!f.email && !f.celular ? '<em>Sem contato</em>' : '') +
            '</span>' +
            (f.responsavel_nome ? '<span class="adminTesteItem__resp">Resp.: ' + $('<span>').text(f.responsavel_nome).html() + '</span>' : '') +
        '</div>' +
        '<div class="adminTesteItem__right">' +
            '<span class="adminTesteItem__data">' + dtLabel + '</span>' +
            termoBadge(f) +
            '<div class="adminTesteItem__acoes">' +
                '<button class="btn--testeAcao btn--editarTeste" data-item=\'' + JSON.stringify(f).replace(/'/g, '&#39;') + '\'>✏ Editar</button>' +
                acoes +
            '</div>' +
            (termoAcoes ? '<div class="adminTesteItem__termoAcoes">' + termoAcoes + '</div>' : '') +
        '</div>' +
    '</div>';
};

const buildTermoAcoes = (f) => {
    if (!f.menor && !f.responsavel_nome) return '';
    const btns = [];

    if (!f.assinado_escola_em) {
        btns.push('<button class="btn--testeAcao btn--assinarEscola" data-id="' + f.id + '">✍ Assinar (escola)</button>');
    }
    if (f.responsavel_email && f.termo_status !== 'concluido') {
        btns.push('<button class="btn--testeAcao btn--enviarTermo" data-id="' + f.id + '">📧 Enviar termo resp.</button>');
    }
    if (f.termo_token) {
        const url = BASE_URL + '/termo?token=' + f.termo_token;
        btns.push('<a class="btn--testeAcao btn--verTermo" href="' + url + '" target="_blank">🔗 Ver termo</a>');
    }

    return btns.join('');
};

const renderBloco = (turma) => {
    const temLimite = turma.max_alunos !== null;
    const vagas     = turma.vagas_teste;

    let vagaBadge;
    if (!temLimite) {
        vagaBadge = '<span class="adminTesteVagaBadge sem-limite">Sem limite</span>';
    } else if (vagas > 0) {
        vagaBadge = '<span class="adminTesteVagaBadge disponivel">' + vagas + ' vaga' + (vagas === 1 ? '' : 's') + ' de teste</span>';
    } else {
        vagaBadge = '<span class="adminTesteVagaBadge lotada">Nenhuma vaga de teste</span>';
    }

    const totalAgendados = turma.agendados.length;
    const totalFila      = turma.fila.length;

    const secaoAgendados = totalAgendados > 0
        ? '<div class="adminTesteBloco__secao">' +
            '<p class="adminTesteBloco__secaoTitulo">Agendados (' + totalAgendados + ')</p>' +
            turma.agendados.map((f, i) => renderItem({ ...f, pos: i + 1 }, 'agendado')).join('') +
          '</div>'
        : '';

    const secaoFila = totalFila > 0
        ? '<div class="adminTesteBloco__secao adminTesteBloco__secao--fila">' +
            '<p class="adminTesteBloco__secaoTitulo">Na fila de teste (' + totalFila + ')</p>' +
            turma.fila.map((f, i) => renderItem({ ...f, pos: i + 1 }, 'fila')).join('') +
          '</div>'
        : '';

    return '<div class="adminTesteBloco" data-turma-id="' + turma.turma_id + '">' +
        '<div class="adminTesteBloco__head">' +
            '<div class="adminTesteBloco__info">' +
                '<h3>' + $('<span>').text(turma.turma_nome).html() + '</h3>' +
                '<p>' + $('<span>').text(turma.quadra_nome).html() +
                    ' &nbsp;·&nbsp; ' + (NIVEL_LABEL[turma.nivel] || turma.nivel) + '</p>' +
            '</div>' +
            '<div class="adminTesteBloco__vagas">' +
                vagaBadge +
                '<span class="adminTesteBloco__count">' +
                    turma.alunos_ativos + (turma.max_alunos ? '/' + turma.max_alunos : '') + ' alunos' +
                '</span>' +
            '</div>' +
        '</div>' +
        (secaoAgendados || secaoFila
            ? secaoAgendados + secaoFila
            : '<p class="adminTeste__empty" style="padding:20px 20px 16px">Nenhum registro ativo.</p>') +
    '</div>';
};

const renderResumo = (turmas) => {
    let totalAgendados = 0, totalFila = 0;

    const cards = turmas.map(t => {
        const ag = t.agendados.length;
        const fl = t.fila.length;
        const tot = ag + fl;
        if (!tot) return '';
        totalAgendados += ag;
        totalFila      += fl;
        return '<div class="adminTesteResumoCard">' +
            '<div class="adminTesteResumoCard__nome">' + $('<span>').text(t.turma_nome).html() + '</div>' +
            '<div class="adminTesteResumoCard__detalhe">' +
                (ag ? '<span class="adminTesteResumoCard__agendados">' + ag + ' agendado' + (ag === 1 ? '' : 's') + '</span>' : '') +
                (fl ? '<span class="adminTesteResumoCard__fila">' + fl + ' na fila</span>' : '') +
            '</div>' +
            '<div class="adminTesteResumoCard__total">' + tot + '</div>' +
        '</div>';
    }).filter(Boolean).join('');

    if (!cards) return '';

    const totalGeral = totalAgendados + totalFila;

    return '<div class="adminTesteResumo">' +
        '<div class="adminTesteResumo__cards">' + cards + '</div>' +
        '<div class="adminTesteResumo__geral">' +
            '<span class="adminTesteResumo__label">Total geral</span>' +
            '<span class="adminTesteResumo__num">' + totalGeral + '</span>' +
            '<div class="adminTesteResumo__sub">' +
                (totalAgendados ? '<span>' + totalAgendados + ' agendado' + (totalAgendados === 1 ? '' : 's') + '</span>' : '') +
                (totalFila ? '<span>' + totalFila + ' na fila</span>' : '') +
            '</div>' +
        '</div>' +
    '</div>';
};

const renderPage = (turmas, realizados) => {
    const body = $('#adminTesteBody');
    const resumo = turmas.length ? renderResumo(turmas) : '';
    const ativos = turmas.length
        ? turmas.map(renderBloco).join('')
        : '<p class="adminTeste__empty">Nenhuma aula experimental agendada no momento.</p>';

    body.html(resumo + ativos + renderRealizados(realizados));
};

const renderRealizados = (lista) => {
    if (!lista.length) return '';

    const rows = lista.map(r => {
        let acaoCol;
        if (r.ja_aluno) {
            acaoCol = '<span class="badge badge--aluno">✓ Já é aluno</span>';
        } else if (r.email) {
            acaoCol = '<button class="btn--testeAcao btn--enviarEmailCadastro" ' +
                'data-nome="' + $('<span>').text(r.nome).html() + '" ' +
                'data-email="' + $('<span>').text(r.email).html() + '" ' +
                'data-id="' + r.id + '">' +
                'Enviar email de cadastro' +
            '</button>';
        } else {
            acaoCol = '<em style="color:#aaa">Sem e-mail</em>';
        }

        return '<tr' + (r.ja_aluno ? ' class="row--ja-aluno"' : '') + '>' +
            '<td>' + $('<span>').text(r.nome).html() + '</td>' +
            '<td>' + (r.email   ? $('<span>').text(r.email).html()   : '<em>—</em>') + '</td>' +
            '<td>' + (r.celular ? $('<span>').text(r.celular).html() : '<em>—</em>') + '</td>' +
            '<td>' + $('<span>').text(r.turma_nome + ' · ' + r.quadra_nome).html() + '</td>' +
            '<td>' + fmtDataCriado(r.criado_em) + '</td>' +
            '<td>' + acaoCol + '</td>' +
        '</tr>';
    }).join('');

    return '<div class="adminTesteRealizados" id="adminTesteRealizados">' +
        '<div class="adminTesteRealizados__head">' +
            '<span>Já realizaram aula experimental</span>' +
            '<span class="adminTesteRealizados__count">' + lista.length + ' pessoa' + (lista.length === 1 ? '' : 's') + '</span>' +
            '<button class="adminTesteRealizados__toggle" id="btnToggleRealizados">Ocultar</button>' +
        '</div>' +
        '<div class="adminTesteRealizados__body" id="realizadosBody">' +
            '<table class="adminTesteRealizados__table">' +
                '<thead><tr>' +
                    '<th>Nome</th><th>E-mail</th><th>Celular</th><th>Turma</th><th>Data</th><th>Ação</th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>' +
    '</div>';
};

const carregarDados = () => {
    $('#adminTesteBody').html('<div class="adminTeste__loading">Carregando...</div>');
    $.get(ADMIN_BASE_URL + '/services/get_aulas_experimentais.php', (res) => {
        if (!res.success) {
            $('#adminTesteBody').html('<p class="adminTeste__empty">Erro ao carregar dados.</p>');
            return;
        }
        renderPage(res.turmas, res.realizados || []);
    }, 'json').fail(() => {
        $('#adminTesteBody').html('<p class="adminTeste__empty">Erro ao comunicar com o servidor.</p>');
    });
};


// ── Modal: CADASTRO ───────────────────────────────────────────────────────────

const openModal = () => {
    $('#testeNome, #testeEmail, #testeCelular, #testeDataNasc, #testeRespNome, #testeRespEmail, #testeRespCpf, #testeRespCelular').val('');
    $('#testeData').val(new Date().toISOString().split('T')[0]);
    $('#testeMenorCheck').prop('checked', false);
    $('#testeAviso').text('').hide();
    $('#testeResponsavelSection').hide();
    $('#adminTesteSubmitBtn').prop('disabled', false).text('Cadastrar');
    $('#testeTurma').html('<option value="">Carregando turmas...</option>');
    $('#adminTesteModal').addClass('is-open');
    $('body').addClass('modal-open');

    $.get(ADMIN_BASE_URL + '/services/get_turmas_para_teste.php', (res) => {
        if (!res.success) { $('#testeTurma').html('<option value="">Erro ao carregar turmas</option>'); return; }
        turmasParaTeste = res.turmas;
        const opts = res.turmas.map(t => {
            const vagaInfo = t.max_alunos === null
                ? 'sem limite'
                : t.vagas_teste + ' vaga' + (t.vagas_teste === 1 ? '' : 's') + ' de teste';
            return '<option value="' + t.id + '" data-vagas="' + (t.vagas_teste ?? 999) + '">' +
                t.nome + ' — ' + t.quadra_nome + ' (' + vagaInfo + ')' +
            '</option>';
        });
        $('#testeTurma').html('<option value="">Selecione a turma...</option>' + opts.join(''));
    }, 'json');
};

const closeModal = () => {
    $('#adminTesteModal').removeClass('is-open');
    $('body').removeClass('modal-open');
};

const atualizarAviso = () => {
    const selected = $('#testeTurma option:selected');
    const vagas = parseInt(selected.data('vagas'));
    const aviso = $('#testeAviso');
    if (!selected.val()) { aviso.text('').hide(); return; }
    if (isNaN(vagas) || vagas > 0) {
        aviso.removeClass('is-fila').addClass('is-ok')
            .text(isNaN(vagas) ? 'Turma sem limite de vagas.' : vagas + ' vaga(s) de teste disponível(is).').show();
    } else {
        aviso.removeClass('is-ok').addClass('is-fila')
            .text('Sem vagas de teste disponíveis — o aluno entrará na fila de espera para teste.').show();
    }
};

const submitForm = (e) => {
    e.preventDefault();
    const nome    = $('#testeNome').val().trim();
    const email   = $('#testeEmail').val().trim();
    const celular = $('#testeCelular').val().trim();
    const turmaId = $('#testeTurma').val();
    const data    = $('#testeData').val();
    const isMenor = $('#testeMenorCheck').is(':checked') ? 1 : 0;
    const dataNasc    = isMenor ? $('#testeDataNasc').val() : '';
    const respNome    = isMenor ? $('#testeRespNome').val().trim() : '';
    const respEmail   = isMenor ? $('#testeRespEmail').val().trim() : '';
    const respCpf     = isMenor ? $('#testeRespCpf').val().trim() : '';
    const respCelular = isMenor ? $('#testeRespCelular').val().trim() : '';

    if (!nome || !turmaId) { alert('Preencha o nome e selecione a turma.'); return; }

    const btn = $('#adminTesteSubmitBtn').prop('disabled', true).text('...');

    $.post(ADMIN_BASE_URL + '/services/add_aluno_teste.php', {
        nome, email, celular, turma_id: turmaId, data_agendada: data,
        is_menor: isMenor,
        data_nascimento: dataNasc,
        responsavel_nome: respNome, responsavel_email: respEmail,
        responsavel_cpf: respCpf, responsavel_celular: respCelular,
    }, (res) => {
        if (res.success) { closeModal(); carregarDados(); }
        else { btn.prop('disabled', false).text('Cadastrar'); alert(res.message || 'Erro ao cadastrar.'); }
    }, 'json').fail((xhr) => {
        btn.prop('disabled', false).text('Cadastrar');
        try { alert(JSON.parse(xhr.responseText).message || 'Erro.'); } catch(e2) { alert('Erro ao comunicar.'); }
    });
};

// ── Modal: EDITAR ─────────────────────────────────────────────────────────────

const openEditModal = (f) => {
    const menor = !!(f.menor);
    $('#editId').val(f.id);
    $('#editNome').val(f.nome || '');
    $('#editEmail').val(f.email || '');
    $('#editCelular').val(f.celular || '');
    $('#editData').val(f.data_agendada || '');
    $('#editMenorCheck').prop('checked', menor);
    $('#editDataNasc').val(f.data_nascimento || '');
    $('#editRespNome').val(f.responsavel_nome || '');
    $('#editRespEmail').val(f.responsavel_email || '');
    $('#editRespCpf').val(f.responsavel_cpf || '');
    $('#editRespCelular').val(f.responsavel_celular || '');
    $('#editResponsavelSection').toggle(menor);
    $('#adminTesteEditSubmitBtn').prop('disabled', false).text('Salvar alterações');

    $('#editTurma').html('<option value="">Carregando...</option>');
    $.get(ADMIN_BASE_URL + '/services/get_turmas_para_teste.php', (res) => {
        if (!res.success) { $('#editTurma').html('<option value="">Erro</option>'); return; }
        const opts = res.turmas.map(t =>
            '<option value="' + t.id + '">' + t.nome + ' — ' + t.quadra_nome + '</option>'
        );
        $('#editTurma').html('<option value="">Selecione...</option>' + opts.join(''));
        if (f.turma_id) $('#editTurma').val(f.turma_id);
    }, 'json');

    $('#adminTesteEditModal').addClass('is-open');
    $('body').addClass('modal-open');
};

const closeEditModal = () => {
    $('#adminTesteEditModal').removeClass('is-open');
    $('body').removeClass('modal-open');
};

const submitEditForm = (e) => {
    e.preventDefault();
    const id      = $('#editId').val();
    const nome    = $('#editNome').val().trim();
    const turmaId = $('#editTurma').val();
    if (!nome || !turmaId) { alert('Preencha nome e turma.'); return; }

    const btn = $('#adminTesteEditSubmitBtn').prop('disabled', true).text('Salvando...');

    const isMenorEdit = $('#editMenorCheck').is(':checked') ? 1 : 0;
    $.post(ADMIN_BASE_URL + '/services/update_aluno_teste.php', {
        id,
        nome,
        email:             $('#editEmail').val().trim(),
        celular:           $('#editCelular').val().trim(),
        is_menor:          isMenorEdit,
        data_nascimento:   isMenorEdit ? $('#editDataNasc').val() : '',
        turma_id:          turmaId,
        data_agendada:     $('#editData').val(),
        responsavel_nome:    isMenorEdit ? $('#editRespNome').val().trim() : '',
        responsavel_email:   isMenorEdit ? $('#editRespEmail').val().trim() : '',
        responsavel_cpf:     isMenorEdit ? $('#editRespCpf').val().trim() : '',
        responsavel_celular: isMenorEdit ? $('#editRespCelular').val().trim() : '',
    }, (res) => {
        if (res.success) { closeEditModal(); carregarDados(); }
        else { btn.prop('disabled', false).text('Salvar alterações'); alert(res.message || 'Erro ao salvar.'); }
    }, 'json').fail(() => {
        btn.prop('disabled', false).text('Salvar alterações');
        alert('Erro ao comunicar com o servidor.');
    });
};

// ── Modal: ASSINAR ESCOLA ─────────────────────────────────────────────────────

let adminsLista = [];

const openAssinarModal = (aulaId) => {
    $('#assinarAulaId').val(aulaId);
    $('#assinarAdminSelect').html('<option value="">Carregando...</option>');
    $('#assinarPreview').html('');
    $('#adminTesteAssinarSubmitBtn').prop('disabled', false).text('✍ Confirmar assinatura');
    $('#adminTesteAssinarModal').addClass('is-open');
    $('body').addClass('modal-open');

    if (adminsLista.length) {
        populateAdminSelect();
    } else {
        $.get(ADMIN_BASE_URL + '/services/get_admins_lista.php', (res) => {
            if (res.success) { adminsLista = res.admins; populateAdminSelect(); }
            else { $('#assinarAdminSelect').html('<option value="">Erro ao carregar</option>'); }
        }, 'json');
    }
};

const populateAdminSelect = () => {
    const opts = adminsLista.map(a =>
        '<option value="' + a.id + '" data-nome="' + $('<span>').text(a.nome_completo).html() + '">' +
        a.nome_completo + ' (' + a.nivel_acesso + ')' +
        '</option>'
    );
    $('#assinarAdminSelect').html('<option value="">Selecione o responsável...</option>' + opts.join(''));
    updateAssinarPreview();
};

const updateAssinarPreview = () => {
    const nome = $('#assinarAdminSelect option:selected').data('nome') || '';
    $('#assinarPreview').html(nome
        ? '<div class="adminTesteModal__assPreviewNome">' + $('<span>').text(nome).html() + '</div>' +
          '<div class="adminTesteModal__assPreviewLabel">MPG Academy</div>'
        : '');
};

const closeAssinarModal = () => {
    $('#adminTesteAssinarModal').removeClass('is-open');
    $('body').removeClass('modal-open');
};

const submitAssinar = () => {
    const aulaId    = $('#assinarAulaId').val();
    const sel       = $('#assinarAdminSelect option:selected');
    const adminId   = sel.val();
    const adminNome = sel.data('nome');
    if (!adminId) { alert('Selecione o responsável pela escola.'); return; }

    const btn = $('#adminTesteAssinarSubmitBtn').prop('disabled', true).text('Assinando...');

    $.post(ADMIN_BASE_URL + '/services/assinar_escola.php', {
        aula_id: aulaId, admin_id: adminId, admin_nome: adminNome,
    }, (res) => {
        if (res.success) { closeAssinarModal(); carregarDados(); }
        else { btn.prop('disabled', false).text('✍ Confirmar assinatura'); alert(res.message || 'Erro.'); }
    }, 'json').fail(() => {
        btn.prop('disabled', false).text('✍ Confirmar assinatura');
        alert('Erro ao comunicar com o servidor.');
    });
};

// ── Ações nos itens ───────────────────────────────────────────────────────────

const atualizar = (id, action, btn) => {
    const labels = { realizar: 'Realizando...', cancelar: 'Cancelando...', promover: 'Promovendo...' };
    btn.prop('disabled', true).text(labels[action] || '...');

    $.post(ADMIN_BASE_URL + '/services/update_aula_experimental.php', { id, action }, (res) => {
        if (res.success) { carregarDados(); }
        else { btn.prop('disabled', false).text(btn.data('label')); alert(res.message || 'Erro ao atualizar.'); }
    }, 'json').fail(() => {
        btn.prop('disabled', false).text(btn.data('label'));
        alert('Erro ao comunicar com o servidor.');
    });
};

// ── Init ──────────────────────────────────────────────────────────────────────

$(document).ready(() => {
    carregarDados();

    $(document).on('click', '#btnNovoTeste', openModal);
    $(document).on('click', '#adminTesteModalClose, #adminTesteModalOverlay, #adminTesteCancelBtn', closeModal);
    $(document).on('keydown', (e) => { if (e.key === 'Escape') { closeModal(); closeEditModal(); closeAssinarModal(); } });

    $(document).on('input', '#testeCelular, #editCelular, #testeRespCelular, #editRespCelular', function () {
        const cur = this.selectionStart, prev = this.value.length;
        this.value = maskPhone(this.value);
        const diff = this.value.length - prev;
        this.setSelectionRange(cur + diff, cur + diff);
    });

    $(document).on('input', '#testeRespCpf, #editRespCpf', function () {
        this.value = maskCpf(this.value);
    });

    $(document).on('change', '#testeMenorCheck', function () {
        const checked = $(this).is(':checked');
        $('#testeResponsavelSection').toggle(checked);
        if (!checked) {
            $('#testeDataNasc, #testeRespNome, #testeRespEmail, #testeRespCpf, #testeRespCelular').val('');
        }
    });
    $(document).on('change', '#editMenorCheck', function () {
        const checked = $(this).is(':checked');
        $('#editResponsavelSection').toggle(checked);
        if (!checked) {
            $('#editDataNasc, #editRespNome, #editRespEmail, #editRespCpf, #editRespCelular').val('');
        }
    });

    $(document).on('change', '#testeTurma', atualizarAviso);
    $(document).on('submit', '#adminTesteForm', submitForm);

    $(document).on('click', '.btn--editarTeste', function () {
        const f = JSON.parse($(this).attr('data-item'));
        openEditModal(f);
    });
    $(document).on('click', '#adminTesteEditClose, #adminTesteEditOverlay, #adminTesteEditCancelBtn', closeEditModal);
    $(document).on('submit', '#adminTesteEditForm', submitEditForm);

    $(document).on('click', '.btn--assinarEscola', function () {
        openAssinarModal($(this).data('id'));
    });
    $(document).on('click', '#adminTesteAssinarClose, #adminTesteAssinarOverlay, #adminTesteAssinarCancelBtn', closeAssinarModal);
    $(document).on('click', '#adminTesteAssinarSubmitBtn', submitAssinar);
    $(document).on('change', '#assinarAdminSelect', updateAssinarPreview);

    $(document).on('click', '.btn--enviarTermo', function () {
        const btn = $(this);
        const aulaId = btn.data('id');
        if (!confirm('Enviar o termo de responsabilidade por e-mail ao responsável cadastrado?')) return;
        btn.prop('disabled', true).text('Enviando...');
        $.post(ADMIN_BASE_URL + '/services/enviar_termo_responsavel.php', { aula_id: aulaId }, (res) => {
            if (res.success) { btn.text('✓ Enviado').css('opacity', '.6'); carregarDados(); }
            else { btn.prop('disabled', false).text('📧 Enviar termo resp.'); alert(res.message || 'Erro.'); }
        }, 'json').fail(() => {
            btn.prop('disabled', false).text('📧 Enviar termo resp.');
            alert('Erro ao comunicar com o servidor.');
        });
    });

    $(document).on('click', '.btn--realizar', function () {
        const id = parseInt($(this).data('id'));
        if (confirm('Confirmar que a aula experimental foi realizada?')) {
            $(this).data('label', $(this).text());
            atualizar(id, 'realizar', $(this));
        }
    });

    $(document).on('click', '.btn--cancelarTeste', function () {
        const id = parseInt($(this).data('id'));
        if (confirm('Deseja cancelar este registro de aula teste?')) {
            $(this).data('label', $(this).text());
            atualizar(id, 'cancelar', $(this));
        }
    });

    $(document).on('click', '#btnToggleRealizados', function () {
        const body = $('#realizadosBody');
        const hidden = body.is(':hidden');
        body.toggle();
        $(this).text(hidden ? 'Ocultar' : 'Ver');
    });

    $(document).on('click', '.btn--promoverTeste', function () {
        const id = parseInt($(this).data('id'));
        if (confirm('Deseja promover este aluno da fila para aula agendada?')) {
            $(this).data('label', $(this).text());
            atualizar(id, 'promover', $(this));
        }
    });

    $(document).on('click', '.btn--enviarEmailCadastro', function () {
        const btn   = $(this);
        const nome  = btn.data('nome');
        const email = btn.data('email');
        if (!confirm('Enviar email de cadastro para ' + nome + ' (' + email + ')?')) return;
        btn.prop('disabled', true).text('Enviando...');
        $.post(ADMIN_BASE_URL + '/services/enviar_email_cadastro.php', { nome, email }, (res) => {
            if (res.success) btn.text('✓ Enviado').css('opacity', '0.6');
            else { btn.prop('disabled', false).text('Enviar email de cadastro'); alert(res.message || 'Erro.'); }
        }, 'json').fail(() => {
            btn.prop('disabled', false).text('Enviar email de cadastro');
            alert('Erro ao comunicar com o servidor.');
        });
    });
});
