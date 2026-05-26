
// ─── LISTA ───────────────────────────────────────────────────────────────────

const formatValor = (v) => {
    return 'R$ ' + parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const renderTabela = (registros) => {
    const tbody = $('#quadrasTableBody');
    if (!registros || registros.length === 0) {
        tbody.html('<tr><td colspan="9" class="interessados__empty">Nenhuma quadra encontrada.</td></tr>');
        return;
    }
    const rows = registros.map(r =>
        '<tr>' +
            '<td class="interessados__id">' + r.id + '</td>' +
            '<td><strong>' + $('<span>').text(r.nome).html() + '</strong></td>' +
            '<td>' + $('<span>').text(r.telefone).html() + '</td>' +
            '<td>' + $('<span>').text(r.cidade + ' / ' + r.estado).html() + '</td>' +
            '<td>' + formatValor(r.valor_mensal) + '</td>' +
            '<td class="quadras__centerCell">' + r.total_horarios + '</td>' +
            '<td class="quadras__centerCell">' + r.total_turmas + '</td>' +
            '<td><span class="statusBadge statusBadge--' + r.status + '">' + r.status.toUpperCase() + '</span></td>' +
            '<td><div class="quadras__actions"><a href="' + BASE_URL + '/admin/quadras?id=' + r.id + '" class="btn btn--gray quadras__btnDetalhe">Ver detalhes</a></div></td>' +
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

    html += '<span class="quadras__pageInfo">Página ' + pagina + ' de ' + totalPaginas + '</span>';
    html += '<button class="btn btn--pag" ' + (pagina < totalPaginas ? 'data-pag="' + (pagina + 1) + '"' : 'disabled') + '>Próxima &#8594;</button>';
    wrap.html(html);
};

let paginaAtual = 1;

const carregarQuadras = (pagina, busca) => {
    paginaAtual = pagina;
    const tbody = $('#quadrasTableBody');
    tbody.html('<tr><td colspan="9" class="interessados__loading">Carregando...</td></tr>');

    $.get(ADMIN_BASE_URL + '/services/get_quadras.php', { pagina, busca }, (res) => {
        if (!res.success) {
            tbody.html('<tr><td colspan="9" class="interessados__empty">Erro ao carregar dados.</td></tr>');
            return;
        }
        $('#totalGeral').text(res.totalGeral);
        $('#resultCount').html(
            busca
                ? res.total + ' resultado(s) para "' + $('<span>').text(busca).html() + '"'
                : res.total + ' quadra(s)'
        );
        renderTabela(res.registros);
        renderPaginacao(res.pagina, res.totalPaginas);
    }, 'json').fail(() => {
        tbody.html('<tr><td colspan="9" class="interessados__empty">Erro ao comunicar com o servidor.</td></tr>');
    });
};

// ─── DETALHE ─────────────────────────────────────────────────────────────────

const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const getFileIcon = (mime) => {
    if (mime.startsWith('image/')) return '🖼';
    if (mime === 'application/pdf') return '📄';
    if (mime.includes('word')) return '📝';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return '📊';
    return '📎';
};

const docItemHtml = (d) =>
    '<div class="quadras__docItem" data-id="' + d.id + '">' +
        '<span class="quadras__docIcon">' + getFileIcon(d.tipo_mime) + '</span>' +
        '<div class="quadras__docInfo">' +
            '<a href="' + BASE_URL + '/' + d.caminho + '" target="_blank" class="quadras__docNome">' + $('<span>').text(d.nome_original).html() + '</a>' +
            '<span class="quadras__docSize">' + formatFileSize(parseInt(d.tamanho)) + '</span>' +
        '</div>' +
        '<button type="button" class="btn btn--gray quadras__docDelete" data-id="' + d.id + '">✕</button>' +
    '</div>';

const uploadDocumento = (file) => {
    const progress = $('#uploadProgress');
    progress.text('Enviando "' + file.name + '"...').show();

    const formData = new FormData();
    formData.append('quadra_id', QUADRA_ID);
    formData.append('documento', file);

    $.ajax({
        url: ADMIN_BASE_URL + '/services/upload_documento_quadra.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: (res) => {
            progress.hide();
            if (res.success) {
                const lista = $('#documentosList');
                $('#documentosEmpty').remove();
                lista.append(docItemHtml(res.documento));
            } else {
                alert(res.message || 'Erro ao enviar documento.');
            }
        },
        error: () => {
            progress.hide();
            alert('Erro ao comunicar com o servidor.');
        }
    });
};

const deleteDocumento = (id) => {
    $.post(ADMIN_BASE_URL + '/services/delete_documento_quadra.php', { id }, (res) => {
        if (res.success) {
            $('.quadras__docItem[data-id="' + id + '"]').remove();
            if ($('#documentosList .quadras__docItem').length === 0) {
                $('#documentosList').html('<p class="quadras__empty" id="documentosEmpty">Nenhum documento anexado.</p>');
            }
        } else {
            alert(res.message || 'Erro ao excluir documento.');
        }
    }, 'json').fail(() => alert('Erro ao comunicar com o servidor.'));
};

const deletarQuadra = () => {
    $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');
    $.post(ADMIN_BASE_URL + '/services/delete_quadra.php', { id: QUADRA_ID }, (res) => {
        $('.overlayForm').remove();
        if (res.success) {
            window.location.href = BASE_URL + '/admin/quadras';
        } else {
            alert(res.message || 'Erro ao excluir quadra.');
        }
    }, 'json').fail(() => {
        $('.overlayForm').remove();
        alert('Erro ao comunicar com o servidor.');
    });
};

// ─── INIT ─────────────────────────────────────────────────────────────────────

$(document).ready(() => {

    if (QUADRA_VIEW === 'lista') {
        carregarQuadras(1, '');

        let debounceTimer;
        $('#buscaQuadras').on('input', function () {
            clearTimeout(debounceTimer);
            const busca = $(this).val().trim();
            debounceTimer = setTimeout(() => carregarQuadras(1, busca), 400);
        });

        $(document).on('click', '.btn--pag', function () {
            carregarQuadras(parseInt($(this).data('pag')), $('#buscaQuadras').val().trim());
        });

        return;
    }

    // ── Modo detalhe ─────────────────────────────────────────────

    $('#uploadDocumento').on('change', function () {
        const file = this.files[0];
        if (file) {
            uploadDocumento(file);
            this.value = '';
        }
    });

    $(document).on('click', '.quadras__docDelete', function () {
        const id = parseInt($(this).data('id'));
        if (confirm('Excluir este documento?')) deleteDocumento(id);
    });

    $('#btnDeletarQuadra').on('click', () => {
        $('#confirmDeleteModal').addClass('confirmModal--open');
    });

    $('#btnCancelarDelete').on('click', () => {
        $('#confirmDeleteModal').removeClass('confirmModal--open');
    });

    $('#confirmDeleteModal').on('click', function (e) {
        if (e.target === this) $(this).removeClass('confirmModal--open');
    });

    $('#btnConfirmarDelete').on('click', deletarQuadra);
});
