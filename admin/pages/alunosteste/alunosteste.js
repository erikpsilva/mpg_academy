
const NIVEL_LABEL = { iniciante: 'Iniciante', intermediario: 'Intermediário', avancado: 'Avançado' };

const maskPhone = (v) => {
    v = v.replace(/\D/g, '').slice(0, 11);
    if (v.length === 11) return v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    if (v.length >= 7)   return v.replace(/^(\d{2})(\d{4,5})(\d{0,4})/, '($1) $2-$3');
    if (v.length >= 3)   return v.replace(/^(\d{2})(\d+)/, '($1) $2');
    if (v.length >= 1)   return '(' + v;
    return v;
};

let turmasParaTeste = []; // carregado ao abrir o modal

const fmtData = (str) => {
    if (!str) return '—';
    const [y, m, d] = str.split('-');
    return d + '/' + m + '/' + y;
};

const fmtDataCriado = (str) => {
    const d = new Date(str);
    return d.toLocaleDateString('pt-BR');
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

    return '<div class="adminTesteItem">' +
        '<div class="adminTesteItem__info">' +
            '<strong>' + $('<span>').text(f.nome).html() + '</strong>' +
            '<span>' +
                (f.email  ? $('<span>').text(f.email).html()   : '') +
                (f.email && f.celular ? ' &nbsp;·&nbsp; ' : '') +
                (f.celular ? $('<span>').text(f.celular).html() : '') +
                (!f.email && !f.celular ? '<em>Sem contato</em>' : '') +
            '</span>' +
        '</div>' +
        '<span class="adminTesteItem__data">' + dtLabel + '</span>' +
        '<div class="adminTesteItem__acoes">' + acoes + '</div>' +
    '</div>';
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
            turma.agendados.map(f => renderItem(f, 'agendado')).join('') +
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

const renderPage = (turmas, realizados) => {
    const body = $('#adminTesteBody');
    const ativos = turmas.length
        ? turmas.map(renderBloco).join('')
        : '<p class="adminTeste__empty">Nenhuma aula experimental agendada no momento.</p>';

    body.html(ativos + renderRealizados(realizados));
};

const renderRealizados = (lista) => {
    if (!lista.length) return '';

    const rows = lista.map(r =>
        '<tr>' +
            '<td>' + $('<span>').text(r.nome).html() + '</td>' +
            '<td>' + (r.email   ? $('<span>').text(r.email).html()   : '<em>—</em>') + '</td>' +
            '<td>' + (r.celular ? $('<span>').text(r.celular).html() : '<em>—</em>') + '</td>' +
            '<td>' + $('<span>').text(r.turma_nome + ' · ' + r.quadra_nome).html() + '</td>' +
            '<td>' + fmtDataCriado(r.criado_em) + '</td>' +
        '</tr>'
    ).join('');

    return '<div class="adminTesteRealizados" id="adminTesteRealizados">' +
        '<div class="adminTesteRealizados__head">' +
            '<span>Já realizaram aula experimental</span>' +
            '<span class="adminTesteRealizados__count">' + lista.length + ' pessoa' + (lista.length === 1 ? '' : 's') + '</span>' +
            '<button class="adminTesteRealizados__toggle" id="btnToggleRealizados">Ocultar</button>' +
        '</div>' +
        '<div class="adminTesteRealizados__body" id="realizadosBody">' +
            '<table class="adminTesteRealizados__table">' +
                '<thead><tr>' +
                    '<th>Nome</th><th>E-mail</th><th>Celular</th><th>Turma</th><th>Data</th>' +
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

// ── Modal ─────────────────────────────────────────────────────────────────────

const openModal = () => {
    $('#testeNome, #testeEmail, #testeCelular').val('');
    $('#testeData').val(new Date().toISOString().split('T')[0]);
    $('#testeAviso').text('').hide();
    $('#adminTesteSubmitBtn').prop('disabled', false).text('Cadastrar');
    $('#testeTurma').html('<option value="">Carregando turmas...</option>');
    $('#adminTesteModal').addClass('is-open');
    $('body').addClass('modal-open');

    $.get(ADMIN_BASE_URL + '/services/get_turmas_para_teste.php', (res) => {
        if (!res.success) {
            $('#testeTurma').html('<option value="">Erro ao carregar turmas</option>');
            return;
        }
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
            .text(isNaN(vagas) ? 'Turma sem limite de vagas.' : vagas + ' vaga(s) de teste disponível(is).')
            .show();
    } else {
        aviso.removeClass('is-ok').addClass('is-fila')
            .text('Sem vagas de teste disponíveis — o aluno entrará na fila de espera para teste.')
            .show();
    }
};

const submitForm = (e) => {
    e.preventDefault();
    const nome    = $('#testeNome').val().trim();
    const email   = $('#testeEmail').val().trim();
    const celular = $('#testeCelular').val().trim();
    const turmaId = $('#testeTurma').val();
    const data    = $('#testeData').val();

    if (!nome || !turmaId) {
        alert('Preencha o nome e selecione a turma.');
        return;
    }

    const btn = $('#adminTesteSubmitBtn').prop('disabled', true).text('...');

    $.post(ADMIN_BASE_URL + '/services/add_aluno_teste.php', {
        nome, email, celular,
        turma_id:      turmaId,
        data_agendada: data,
    }, (res) => {
        if (res.success) {
            closeModal();
            carregarDados();
        } else {
            btn.prop('disabled', false).text('Cadastrar');
            alert(res.message || 'Erro ao cadastrar.');
        }
    }, 'json').fail((xhr) => {
        btn.prop('disabled', false).text('Cadastrar');
        try {
            const r = JSON.parse(xhr.responseText);
            alert(r.message || 'Erro ao cadastrar.');
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    });
};

// ── Ações nos itens ───────────────────────────────────────────────────────────

const atualizar = (id, action, btn) => {
    const labels = { realizar: 'Realizando...', cancelar: 'Cancelando...', promover: 'Promovendo...' };
    btn.prop('disabled', true).text(labels[action] || '...');

    $.post(ADMIN_BASE_URL + '/services/update_aula_experimental.php', { id, action }, (res) => {
        if (res.success) {
            carregarDados();
        } else {
            btn.prop('disabled', false).text(btn.data('label'));
            alert(res.message || 'Erro ao atualizar.');
        }
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
    $(document).on('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    $(document).on('input', '#testeCelular', function () {
        const cur = this.selectionStart;
        const prev = this.value.length;
        this.value = maskPhone(this.value);
        const diff = this.value.length - prev;
        this.setSelectionRange(cur + diff, cur + diff);
    });

    $(document).on('change', '#testeTurma', atualizarAviso);
    $(document).on('submit', '#adminTesteForm', submitForm);

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
});
