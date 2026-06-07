
const NIVEL_LABEL = { iniciante: 'Iniciante', intermediario: 'Intermediário', avancado: 'Avançado' };

const fmtData = (str) => {
    if (!str) return '—';
    const [y, m, d] = str.split('T')[0].split('-');
    return d + '/' + m + '/' + y;
};

const esc = (s) => $('<span>').text(s).html();

// ── Badge de termo ────────────────────────────────────────────────────────────

const renderTermoBadge = (r) => {
    if (!r.is_menor) return '<em>—</em>';
    const map = {
        concluido:              '<span class="badge badge--termo-ok">✅ Assinado</span>',
        aguardando_responsavel: '<span class="badge badge--termo-meio">🕐 Aguard. responsável</span>',
        aguardando_escola:      '<span class="badge badge--termo-meio">🕐 Aguard. escola</span>',
        pendente:               '<span class="badge badge--termo-pendente">📋 Pendente</span>',
        nao_gerado:             '<span class="badge badge--termo-pendente">📋 Não gerado</span>',
    };
    let html = '<div class="adminTodosTable__termoBox">' + (map[r.termo_status] || '<em>—</em>');
    if (r.responsavel_nome) {
        html += '<small class="adminTodosTable__resp">Resp.: ' + esc(r.responsavel_nome) + '</small>';
    }
    if (r.termo_token) {
        html += '<a class="adminTodosTable__verTermo" href="' + BASE_URL + '/termo?token=' + esc(r.termo_token) + '" target="_blank">🔗 Ver termo</a>';
    }
    return html + '</div>';
};

const renderNome = (r) => {
    const menorBadge = r.is_menor ? '<span class="badge badge--menor">Menor</span>' : '';
    return '<strong class="adminTodosTable__nome">' + menorBadge + '<span>' + esc(r.nome) + '</span></strong>';
};

const renderCell = (label, content, className) => {
    return '<td data-label="' + esc(label) + '"' + (className ? ' class="' + className + '"' : '') + '>' + content + '</td>';
};

// ── Para fazer ────────────────────────────────────────────────────────────────

const renderParaFazer = (lista, startAt) => {
    if (!lista.length) return '<p class="adminTodosAlunos__empty">Nenhum aluno aguardando aula experimental.</p>';

    const rows = lista.map((r, i) => {
        const statusBadge = r.status === 'agendada'
            ? '<span class="badge badge--agendada">Agendada</span>'
            : '<span class="badge badge--fila">Na fila</span>';
        const dataLabel = r.status === 'agendada' && r.data_agendada
            ? fmtData(r.data_agendada)
            : fmtData(r.criado_em);
        return '<tr>' +
            renderCell('#', (startAt + i), 'col-num') +
            renderCell('Nome', renderNome(r), 'adminTodosTable__aluno') +
            renderCell('E-mail', (r.email   ? esc(r.email)   : '<em>—</em>'), 'adminTodosTable__email') +
            renderCell('Celular', (r.celular ? esc(r.celular) : '<em>—</em>')) +
            renderCell('Turma', esc(r.turma_nome + ' · ' + r.quadra_nome)) +
            renderCell('Status', statusBadge) +
            renderCell('Data', dataLabel) +
            renderCell('Termo', renderTermoBadge(r), 'adminTodosTable__termo') +
        '</tr>';
    }).join('');

    return '<div class="adminTodosSecao">' +
        '<div class="adminTodosSecao__head">' +
            '<h3>Para fazer</h3>' +
            '<span class="adminTodosSecao__count">' + lista.length + ' aluno' + (lista.length === 1 ? '' : 's') + '</span>' +
        '</div>' +
        '<div class="adminTodosSecao__body">' +
            '<table class="adminTodosTable">' +
                '<thead><tr><th class="col-num">#</th><th>Nome</th><th>E-mail</th><th>Celular</th><th>Turma</th><th>Status</th><th>Data</th><th>Termo</th></tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>' +
    '</div>';
};

// ── Já fizeram ────────────────────────────────────────────────────────────────

const renderAcao = (r) => {
    if (r.ja_aluno) {
        return '<span class="badge badge--aluno">✓ Já é aluno</span>';
    }
    if (r.na_fila) {
        return '<span class="badge badge--fila-espera">Na fila de espera' +
            (r.fila_turma_nome ? ': ' + esc(r.fila_turma_nome) : '') + '</span>';
    }

    let btns = '';
    if (r.email) {
        btns += '<button class="btn--acaoTodos btn--enviarEmail" ' +
            'data-id="' + r.aluno_teste_id + '" ' +
            'data-nome="' + esc(r.nome) + '" ' +
            'data-email="' + esc(r.email) + '">' +
            'Enviar email de cadastro' +
        '</button>';
    }
    btns += '<button class="btn--acaoTodos btn--adicionarFila" ' +
        'data-id="' + r.aluno_teste_id + '" ' +
        'data-nome="' + esc(r.nome) + '">' +
        'Colocar na fila de espera' +
    '</button>';
    return btns;
};

const renderJaFizeram = (lista, startAt) => {
    if (!lista.length) return '<p class="adminTodosAlunos__empty">Nenhum aluno concluiu aula experimental ainda.</p>';

    const rows = lista.map((r, i) => {
        const vagaInfo = r.vagas === null
            ? '<span class="badge badge--semLimite">Sem limite</span>'
            : r.vagas > 0
                ? '<span class="badge badge--comVaga">' + r.vagas + ' vaga' + (r.vagas === 1 ? '' : 's') + '</span>'
                : '<span class="badge badge--lotada">Lotada</span>';

        return '<tr' + (r.ja_aluno ? ' class="row--ja-aluno"' : '') + '>' +
            renderCell('#', (startAt + i), 'col-num') +
            renderCell('Nome', renderNome(r), 'adminTodosTable__aluno') +
            renderCell('E-mail', (r.email   ? esc(r.email)   : '<em>—</em>'), 'adminTodosTable__email') +
            renderCell('Celular', (r.celular ? esc(r.celular) : '<em>—</em>')) +
            renderCell('Turma', esc(r.turma_nome + ' · ' + r.quadra_nome) + ' ' + vagaInfo) +
            renderCell('Data', fmtData(r.criado_em)) +
            renderCell('Termo', renderTermoBadge(r), 'adminTodosTable__termo') +
            renderCell('Ação', renderAcao(r), 'adminTodosTable__acoes') +
        '</tr>';
    }).join('');

    return '<div class="adminTodosSecao adminTodosSecao--realizados">' +
        '<div class="adminTodosSecao__head">' +
            '<h3>Já fizeram</h3>' +
            '<span class="adminTodosSecao__count">' + lista.length + ' aluno' + (lista.length === 1 ? '' : 's') + '</span>' +
        '</div>' +
        '<div class="adminTodosSecao__body">' +
            '<table class="adminTodosTable">' +
                '<thead><tr><th class="col-num">#</th><th>Nome</th><th>E-mail</th><th>Celular</th><th>Turma onde fez o teste</th><th>Data</th><th>Termo</th><th>Ação</th></tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>' +
    '</div>';
};

// ── Carregar dados ────────────────────────────────────────────────────────────

const carregarDados = () => {
    $('#adminTodosBody').html('<div class="adminTodosAlunos__loading">Carregando...</div>');
    $.get(ADMIN_BASE_URL + '/services/get_todos_alunos_teste.php', (res) => {
        if (!res.success) {
            $('#adminTodosBody').html('<p class="adminTodosAlunos__empty">Erro ao carregar dados.</p>');
            return;
        }
        const offsetJaFizeram = (res.para_fazer.length || 0) + 1;
        $('#adminTodosBody').html(
            renderParaFazer(res.para_fazer, 1) +
            renderJaFizeram(res.ja_fizeram, offsetJaFizeram)
        );
    }, 'json').fail(() => {
        $('#adminTodosBody').html('<p class="adminTodosAlunos__empty">Erro ao comunicar com o servidor.</p>');
    });
};

// ── Modal fila ────────────────────────────────────────────────────────────────

let modalFilaAlunoId = null;
let turmasCache = [];

const abrirModalFila = (alunoTesteId, nomeAluno) => {
    modalFilaAlunoId = alunoTesteId;
    $('#modalFilaAluno').text(nomeAluno);
    $('#modalFilaTurma').html('<option value="">Carregando turmas...</option>');
    $('#modalFilaAviso').text('').hide();
    $('#modalFilaConfirmar').prop('disabled', false).text('Confirmar');
    $('#modalFila').addClass('is-open');
    $('body').addClass('modal-open');

    if (turmasCache.length) {
        preencherSelectTurmas(turmasCache);
        return;
    }
    $.get(ADMIN_BASE_URL + '/services/get_turmas_para_fila.php', (res) => {
        if (!res.success) {
            $('#modalFilaTurma').html('<option value="">Erro ao carregar turmas</option>');
            return;
        }
        turmasCache = res.turmas;
        preencherSelectTurmas(turmasCache);
    }, 'json');
};

const preencherSelectTurmas = (turmas) => {
    const opts = turmas.map(t => {
        const vagaInfo = t.vagas === null ? 'sem limite' : t.vagas + ' vaga' + (t.vagas === 1 ? '' : 's');
        return '<option value="' + t.id + '" data-lotada="' + (t.lotada ? '1' : '0') + '">' +
            esc(t.nome) + ' — ' + esc(t.quadra_nome) + ' (' + vagaInfo + ')' +
        '</option>';
    });
    $('#modalFilaTurma').html('<option value="">Selecione a turma...</option>' + opts.join(''));
};

const fecharModalFila = () => {
    $('#modalFila').removeClass('is-open');
    $('body').removeClass('modal-open');
    modalFilaAlunoId = null;
};

// ── Init ──────────────────────────────────────────────────────────────────────

$(document).ready(() => {
    carregarDados();

    // Enviar email de cadastro
    $(document).on('click', '.btn--enviarEmail', function () {
        const btn   = $(this);
        const nome  = btn.data('nome');
        const email = btn.data('email');
        if (!confirm('Enviar email de cadastro para ' + nome + ' (' + email + ')?')) return;
        btn.prop('disabled', true).text('Enviando...');
        $.post(ADMIN_BASE_URL + '/services/enviar_email_cadastro.php', { nome, email }, (res) => {
            if (res.success) {
                btn.text('✓ Enviado').css('opacity', '0.6');
            } else {
                btn.prop('disabled', false).text('Enviar email de cadastro');
                alert(res.message || 'Erro ao enviar e-mail.');
            }
        }, 'json').fail(() => {
            btn.prop('disabled', false).text('Enviar email de cadastro');
            alert('Erro ao comunicar com o servidor.');
        });
    });

    // Abrir modal de fila
    $(document).on('click', '.btn--adicionarFila', function () {
        abrirModalFila(parseInt($(this).data('id')), $(this).data('nome'));
    });

    // Fechar modal
    $(document).on('click', '#modalFilaClose, #modalFilaOverlay, #modalFilaCancelar', fecharModalFila);
    $(document).on('keydown', (e) => { if (e.key === 'Escape') fecharModalFila(); });

    // Aviso de turma lotada
    $(document).on('change', '#modalFilaTurma', function () {
        const selected = $(this).find('option:selected');
        const aviso = $('#modalFilaAviso');
        if (!selected.val()) { aviso.text('').hide(); return; }
        if (selected.data('lotada') == '1') {
            aviso.text('Esta turma está lotada — o aluno ficará na fila de espera até uma vaga abrir.').show();
        } else {
            aviso.text('').hide();
        }
    });

    // Confirmar adição à fila
    $(document).on('click', '#modalFilaConfirmar', function () {
        const turmaId = $('#modalFilaTurma').val();
        if (!turmaId) { alert('Selecione uma turma.'); return; }
        const btn = $(this).prop('disabled', true).text('Salvando...');

        $.post(ADMIN_BASE_URL + '/services/add_fila_espera_teste.php', {
            aluno_teste_id: modalFilaAlunoId,
            turma_id:       turmaId,
        }, (res) => {
            fecharModalFila();
            if (res.success) {
                carregarDados();
            } else {
                alert(res.message || 'Erro ao adicionar à fila.');
            }
        }, 'json').fail(() => {
            btn.prop('disabled', false).text('Confirmar');
            alert('Erro ao comunicar com o servidor.');
        });
    });
});
