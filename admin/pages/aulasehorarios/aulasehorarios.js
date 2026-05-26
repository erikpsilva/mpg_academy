
// ─── LISTA ───────────────────────────────────────────────────────────────────

const formatValor = (v) => v != null
    ? 'R$ ' + parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : '<span class="aulas__naoConfig">Não configurado</span>';

const formatData = (d) => d ? d.split('-').reverse().join('/') : '—';

const renderTabela = (registros) => {
    const tbody = $('#turmasTableBody');
    if (!registros || registros.length === 0) {
        tbody.html('<tr><td colspan="9" class="interessados__empty">Nenhuma turma encontrada.</td></tr>');
        return;
    }
    const rows = registros.map(r =>
        '<tr>' +
            '<td class="interessados__id">' + r.id + '</td>' +
            '<td><strong>' + $('<span>').text(r.nome).html() + '</strong></td>' +
            '<td>' + $('<span>').text(r.quadra_nome || '—').html() + '</td>' +
            '<td>' + $('<span>').text(r.dias_label || '—').html() + '</td>' +
            '<td>' + formatValor(r.valor_mensalidade) + '</td>' +
            '<td>' + formatData(r.data_inicio) + '</td>' +
            '<td class="aulas__centerCell">' + r.total_alunos + '</td>' +
            '<td><span class="statusBadge statusBadge--' + (r.status === 'ativa' ? 'ativo' : 'inativo') + '">' + r.status.toUpperCase() + '</span></td>' +
            '<td><a href="' + BASE_URL + '/admin/aulasehorarios?id=' + r.id + '" class="btn btn--primary aulas__btnConfig">Configurar</a></td>' +
        '</tr>'
    ).join('');
    tbody.html(rows);
};

const renderPaginacao = (pagina, totalPaginas) => {
    const wrap = $('#paginacaoControles');
    let html = '';
    html += '<button class="btn btn--pag" ' + (pagina > 1 ? 'data-pag="' + (pagina - 1) + '"' : 'disabled') + '>&#8592; Anterior</button>';
    const inicio = Math.max(1, pagina - 2);
    const fim    = Math.min(totalPaginas, pagina + 2);
    for (let p = inicio; p <= fim; p++) {
        const ativo = p === pagina ? ' btn--pag--ativo' : '';
        html += '<button class="btn btn--pag' + ativo + '" data-pag="' + p + '">' + p + '</button>';
    }
    html += '<span class="aulas__pageInfo">Página ' + pagina + ' de ' + totalPaginas + '</span>';
    html += '<button class="btn btn--pag" ' + (pagina < totalPaginas ? 'data-pag="' + (pagina + 1) + '"' : 'disabled') + '>Próxima &#8594;</button>';
    wrap.html(html);
};

const carregarTurmas = (pagina, busca) => {
    const tbody = $('#turmasTableBody');
    tbody.html('<tr><td colspan="9" class="interessados__loading">Carregando...</td></tr>');

    $.get(ADMIN_BASE_URL + '/services/get_turmas.php', { pagina, busca }, (res) => {
        if (!res.success) {
            tbody.html('<tr><td colspan="9" class="interessados__empty">Erro ao carregar dados.</td></tr>');
            return;
        }
        $('#totalGeral').text(res.totalGeral);
        $('#resultCount').html(
            busca
                ? res.total + ' resultado(s) para "' + $('<span>').text(busca).html() + '"'
                : res.total + ' turma(s)'
        );
        renderTabela(res.registros);
        renderPaginacao(res.pagina, res.totalPaginas);
    }, 'json').fail(() => {
        tbody.html('<tr><td colspan="9" class="interessados__empty">Erro ao comunicar com o servidor.</td></tr>');
    });
};

// ─── DETALHE — CONFIGURAÇÃO ──────────────────────────────────────────────────

const salvarConfig = () => {
    $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');

    $.post(ADMIN_BASE_URL + '/services/save_turma_config.php', {
        id:                TURMA_ID,
        valor_mensalidade: $('#valorMensalidade').val(),
        data_inicio:       $('#dataInicio').val(),
        status:            $('#statusTurma').val(),
    }, (res) => {
        $('.overlayForm').remove();
        if (res.success) {
            const badge = $('.statusBadge');
            const st = $('#statusTurma').val();
            badge.attr('class', 'statusBadge statusBadge--' + (st === 'ativa' ? 'ativo' : 'inativo'));
            badge.text(st.toUpperCase());
            showToast(res.message);
        } else {
            alert(res.message || 'Erro ao salvar.');
        }
    }, 'json').fail(() => {
        $('.overlayForm').remove();
        alert('Erro ao comunicar com o servidor.');
    });
};

const showToast = (msg) => {
    const t = $('<div class="aulas__toast">' + msg + '</div>');
    $('body').append(t);
    setTimeout(() => t.addClass('aulas__toast--show'), 10);
    setTimeout(() => { t.removeClass('aulas__toast--show'); setTimeout(() => t.remove(), 300); }, 2800);
};

// ─── DETALHE — ALUNOS ────────────────────────────────────────────────────────

let debounceAluno;

const alunoItemHtml = (a) =>
    '<div class="aulas__alunoItem" data-aluno-id="' + a.id + '">' +
        '<div class="aulas__alunoInfo">' +
            '<strong class="aulas__alunoNome">' + $('<span>').text(a.nome).html() + '</strong>' +
            '<span class="aulas__alunoEmail">' + $('<span>').text(a.email).html() + '</span>' +
        '</div>' +
        '<span class="aulas__alunoData">desde ' + formatData(a.data_entrada) + '</span>' +
        '<button type="button" class="btn btn--gray aulas__btnRemoverAluno" data-aluno-id="' + a.id + '">Remover</button>' +
    '</div>';

const addAlunoTurma = (alunoId) => {
    $.post(ADMIN_BASE_URL + '/services/add_aluno_turma.php', { turma_id: TURMA_ID, aluno_id: alunoId }, (res) => {
        if (res.success) {
            $('#alunosEmpty').remove();
            $('#alunosLista').append(alunoItemHtml(res.aluno));
            atualizarContador(1);
            $('#buscaAluno').val('');
            $('#alunoSuggestions').empty().hide();
        } else {
            alert(res.message || 'Erro ao adicionar aluno.');
        }
    }, 'json').fail(() => alert('Erro ao comunicar com o servidor.'));
};

const removeAlunoTurma = (alunoId) => {
    if (!confirm('Remover este aluno da turma?')) return;
    $.post(ADMIN_BASE_URL + '/services/remove_aluno_turma.php', { turma_id: TURMA_ID, aluno_id: alunoId }, (res) => {
        if (res.success) {
            $('.aulas__alunoItem[data-aluno-id="' + alunoId + '"]').remove();
            atualizarContador(-1);
            if ($('#alunosLista .aulas__alunoItem').length === 0) {
                $('#alunosLista').prepend('<p class="aulas__empty" id="alunosEmpty">Nenhum aluno nesta turma ainda.</p>');
            }
        } else {
            alert(res.message || 'Erro ao remover aluno.');
        }
    }, 'json').fail(() => alert('Erro ao comunicar com o servidor.'));
};

const atualizarContador = (delta) => {
    const el = $('#alunoCount');
    const n  = (parseInt(el.data('count') || el.text()) || 0) + delta;
    el.text(n + ' aluno(s)').data('count', n);
};

// ─── DETALHE — CALENDÁRIO ────────────────────────────────────────────────────

const DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

const renderCalendario = (treinos) => {
    const body = $('#calendarBody');
    if (!treinos || treinos.length === 0) {
        body.html('<p class="aulas__empty">Nenhum treino gerado. Configure a data de início e clique em "Gerar Calendário".</p>');
        return;
    }

    // Group by year-month
    const grouped = {};
    treinos.forEach(t => {
        const ym = t.data_treino.substring(0, 7);
        if (!grouped[ym]) grouped[ym] = [];
        grouped[ym].push(t);
    });

    let html = '';
    Object.keys(grouped).sort().forEach(ym => {
        const [y, m] = ym.split('-');
        html += '<div class="aulas__calMes">';
        html += '<h5 class="aulas__calMesTitulo">' + MESES[parseInt(m) - 1] + ' ' + y + '</h5>';
        html += '<div class="aulas__calItens">';
        grouped[ym].forEach(t => {
            const date = new Date(t.data_treino + 'T00:00:00');
            const dow  = date.getDay();
            const dataFmt = t.data_treino.split('-').reverse().join('/');
            html += '<div class="aulas__calItem aulas__calItem--' + t.status + '" data-id="' + t.id + '">' +
                '<span class="aulas__calDia">' + DIAS_SEMANA[dow] + ', ' + dataFmt + '</span>' +
                '<span class="aulas__calStatus statusBadge statusBadge--treino-' + t.status + '">' + t.status + '</span>' +
                '<div class="aulas__calBtns">' +
                    (t.status !== 'realizado'  ? '<button class="btn btn--gray aulas__calBtn" data-id="' + t.id + '" data-status="realizado">✓ Realizado</button>' : '') +
                    (t.status !== 'cancelado'  ? '<button class="btn btn--gray aulas__calBtn" data-id="' + t.id + '" data-status="cancelado">✕ Cancelar</button>' : '') +
                    (t.status !== 'agendado'   ? '<button class="btn btn--gray aulas__calBtn" data-id="' + t.id + '" data-status="agendado">↩ Reagendar</button>' : '') +
                '</div>' +
            '</div>';
        });
        html += '</div></div>';
    });
    body.html(html);
};

const carregarCalendario = () => {
    $('#calendarBody').html('<p class="aulas__empty">Carregando...</p>');
    $.get(ADMIN_BASE_URL + '/services/get_turma_treinos.php', { turma_id: TURMA_ID }, (res) => {
        renderCalendario(res.treinos);
    }, 'json');
};

const gerarCalendario = () => {
    const dataFim = $('#calDataFim').val();
    if (!dataFim) { alert('Selecione a data fim.'); return; }

    $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');

    $.post(ADMIN_BASE_URL + '/services/gerar_calendario.php', { turma_id: TURMA_ID, data_fim: dataFim }, (res) => {
        $('.overlayForm').remove();
        if (res.success) {
            showToast(res.message);
            carregarCalendario();
        } else {
            alert(res.message || 'Erro ao gerar calendário.');
        }
    }, 'json').fail(() => {
        $('.overlayForm').remove();
        alert('Erro ao comunicar com o servidor.');
    });
};

// ─── INIT ─────────────────────────────────────────────────────────────────────

$(document).ready(() => {

    if (AULAS_VIEW === 'lista') {
        carregarTurmas(1, '');

        let timer;
        $('#buscaTurmas').on('input', function () {
            clearTimeout(timer);
            const busca = $(this).val().trim();
            timer = setTimeout(() => carregarTurmas(1, busca), 400);
        });

        $(document).on('click', '.btn--pag', function () {
            carregarTurmas(parseInt($(this).data('pag')), $('#buscaTurmas').val().trim());
        });

        return;
    }

    // ── Modo detalhe ─────────────────────────────────────────────

    $('#btnSalvarConfig').on('click', salvarConfig);

    // Aluno search
    $('#buscaAluno').on('input', function () {
        clearTimeout(debounceAluno);
        const busca = $(this).val().trim();
        if (busca.length < 2) { $('#alunoSuggestions').empty().hide(); return; }

        debounceAluno = setTimeout(() => {
            $.get(ADMIN_BASE_URL + '/services/search_alunos_disponiveis.php', { turma_id: TURMA_ID, busca }, (res) => {
                const box = $('#alunoSuggestions');
                if (!res.alunos || res.alunos.length === 0) { box.html('<div class="aulas__suggItem aulas__suggNone">Nenhum aluno encontrado.</div>').show(); return; }
                box.html(res.alunos.map(a =>
                    '<div class="aulas__suggItem" data-id="' + a.id + '">' +
                        '<strong>' + $('<span>').text(a.nome).html() + '</strong>' +
                        '<span>' + $('<span>').text(a.email).html() + '</span>' +
                    '</div>'
                ).join('')).show();
            }, 'json');
        }, 300);
    });

    $(document).on('click', '.aulas__suggItem[data-id]', function () {
        addAlunoTurma(parseInt($(this).data('id')));
    });

    $(document).on('click', '.aulas__btnRemoverAluno', function () {
        removeAlunoTurma(parseInt($(this).data('aluno-id')));
    });

    // Close suggestions on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.aulas__searchWrap').length) {
            $('#alunoSuggestions').hide();
        }
    });

    // Calendar
    carregarCalendario();
    $('#btnGerarCal').on('click', gerarCalendario);

    $(document).on('click', '.aulas__calBtn', function () {
        const id     = parseInt($(this).data('id'));
        const status = $(this).data('status');

        $.post(ADMIN_BASE_URL + '/services/update_treino_status.php', { id, status }, (res) => {
            if (res.success) carregarCalendario();
        }, 'json');
    });
});
