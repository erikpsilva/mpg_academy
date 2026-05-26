
const NIVEL_LABEL = { iniciante: 'Iniciante', intermediario: 'Intermediário', avancado: 'Avançado' };

const fmtData = (str) => {
    const d = new Date(str);
    return d.toLocaleDateString('pt-BR');
};

const renderFila = (turmas) => {
    const body = $('#adminFilaBody');

    if (!turmas.length) {
        body.html('<p class="adminFila__empty">Nenhum aluno em fila de espera no momento.</p>');
        return;
    }

    const html = turmas.map(turma => {
        const vagas     = turma.vagas;
        const temVaga   = vagas !== null && vagas > 0;
        const vagaLabel = vagas === null ? 'Sem limite' : vagas + ' vaga' + (vagas === 1 ? '' : 's');
        const vagaCls   = vagas === null ? 'sem-limite' : vagas === 0 ? 'lotada' : 'disponivel';

        const itens = turma.fila.map((f, idx) =>
            '<div class="adminFilaItem" data-fila-id="' + f.id + '">' +
                '<span class="adminFilaItem__pos">' + (idx + 1) + 'º</span>' +
                '<div class="adminFilaItem__info">' +
                    '<strong>' + $('<span>').text(f.nome).html() + '</strong>' +
                    '<span>' + $('<span>').text(f.email).html() + '</span>' +
                '</div>' +
                '<span class="adminFilaItem__data">' + fmtData(f.criado_em) + '</span>' +
                '<button class="btn btn--sm btn--promover ' + (temVaga ? '' : 'is-disabled') + '" ' +
                    'data-fila-id="' + f.id + '" ' +
                    (temVaga ? '' : 'disabled title="Sem vagas disponíveis"') + '>' +
                    'Promover' +
                '</button>' +
            '</div>'
        ).join('');

        return '<div class="adminFilaBloco" data-turma-id="' + turma.turma_id + '">' +
            '<div class="adminFilaBloco__head">' +
                '<div class="adminFilaBloco__info">' +
                    '<h3>' + $('<span>').text(turma.turma_nome).html() + '</h3>' +
                    '<p>' + $('<span>').text(turma.quadra_nome).html() +
                        ' &nbsp;·&nbsp; ' + (NIVEL_LABEL[turma.nivel] || turma.nivel) + '</p>' +
                '</div>' +
                '<div class="adminFilaBloco__vagas">' +
                    '<span class="adminFilaVagaBadge ' + vagaCls + '">' + vagaLabel + '</span>' +
                    '<span class="adminFilaBloco__count">' + turma.fila.length + ' na fila</span>' +
                '</div>' +
            '</div>' +
            '<div class="adminFilaBloco__lista">' + itens + '</div>' +
        '</div>';
    }).join('');

    body.html(html);
};

const carregarFila = () => {
    $('#adminFilaBody').html('<div class="adminFila__loading">Carregando fila de espera...</div>');
    $.get(ADMIN_BASE_URL + '/services/get_fila_espera.php', (res) => {
        if (!res.success) {
            $('#adminFilaBody').html('<p class="adminFila__empty">Erro ao carregar dados.</p>');
            return;
        }
        renderFila(res.turmas);
    }, 'json').fail(() => {
        $('#adminFilaBody').html('<p class="adminFila__empty">Erro ao comunicar com o servidor.</p>');
    });
};

const promover = (filaId, btn) => {
    const hoje = new Date().toISOString().split('T')[0];
    btn.prop('disabled', true).text('...');

    $.post(ADMIN_BASE_URL + '/services/promover_fila_espera.php', {
        fila_id:     filaId,
        data_inicio: hoje,
    }, (res) => {
        if (res.success) {
            carregarFila();
        } else {
            btn.prop('disabled', false).text('Promover');
            alert(res.message || 'Erro ao promover aluno.');
        }
    }, 'json').fail((xhr) => {
        btn.prop('disabled', false).text('Promover');
        try {
            const r = JSON.parse(xhr.responseText);
            alert(r.message || 'Erro ao promover aluno.');
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    });
};

$(document).ready(() => {
    carregarFila();

    $(document).on('click', '.btn--promover:not(.is-disabled)', function () {
        const filaId = parseInt($(this).data('fila-id'));
        if (confirm('Deseja promover este aluno para a turma?')) {
            promover(filaId, $(this));
        }
    });
});
