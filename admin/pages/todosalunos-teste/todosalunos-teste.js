
const NIVEL_LABEL = { iniciante: 'Iniciante', intermediario: 'Intermediário', avancado: 'Avançado' };

// criado_em vem como timestamp ("2026-08-12 21:33:35"); data_agendada como date.
// Corta tanto no "T" do ISO quanto no espaço do MySQL antes de quebrar em partes.
const fmtData = (str) => {
    if (!str) return '—';
    const [y, m, d] = str.split(/[T ]/)[0].split('-');
    return d + '/' + m + '/' + y;
};

const esc = (s) => $('<span>').text(s).html();

// WhatsApp cadastrado: usa o do responsável quando o aluno é menor de idade
const whatsAppDoAluno = (r) => {
    const bruto = (r.is_menor && r.responsavel_celular) ? r.responsavel_celular : r.celular;
    const digitos = (bruto || '').replace(/\D/g, '');
    if (digitos.length < 10) return null;
    return {
        numero: digitos.length <= 11 ? '55' + digitos : digitos,
        label:  bruto,
        doResp: !!(r.is_menor && r.responsavel_celular),
    };
};

const btnWhatsApp = (r) => {
    const wa = whatsAppDoAluno(r);
    if (!wa) return '<em class="adminTodosTable__semWpp">Sem WhatsApp cadastrado</em>';
    return '<a class="btn--acaoTodos btn--whatsapp" target="_blank" rel="noopener" ' +
        'href="https://wa.me/' + wa.numero + '" ' +
        'title="' + esc(wa.label + (wa.doResp ? ' (responsável)' : '')) + '">' +
        '💬 Entrar em contato' + (wa.doResp ? ' (resp.)' : '') +
    '</a>';
};

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

// ── Já fizeram ────────────────────────────────────────────────────────────────

const renderAcao = (r) => {
    let btns = btnWhatsApp(r);

    if (r.ja_aluno) {
        return btns + '<span class="badge badge--aluno">✓ Já é aluno</span>';
    }
    if (r.na_fila) {
        return btns + '<span class="badge badge--fila-espera">Na fila de espera' +
            (r.fila_turma_nome ? ': ' + esc(r.fila_turma_nome) : '') + '</span>';
    }

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
            renderCell('Status', '<span class="badge badge--realizada">✅ Treino realizado</span>') +
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
                '<thead><tr><th class="col-num">#</th><th>Nome</th><th>E-mail</th><th>Celular</th><th>Status</th><th>Turma onde fez o teste</th><th>Data</th><th>Termo</th><th>Ação</th></tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
            '</table>' +
        '</div>' +
    '</div>';
};

// ── Cancelados ────────────────────────────────────────────────────────────────

const renderResponsavel = (r) => {
    if (!r.is_menor || !r.responsavel_nome) return '<em>—</em>';
    let html = esc(r.responsavel_nome);
    if (r.responsavel_celular) html += '<br><small>' + esc(r.responsavel_celular) + '</small>';
    return html;
};

const renderAcaoCancelado = (r) => {
    return '<button class="btn--acaoTodos btn--reagendarTeste" ' +
        'data-id="' + r.id + '" ' +
        'data-nome="' + esc(r.nome) + '" ' +
        'data-turma-id="' + r.turma_id + '">' +
        '↻ Reagendar' +
    '</button>';
};

const renderCancelados = (lista, startAt) => {
    if (!lista.length) return '<p class="adminTodosAlunos__empty">Nenhum teste cancelado até o momento.</p>';

    const rows = lista.map((r, i) => {
        const dataLabel = r.data_agendada ? fmtData(r.data_agendada) : fmtData(r.criado_em);
        return '<tr>' +
            renderCell('#', (startAt + i), 'col-num') +
            renderCell('Nome', renderNome(r), 'adminTodosTable__aluno') +
            renderCell('E-mail', (r.email   ? esc(r.email)   : '<em>—</em>'), 'adminTodosTable__email') +
            renderCell('Celular', (r.celular ? esc(r.celular) : '<em>—</em>')) +
            renderCell('Status', '<span class="badge badge--cancelada">Cancelado</span>') +
            renderCell('Turma', esc(r.turma_nome + ' · ' + r.quadra_nome)) +
            renderCell('Data agendada', dataLabel) +
            renderCell('Responsável', renderResponsavel(r)) +
            renderCell('Ação', renderAcaoCancelado(r), 'adminTodosTable__acoes') +
        '</tr>';
    }).join('');

    return '<div class="adminTodosSecao adminTodosSecao--cancelados">' +
        '<div class="adminTodosSecao__head">' +
            '<h3>Cancelaram</h3>' +
            '<span class="adminTodosSecao__count">' + lista.length + ' registro' + (lista.length === 1 ? '' : 's') + '</span>' +
        '</div>' +
        '<div class="adminTodosSecao__body">' +
            '<table class="adminTodosTable adminTodosTable--cancelados">' +
                '<thead><tr><th class="col-num">#</th><th>Nome</th><th>E-mail</th><th>Celular</th><th>Status</th><th>Turma</th><th>Data agendada</th><th>Responsável</th><th>Ação</th></tr></thead>' +
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
        const offsetCancelados = (res.ja_fizeram.length || 0) + 1;
        $('#adminTodosBody').html(
            renderJaFizeram(res.ja_fizeram, 1) +
            renderCancelados(res.cancelados, offsetCancelados)
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

// ── Modal reagendar ──────────────────────────────────────────────────────────

let modalReagendarAulaId = null;

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

// ── Init ──────────────────────────────────────────────────────────────────────

$(document).ready(() => {
    carregarDados();

    // Abrir modal de reagendamento
    $(document).on('click', '.btn--reagendarTeste', function () {
        abrirModalReagendar(
            parseInt($(this).data('id')),
            $(this).data('nome'),
            parseInt($(this).data('turma-id'))
        );
    });

    // Fechar modal de reagendamento
    $(document).on('click', '#modalReagendarClose, #modalReagendarOverlay, #modalReagendarCancelar', fecharModalReagendar);

    // Confirmar reagendamento
    $(document).on('click', '#modalReagendarConfirmar', function () {
        const turmaId      = $('#modalReagendarTurma').val();
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
    $(document).on('keydown', (e) => {
        if (e.key !== 'Escape') return;
        fecharModalFila();
        fecharModalReagendar();
    });

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
