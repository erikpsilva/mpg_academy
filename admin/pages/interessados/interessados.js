
const formatCelular = (cel) => {
    if (!cel || cel.length !== 11) return cel || '-';
    return '(' + cel.slice(0, 2) + ') ' + cel.slice(2, 7) + '-' + cel.slice(7);
};

const formatData = (val) => {
    if (!val) return '-';
    const d = new Date(val);
    if (isNaN(d.getTime())) return val;
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
};

const renderTabela = (registros) => {
    const tbody = $('#interessadosTableBody');
    if (!registros || registros.length === 0) {
        tbody.html('<tr><td colspan="5" class="interessados__empty">Nenhum registro encontrado.</td></tr>');
        return;
    }
    const rows = registros.map((r, i) =>
        '<tr>' +
            '<td class="interessados__id">' + r.id + '</td>' +
            '<td>' + $('<span>').text(r.nome_completo).html() + '</td>' +
            '<td>' + $('<span>').text(r.email).html() + '</td>' +
            '<td>' + formatCelular(r.celular) + '</td>' +
            '<td>' + formatData(r.created_at) + '</td>' +
        '</tr>'
    ).join('');
    tbody.html(rows);
};

const renderPaginacao = (pagina, totalPaginas) => {
    const wrap = $('#paginacaoControles');
    if (totalPaginas <= 1) { wrap.html(''); return; }

    let html = '';

    if (pagina > 1) {
        html += '<button class="btn btn--pag" data-pag="' + (pagina - 1) + '">&#8592; Anterior</button>';
    }

    const inicio = Math.max(1, pagina - 2);
    const fim    = Math.min(totalPaginas, pagina + 2);

    for (let p = inicio; p <= fim; p++) {
        const ativo = p === pagina ? ' btn--pag--ativo' : '';
        html += '<button class="btn btn--pag' + ativo + '" data-pag="' + p + '">' + p + '</button>';
    }

    if (pagina < totalPaginas) {
        html += '<button class="btn btn--pag" data-pag="' + (pagina + 1) + '">Próxima &#8594;</button>';
    }

    wrap.html(html);
};

let paginaAtual = 1;

const carregarInteressados = (pagina, busca) => {
    paginaAtual = pagina;
    const tbody = $('#interessadosTableBody');
    tbody.html('<tr><td colspan="5" class="interessados__loading">Carregando...</td></tr>');

    $.get(ADMIN_BASE_URL + '/services/get_interessados.php', { pagina: pagina, busca: busca }, function (res) {
        if (!res.success) {
            tbody.html('<tr><td colspan="5" class="interessados__empty">Erro ao carregar dados.</td></tr>');
            return;
        }

        $('#totalGeral').text(res.totalGeral);

        const label = busca
            ? res.total + ' resultado(s) para "' + $('<span>').text(busca).html() + '"'
            : res.total + ' registro(s)';
        $('#resultCount').html(label);

        renderTabela(res.registros);
        renderPaginacao(res.pagina, res.totalPaginas);
    }, 'json').fail(function () {
        tbody.html('<tr><td colspan="5" class="interessados__empty">Erro ao comunicar com o servidor.</td></tr>');
    });
};

let debounceTimer;

$(document).ready(function () {
    carregarInteressados(1, '');

    $('#buscaInteressados').on('input', function () {
        clearTimeout(debounceTimer);
        const busca = $(this).val().trim();
        debounceTimer = setTimeout(function () {
            carregarInteressados(1, busca);
        }, 400);
    });

    $(document).on('click', '.btn--pag', function () {
        const pag   = parseInt($(this).data('pag'));
        const busca = $('#buscaInteressados').val().trim();
        carregarInteressados(pag, busca);
    });
});
