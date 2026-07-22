
const fmtData = (str) => {
    if (!str) return '—';
    const [y, m, d] = str.split('T')[0].split('-');
    return d + '/' + m + '/' + y;
};

const esc = (s) => $('<span>').text(s).html();

const renderCell = (label, content, className) => {
    return '<td data-label="' + esc(label) + '"' + (className ? ' class="' + className + '"' : '') + '>' + content + '</td>';
};

const renderNome = (r) => {
    const menorBadge = r.is_menor ? '<span class="badge badge--menor">Menor</span>' : '';
    return '<strong class="adminTodosTable__nome">' + menorBadge + '<span>' + esc(r.nome) + '</span></strong>';
};

const renderResponsavel = (r) => {
    if (!r.is_menor) return '<em>—</em>';
    if (!r.responsavel_nome) return '<em>—</em>';
    let html = esc(r.responsavel_nome);
    if (r.responsavel_celular) html += '<br><small>' + esc(r.responsavel_celular) + '</small>';
    return html;
};

const renderAcao = (r) => {
    return '<button class="btn--acaoTodos btn--reagendarTeste" ' +
        'data-id="' + r.id + '" ' +
        'data-nome="' + esc(r.nome) + '" ' +
        'data-turma-id="' + r.turma_id + '">' +
        '↻ Reagendar' +
    '</button>';
};

const renderCancelados = (lista) => {
    if (!lista.length) {
        return '<p class="adminTodosAlunos__empty">Nenhum teste cancelado até o momento.</p>';
    }

    const rows = lista.map((r, i) => {
        const dataLabel = r.data_agendada ? fmtData(r.data_agendada) : fmtData(r.criado_em);
        return '<tr>' +
            renderCell('#', (i + 1), 'col-num') +
            renderCell('Nome', renderNome(r), 'adminTodosTable__aluno') +
            renderCell('E-mail', (r.email   ? esc(r.email)   : '<em>—</em>'), 'adminTodosTable__email') +
            renderCell('Celular', (r.celular ? esc(r.celular) : '<em>—</em>')) +
            renderCell('Turma', esc(r.turma_nome + ' · ' + r.quadra_nome)) +
            renderCell('Data agendada', dataLabel) +
            renderCell('Status', '<span class="badge badge--cancelada">Cancelado</span>') +
            renderCell('Responsável', renderResponsavel(r)) +
            renderCell('Ação', renderAcao(r), 'adminTodosTable__acoes') +
        '</tr>';
    }).join('');

    return '<div class="adminTodosSecao">' +
        '<div class="adminTodosSecao__head">' +
            '<h3>Cancelados</h3>' +
            '<span class="adminTodosSecao__count">' + lista.length + ' registro' + (lista.length === 1 ? '' : 's') + '</span>' +
        '</div>' +
        '<div class="adminTodosSecao__body">' +
            '<table class="adminTodosTable adminTodosTable--cancelados">' +
                '<thead><tr><th class="col-num">#</th><th>Nome</th><th>E-mail</th><th>Celular</th><th>Turma</th><th>Data agendada</th><th>Status</th><th>Responsável</th><th>Ação</th></tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>' +
    '</div>';
};

const carregarDados = () => {
    $('#adminCanceladosBody').html('<div class="adminTodosAlunos__loading">Carregando...</div>');
    $.get(ADMIN_BASE_URL + '/services/get_testes_cancelados.php', (res) => {
        if (!res.success) {
            $('#adminCanceladosBody').html('<p class="adminTodosAlunos__empty">Erro ao carregar dados.</p>');
            return;
        }
        $('#adminCanceladosBody').html(renderCancelados(res.cancelados));
    }, 'json').fail(() => {
        $('#adminCanceladosBody').html('<p class="adminTodosAlunos__empty">Erro ao comunicar com o servidor.</p>');
    });
};

// ── Modal reagendar ──────────────────────────────────────────────────────────

let modalReagendarAulaId = null;
let turmasCache = [];

const preencherSelectTurmasReagendar = (turmas, turmaIdAtual) => {
    const opts = turmas.map((t) => {
        const vagaInfo = t.vagas === null ? 'sem limite' : t.vagas + ' vaga' + (t.vagas === 1 ? '' : 's');
        return '<option value="' + t.id + '"' + (t.id === turmaIdAtual ? ' selected' : '') + '>' +
            esc(t.nome) + ' — ' + esc(t.quadra_nome) + ' (' + vagaInfo + ')' +
        '</option>';
    });
    $('#modalReagendarTurma').html(opts.join(''));
};

const abrirModalReagendar = (aulaId, nomeAluno, turmaIdAtual) => {
    modalReagendarAulaId = aulaId;
    $('#modalReagendarAluno').text(nomeAluno);
    $('#modalReagendarData').val('');
    $('#modalReagendarTurma').html('<option value="">Carregando turmas...</option>');
    $('#modalReagendarConfirmar').prop('disabled', false).text('Reagendar e avisar no WhatsApp');
    $('#modalReagendar').addClass('is-open');
    $('body').addClass('modal-open');

    if (turmasCache.length) {
        preencherSelectTurmasReagendar(turmasCache, turmaIdAtual);
        return;
    }
    $.get(ADMIN_BASE_URL + '/services/get_turmas_para_fila.php', (res) => {
        if (!res.success) {
            $('#modalReagendarTurma').html('<option value="">Erro ao carregar turmas</option>');
            return;
        }
        turmasCache = res.turmas;
        preencherSelectTurmasReagendar(turmasCache, turmaIdAtual);
    }, 'json');
};

const fecharModalReagendar = () => {
    $('#modalReagendar').removeClass('is-open');
    $('body').removeClass('modal-open');
    modalReagendarAulaId = null;
};

$(document).ready(() => {
    carregarDados();

    $(document).on('click', '.btn--reagendarTeste', function () {
        abrirModalReagendar(
            parseInt($(this).data('id')),
            $(this).data('nome'),
            parseInt($(this).data('turma-id'))
        );
    });

    $(document).on('click', '#modalReagendarClose, #modalReagendarOverlay, #modalReagendarCancelar', fecharModalReagendar);

    $(document).on('keydown', (e) => {
        if (e.key === 'Escape') fecharModalReagendar();
    });

    $(document).on('click', '#modalReagendarConfirmar', function () {
        const turmaId = $('#modalReagendarTurma').val();
        const dataAgendada = $('#modalReagendarData').val();
        if (!turmaId) { alert('Selecione a turma.'); return; }
        if (!dataAgendada) { alert('Selecione a nova data do teste.'); return; }

        const btn = $(this).prop('disabled', true).text('Reagendando...');

        $.post(ADMIN_BASE_URL + '/services/update_aula_experimental.php', {
            id:            modalReagendarAulaId,
            action:        'reagendar',
            turma_id:      turmaId,
            data_agendada: dataAgendada,
        }, (res) => {
            fecharModalReagendar();
            if (res.success) {
                carregarDados();
            } else {
                alert(res.message || 'Erro ao reagendar.');
            }
        }, 'json').fail(() => {
            btn.prop('disabled', false).text('Reagendar e avisar no WhatsApp');
            alert('Erro ao comunicar com o servidor.');
        });
    });
});
