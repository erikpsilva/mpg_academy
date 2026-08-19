$(document).ready(() => {

    let jogadores = [];
    let jogadorSelecionado = null;
    let jogadorIdParaExcluir = 0;

    const estrelasHtml = (id, nivel) => {
        let html = '<div class="jogadorEstrelas" data-id="' + id + '">';
        for (let i = 1; i <= 5; i++) {
            html += '<span class="jogadorEstrelas__star' + (i <= nivel ? ' is-filled' : '') + '" data-valor="' + i + '">★</span>';
        }
        html += '</div>';
        return html;
    };

    const thumbHtml = (foto, nome) => {
        const img = foto
            ? '<img src="' + BASE_URL + '/' + foto + '" alt="' + $('<span>').text(nome).html() + '" data-lightbox>'
            : '<i class="icon-user" aria-hidden="true"></i>';
        return '<span class="jogadores__thumb">' + img + '</span>';
    };

    const renderTabela = () => {
        const body = $('#jogadoresTableBody');
        if (jogadores.length === 0) {
            body.html('<tr><td colspan="6" class="interessados__loading">Nenhum jogador cadastrado.</td></tr>');
            return;
        }
        body.html(jogadores.map((j, i) =>
            '<tr data-id="' + j.id + '">' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + thumbHtml(j.foto, j.nome) + '</td>' +
                '<td>' + $('<span>').text(j.nome).html() + '</td>' +
                '<td>' + (j.altura_cm ? j.altura_cm + ' cm' : '—') + '</td>' +
                '<td>' + estrelasHtml(j.id, j.nivel || 0) + '</td>' +
                '<td><button type="button" class="btn btn--sm btn--outline btnEditarJogador" data-id="' + j.id + '">Editar</button></td>' +
            '</tr>'
        ).join(''));
    };

    const carregarJogadores = () => {
        $.get(ADMIN_BASE_URL + '/services/get_jogadores_batebola.php', (res) => {
            if (!res.success) {
                $('#jogadoresTableBody').html('<tr><td colspan="6" class="interessados__empty">Erro ao carregar dados.</td></tr>');
                return;
            }
            jogadores = res.jogadores;
            $('#totalGeral').text(jogadores.length);
            renderTabela();
        }, 'json').fail(() => {
            $('#jogadoresTableBody').html('<tr><td colspan="6" class="interessados__empty">Erro ao comunicar com o servidor.</td></tr>');
        });
    };

    carregarJogadores();

    // ── Nível (estrelas) ────────────────────────────────────────────────────────
    $(document).on('click', '.jogadorEstrelas__star', function () {
        const wrap  = $(this).closest('.jogadorEstrelas');
        const id    = wrap.data('id');
        const nivel = $(this).data('valor');

        wrap.find('.jogadorEstrelas__star').each(function () {
            $(this).toggleClass('is-filled', $(this).data('valor') <= nivel);
        });

        $.post(ADMIN_BASE_URL + '/services/save_nivel_jogador.php', { id, nivel }, (res) => {
            if (res.success) {
                const jog = jogadores.find(j => String(j.id) === String(id));
                if (jog) jog.nivel = nivel;
            } else {
                alert(res.message || 'Erro ao salvar nível.');
                carregarJogadores();
            }
        }, 'json').fail(() => {
            alert('Erro ao comunicar com o servidor.');
            carregarJogadores();
        });
    });

    // ── Editar jogador (dados + senha + excluir) ──────────────────────────────────
    $(document).on('click', '.btnEditarJogador', function () {
        const id = $(this).data('id');
        jogadorSelecionado = jogadores.find(j => String(j.id) === String(id));
        if (!jogadorSelecionado) return;

        $('#editarNome').text(jogadorSelecionado.nome);
        $('#editarEmail').text(jogadorSelecionado.email);
        $('#editarCelular').text(jogadorSelecionado.celular);
        $('#editarAltura').text(jogadorSelecionado.altura_cm ? jogadorSelecionado.altura_cm + ' cm' : '—');
        $('#editarFotoPreview').html(
            jogadorSelecionado.foto
                ? '<img src="' + BASE_URL + '/' + jogadorSelecionado.foto + '" alt="' + jogadorSelecionado.nome + '" data-lightbox>'
                : '<i class="icon-user" aria-hidden="true"></i>'
        );
        $('#editarSenhaNova').val('');
        $('#editarSenhaMsg').hide();
        $('#editarModal').addClass('confirmModal--open');
    });

    $('#editarFechar').on('click', () => $('#editarModal').removeClass('confirmModal--open'));
    $('#editarModal').on('click', function (e) {
        if ($(e.target).is('#editarModal')) $(this).removeClass('confirmModal--open');
    });

    $('#editarSalvarSenha').on('click', function () {
        const btn   = $(this);
        const senha = $('#editarSenhaNova').val().trim();
        const msg   = $('#editarSenhaMsg');

        if (senha.length < 6) {
            msg.text('A senha precisa ter pelo menos 6 caracteres.').css('color', '#cf7e7e').show();
            return;
        }

        btn.prop('disabled', true).text('Salvando...');
        $.post(ADMIN_BASE_URL + '/services/reset_senha_jogador.php', { id: jogadorSelecionado.id, senha }, (res) => {
            if (res.success) {
                msg.text('Senha alterada com sucesso!').css('color', '#7ecf7e').show();
                $('#editarSenhaNova').val('');
            } else {
                msg.text(res.message || 'Erro ao alterar senha.').css('color', '#cf7e7e').show();
            }
            btn.prop('disabled', false).text('Salvar nova senha');
        }, 'json').fail(() => {
            msg.text('Erro ao comunicar com o servidor.').css('color', '#cf7e7e').show();
            btn.prop('disabled', false).text('Salvar nova senha');
        });
    });

    // ── Excluir jogador ────────────────────────────────────────────────────────
    $('#editarExcluirBtn').on('click', () => {
        if (!jogadorSelecionado) return;
        jogadorIdParaExcluir = jogadorSelecionado.id;
        $('#confirmNome').text(jogadorSelecionado.nome);
        $('#confirmModal').addClass('confirmModal--open');
    });

    $('#confirmCancelar').on('click', () => {
        $('#confirmModal').removeClass('confirmModal--open');
        jogadorIdParaExcluir = 0;
    });
    $('#confirmModal').on('click', function (e) {
        if ($(e.target).is('#confirmModal')) {
            $(this).removeClass('confirmModal--open');
            jogadorIdParaExcluir = 0;
        }
    });

    $('#confirmExcluir').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text('Excluindo...');
        $.post(ADMIN_BASE_URL + '/services/delete_jogador.php', { id: jogadorIdParaExcluir }, (res) => {
            if (res.success) {
                $('#confirmModal').removeClass('confirmModal--open');
                $('#editarModal').removeClass('confirmModal--open');
                carregarJogadores();
            } else {
                alert(res.message || 'Erro ao excluir.');
            }
            btn.prop('disabled', false).text('Sim, excluir');
        }, 'json').fail(() => {
            alert('Erro ao comunicar com o servidor.');
            btn.prop('disabled', false).text('Sim, excluir');
        });
    });
});
