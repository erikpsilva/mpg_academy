
const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const NIVEL_LABEL  = { iniciante: 'Iniciante', intermediario: 'Intermediário', avancado: 'Avançado' };
const GENERO_LABEL = { masculino: 'Masculino', feminino: 'Feminino', misto: 'Misto' };

const fmt = (n) => parseFloat(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

let todasTurmas     = [];
let filtroAtivo     = 'todos';
let modalTurmaId    = null;
let modalModo       = 'turma'; // 'turma' | 'fila'
let todosAlunos     = [];
let modalHasChanges = false;
let removeAlunoId   = null;
let removeTurmaId   = null;

const vagasClass = (vagas, max) => {
    if (max === null) return 'sem-limite';
    if (vagas === 0)  return 'lotada';
    if (vagas <= 3)   return 'critica';
    return 'disponivel';
};

const vagasLabel = (vagas, max) => {
    if (max === null) return '<span class="adminTurmasCard__vagasBadge sem-limite">Sem limite</span>';
    const cls   = vagasClass(vagas, max);
    const texto = vagas === 0 ? 'Lotada' : vagas + ' vaga' + (vagas === 1 ? '' : 's');
    return '<span class="adminTurmasCard__vagasBadge ' + cls + '">' + texto + '</span>';
};

const cardHtml = (t) => {
    const nivel  = NIVEL_LABEL[t.nivel]   || t.nivel;
    const genero = GENERO_LABEL[t.genero] || t.genero;
    const ativos = t.alunos_ativos;
    const max    = t.max_alunos;
    const vagas  = t.vagas;

    const horarios = (t.horarios || []).map(h =>
        '<span>' + DIAS[h.dia_semana] + ' ' + h.hora_inicio.slice(0, 5) + '–' + h.hora_fim.slice(0, 5) + '</span>'
    ).join('');

    const preco = t.promo_valor !== null && t.promo_meses !== null
        ? '<del>R$ ' + fmt(t.valor_mensalidade) + '</del> <strong>R$ ' + fmt(t.promo_valor) + '</strong>'
        : (t.valor_mensalidade !== null ? '<strong>R$ ' + fmt(t.valor_mensalidade) + '</strong>' : '<em>—</em>');

    const ocupacao = max !== null
        ? '<div class="adminTurmasCard__barra"><div class="adminTurmasCard__barraFill ' + vagasClass(vagas, max) + '" style="width:' + Math.min(100, Math.round(ativos / max * 100)) + '%"></div></div>'
        : '';

    const alunosHtml = (t.alunos || []).length > 0
        ? '<ul class="adminTurmasCard__alunos">' +
            (t.alunos).map(a =>
                '<li><button class="adminTurmasCard__alunoBtn" ' +
                    'data-aluno-id="' + a.id + '" ' +
                    'data-turma-id="' + t.id + '" ' +
                    'data-nome="' + $('<span>').text(a.nome).html() + '">' +
                    $('<span>').text(a.nome).html() +
                '</button></li>'
            ).join('') +
          '</ul>'
        : '<p class="adminTurmasCard__alunosEmpty">Nenhum aluno inscrito.</p>';

    const statusCls = t.status === 'ativa' ? 'ativa' : 'inativa';

    const isFila   = vagas === 0 && max !== null;
    const btnLabel = isFila ? '+ Lista de espera' : '+ Adicionar aluno';
    const btnCls   = isFila ? 'btn--fila' : '';

    return '<div class="adminTurmasCard" data-id="' + t.id + '" data-nome="' + $('<span>').text(t.nome).html() + '" data-status="' + t.status + '" data-nivel="' + t.nivel + '">' +
        '<div class="adminTurmasCard__head">' +
            '<div class="adminTurmasCard__headInfo">' +
                '<span class="adminTurmasCard__status ' + statusCls + '">' + (t.status === 'ativa' ? 'Ativa' : 'Inativa') + '</span>' +
                '<span class="adminTurmasCard__nivel">' + nivel + '</span>' +
                '<span class="adminTurmasCard__genero">' + genero + '</span>' +
            '</div>' +
            vagasLabel(vagas, max) +
        '</div>' +
        '<h3 class="adminTurmasCard__nome">' + $('<span>').text(t.nome).html() + '</h3>' +
        '<p class="adminTurmasCard__quadra">' + $('<span>').text(t.quadra_nome).html() + '</p>' +
        (horarios ? '<div class="adminTurmasCard__horarios">' + horarios + '</div>' : '') +
        '<div class="adminTurmasCard__alunosSection">' + alunosHtml + '</div>' +
        '<div class="adminTurmasCard__footer">' +
            '<div class="adminTurmasCard__preco">' + preco + '</div>' +
            '<div class="adminTurmasCard__ocupacao">' +
                '<span>' + ativos + (max !== null ? ' / ' + max : '') + ' aluno' + (ativos !== 1 ? 's' : '') + '</span>' +
                ocupacao +
            '</div>' +
        '</div>' +
        '<div class="adminTurmasCard__actions">' +
            '<a class="adminTurmasCard__edit" href="' + BASE_URL + '/admin/cadastrarquadra?id=' + t.quadra_id + '">Editar turma</a>' +
            '<button class="adminTurmasCard__addAluno btn--addAluno ' + btnCls + '" data-turma-id="' + t.id + '">' + btnLabel + '</button>' +
        '</div>' +
    '</div>';
};

const render = (filtro) => {
    const grid = $('#adminTurmasGrid');
    let lista = todasTurmas;

    if (filtro === 'ativa' || filtro === 'inativa') {
        lista = lista.filter(t => t.status === filtro);
    } else if (filtro !== 'todos') {
        lista = lista.filter(t => t.nivel === filtro);
    }

    if (lista.length === 0) {
        grid.html('<p class="adminTurmas__empty">Nenhuma turma encontrada.</p>');
        return;
    }

    grid.html(lista.map(cardHtml).join(''));
};

// ── Modal adicionar aluno / fila ──────────────────────────────────────────────

const buildModal = () => {
    if ($('#addAlunoModal').length) return;
    $('body').append(`
        <div class="addAlunoModal" id="addAlunoModal" aria-hidden="true">
            <div class="addAlunoModal__overlay" id="addAlunoModalOverlay"></div>
            <div class="addAlunoModal__dialog">
                <div class="addAlunoModal__head">
                    <div>
                        <h3 id="addAlunoModalTitle">Adicionar aluno</h3>
                        <p id="addAlunoModalSub"></p>
                    </div>
                    <button class="addAlunoModal__close" id="addAlunoModalClose" aria-label="Fechar">✕</button>
                </div>
                <div class="addAlunoModal__data" id="addAlunoDataSection">
                    <label>Início da mensalidade</label>
                    <input class="input" type="date" id="addAlunoDataInicio">
                </div>
                <div class="addAlunoModal__search">
                    <input class="input" type="text" id="addAlunoSearch" placeholder="Buscar aluno por nome ou e-mail...">
                </div>
                <div class="addAlunoModal__list" id="addAlunoList">
                    <div class="addAlunoModal__loading">Carregando alunos...</div>
                </div>
            </div>
        </div>
    `);

    const hoje = new Date().toISOString().split('T')[0];
    $('#addAlunoDataInicio').val(hoje);
};

const recarregarTurmas = () => {
    $.get(ADMIN_BASE_URL + '/services/get_turmas_admin.php', (res) => {
        if (res.success) {
            todasTurmas = res.turmas;
            render(filtroAtivo);
        }
    }, 'json');
};

const openModal = (turmaId, turmaNome, modo) => {
    modalTurmaId    = turmaId;
    modalModo       = modo || 'turma';
    todosAlunos     = [];
    modalHasChanges = false;

    const isFila = modalModo === 'fila';
    $('#addAlunoModalTitle').text(isFila ? 'Adicionar à lista de espera' : 'Adicionar aluno');
    $('#addAlunoModalSub').text(turmaNome);
    $('#addAlunoDataSection').toggle(!isFila);
    $('#addAlunoSearch').val('');
    $('#addAlunoList').html('<div class="addAlunoModal__loading">Carregando alunos...</div>');
    $('#addAlunoModal').removeClass('is-hidden').addClass('is-open');
    $('body').addClass('modal-open');

    $.get(ADMIN_BASE_URL + '/services/get_alunos_disponiveis.php', { turma_id: turmaId, modo: modalModo }, (res) => {
        if (!res.success) {
            $('#addAlunoList').html('<p class="addAlunoModal__empty">Erro ao carregar alunos.</p>');
            return;
        }
        todosAlunos = res.alunos;
        renderAlunos(todosAlunos);
    }, 'json').fail(() => {
        $('#addAlunoList').html('<p class="addAlunoModal__empty">Erro ao comunicar com o servidor.</p>');
    });
};

const closeModal = () => {
    $('#addAlunoModal').removeClass('is-open');
    $('body').removeClass('modal-open');
    modalTurmaId = null;
    if (modalHasChanges) {
        modalHasChanges = false;
        recarregarTurmas();
    }
};

const renderAlunos = (lista) => {
    if (lista.length === 0) {
        $('#addAlunoList').html('<p class="addAlunoModal__empty">Nenhum aluno disponível.</p>');
        return;
    }
    const html = lista.map(a =>
        '<div class="addAlunoModal__item" data-id="' + a.id + '">' +
            '<div class="addAlunoModal__itemInfo">' +
                '<strong>' + $('<span>').text(a.nome).html() + '</strong>' +
                '<span>' + $('<span>').text(a.email).html() + '</span>' +
            '</div>' +
            '<button class="btn btn--sm ' + (a.em_turma ? 'btn--emTurma' : 'btn--primary') + ' btn--addItem" data-aluno-id="' + a.id + '">Adicionar</button>' +
        '</div>'
    ).join('');
    $('#addAlunoList').html(html);
};

const adicionarAluno = (alunoId, btn) => {
    const isFila = modalModo === 'fila';
    btn.prop('disabled', true).text('...');

    const url = isFila
        ? ADMIN_BASE_URL + '/services/add_fila_espera.php'
        : ADMIN_BASE_URL + '/services/add_aluno_turma.php';

    const postData = isFila
        ? { turma_id: modalTurmaId, aluno_id: alunoId }
        : { turma_id: modalTurmaId, aluno_id: alunoId, data_inicio: $('#addAlunoDataInicio').val() };

    $.post(url, postData, (res) => {
        if (res.success) {
            $('#addAlunoList .addAlunoModal__item[data-id="' + alunoId + '"]')
                .addClass('is-added')
                .find('button').prop('disabled', true).text('✓');

            modalHasChanges = true;

            if (!isFila && res.aluno) {
                const card  = $('.adminTurmasCard[data-id="' + modalTurmaId + '"]');
                const turma = todasTurmas.find(t => t.id == modalTurmaId);
                if (turma) {
                    turma.alunos_ativos++;
                    if (turma.max_alunos !== null) turma.vagas = Math.max(0, turma.max_alunos - turma.alunos_ativos);
                    turma.alunos = [...(turma.alunos || []), { id: res.aluno.id, nome: res.aluno.nome }]
                        .sort((a, b) => a.nome.localeCompare(b.nome));
                    card.replaceWith(cardHtml(turma));
                }
            }
        } else {
            btn.prop('disabled', false).text('Adicionar');
            alert(res.message || 'Erro ao adicionar aluno.');
        }
    }, 'json').fail((xhr) => {
        btn.prop('disabled', false).text('Adicionar');
        try {
            const res = JSON.parse(xhr.responseText);
            alert(res.message || 'Erro ao adicionar aluno.');
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    });
};

// ── Remove aluno popup ────────────────────────────────────────────────────────

const buildRemoveModal = () => {
    if ($('#removeAlunoModal').length) return;
    $('body').append(`
        <div class="removeAlunoModal" id="removeAlunoModal">
            <div class="removeAlunoModal__overlay" id="removeAlunoOverlay"></div>
            <div class="removeAlunoModal__dialog">
                <p class="removeAlunoModal__label">Remover aluno da turma</p>
                <strong class="removeAlunoModal__nome" id="removeAlunoNome"></strong>
                <div class="removeAlunoModal__actions">
                    <button class="removeAlunoModal__cancel" id="removeAlunoCancel">Cancelar</button>
                    <button class="removeAlunoModal__confirm" id="removeAlunoConfirm">Remover</button>
                </div>
            </div>
        </div>
    `);
};

const openRemovePopup = (alunoId, alunoNome, turmaId) => {
    removeAlunoId = alunoId;
    removeTurmaId = turmaId;
    $('#removeAlunoNome').text(alunoNome);
    $('#removeAlunoModal').addClass('is-open');
    $('body').addClass('modal-open');
};

const closeRemovePopup = () => {
    $('#removeAlunoModal').removeClass('is-open');
    $('body').removeClass('modal-open');
    removeAlunoId = null;
    removeTurmaId = null;
};

const removerAluno = () => {
    if (!confirm('Tem certeza que deseja remover este aluno da turma?')) return;

    const alunoId = removeAlunoId;
    const turmaId = removeTurmaId;
    closeRemovePopup();

    $.post(ADMIN_BASE_URL + '/services/remove_aluno_turma.php', {
        turma_id: turmaId,
        aluno_id: alunoId,
    }, (res) => {
        if (res.success) {
            const turma = todasTurmas.find(t => t.id == turmaId);
            if (turma) {
                turma.alunos        = (turma.alunos || []).filter(a => a.id != alunoId);
                turma.alunos_ativos = Math.max(0, turma.alunos_ativos - 1);
                if (turma.max_alunos !== null) turma.vagas = turma.max_alunos - turma.alunos_ativos;
                $('.adminTurmasCard[data-id="' + turmaId + '"]').replaceWith(cardHtml(turma));
            }
        } else {
            alert(res.message || 'Erro ao remover aluno.');
        }
    }, 'json').fail(() => {
        alert('Erro ao comunicar com o servidor.');
    });
};

// ── Init ──────────────────────────────────────────────────────────────────────

$(document).ready(() => {
    buildModal();
    buildRemoveModal();

    $.get(ADMIN_BASE_URL + '/services/get_turmas_admin.php', (res) => {
        if (!res.success) {
            $('#adminTurmasGrid').html('<p class="adminTurmas__empty">Erro ao carregar turmas.</p>');
            return;
        }
        todasTurmas = res.turmas;
        render(filtroAtivo);
    }, 'json').fail(() => {
        $('#adminTurmasGrid').html('<p class="adminTurmas__empty">Erro ao comunicar com o servidor.</p>');
    });

    $(document).on('click', '.btn--filter', function () {
        $('.btn--filter').removeClass('is-active');
        $(this).addClass('is-active');
        filtroAtivo = $(this).data('filter');
        render(filtroAtivo);
    });

    $(document).on('click', '.btn--addAluno', function () {
        const turmaId   = parseInt($(this).data('turma-id'));
        const card      = $(this).closest('.adminTurmasCard');
        const turmaNome = card.data('nome');
        const isFila    = $(this).hasClass('btn--fila');
        openModal(turmaId, turmaNome, isFila ? 'fila' : 'turma');
    });

    $(document).on('click', '#addAlunoModalClose, #addAlunoModalOverlay', closeModal);

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            if ($('#removeAlunoModal').hasClass('is-open')) closeRemovePopup();
            else closeModal();
        }
    });

    $(document).on('input', '#addAlunoSearch', function () {
        const q = $(this).val().toLowerCase().trim();
        if (!q) { renderAlunos(todosAlunos); return; }
        renderAlunos(todosAlunos.filter(a =>
            a.nome.toLowerCase().includes(q) || a.email.toLowerCase().includes(q)
        ));
    });

    $(document).on('click', '.btn--addItem', function () {
        adicionarAluno(parseInt($(this).data('aluno-id')), $(this));
    });

    $(document).on('click', '.adminTurmasCard__alunoBtn', function () {
        const alunoId   = parseInt($(this).data('aluno-id'));
        const turmaId   = parseInt($(this).data('turma-id'));
        const alunoNome = $(this).data('nome');
        openRemovePopup(alunoId, alunoNome, turmaId);
    });

    $(document).on('click', '#removeAlunoConfirm', removerAluno);
    $(document).on('click', '#removeAlunoCancel, #removeAlunoOverlay', closeRemovePopup);
});
