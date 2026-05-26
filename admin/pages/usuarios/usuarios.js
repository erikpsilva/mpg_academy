
const nivelBadge = (nivel) => {
    const map = {
        admin  : 'usuarios__nivelBadge--admin',
        editor : 'usuarios__nivelBadge--editor',
        leitor : 'usuarios__nivelBadge--leitor',
    };
    return '<span class="usuarios__nivelBadge ' + (map[nivel] || '') + '">' + nivel.toUpperCase() + '</span>';
};

const formatData = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return isNaN(d.getTime()) ? val : d.toLocaleDateString('pt-BR');
};

const maskCpf = (cpf) => {
    if (!cpf || cpf.length !== 11) return cpf || '—';
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
};

const renderTabela = (usuarios) => {
    const tbody = $('#usuariosTableBody');
    if (!usuarios || usuarios.length === 0) {
        tbody.html('<tr><td colspan="7" class="interessados__empty">Nenhum usuário encontrado.</td></tr>');
        return;
    }
    const rows = usuarios.map(u => {
        const isSelf = parseInt(u.id) === USUARIO_ATUAL;
        const selfTag = isSelf ? ' <span class="usuarios__voce">você</span>' : '';
        return '<tr' + (isSelf ? ' class="usuarios__rowSelf"' : '') + '>' +
            '<td class="interessados__id">' + u.id + '</td>' +
            '<td><strong>' + $('<span>').text(u.nome_completo).html() + '</strong>' + selfTag + '</td>' +
            '<td>' + $('<span>').text(u.email).html() + '</td>' +
            '<td class="usuarios__cpf">' + maskCpf(u.cpf) + '</td>' +
            '<td>' + nivelBadge(u.nivel_acesso) + '</td>' +
            '<td>' + formatData(u.created_at) + '</td>' +
            '<td>' +
                '<div class="usuarios__acoes">' +
                    '<button class="btn btn--sm btn--gray usuarios__btnEditar" ' +
                        'data-id="' + u.id + '" ' +
                        'data-nome="' + $('<span>').text(u.nome_completo).html() + '" ' +
                        'data-email="' + $('<span>').text(u.email).html() + '" ' +
                        'data-nivel="' + u.nivel_acesso + '">' +
                        'Editar' +
                    '</button>' +
                    (!isSelf
                        ? '<button class="btn btn--sm btn--error usuarios__btnDeletar" ' +
                              'data-id="' + u.id + '" ' +
                              'data-nome="' + $('<span>').text(u.nome_completo).html() + '">' +
                              'Excluir' +
                          '</button>'
                        : '') +
                '</div>' +
            '</td>' +
        '</tr>';
    }).join('');
    tbody.html(rows);
};

const carregarUsuarios = () => {
    $('#usuariosTableBody').html('<tr><td colspan="7" class="interessados__loading">Carregando...</td></tr>');
    $.get(ADMIN_BASE_URL + '/services/get_usuarios.php', (res) => {
        if (!res.success) {
            $('#usuariosTableBody').html('<tr><td colspan="7" class="interessados__empty">Erro ao carregar dados.</td></tr>');
            return;
        }
        renderTabela(res.usuarios);
    }, 'json').fail(() => {
        $('#usuariosTableBody').html('<tr><td colspan="7" class="interessados__empty">Erro ao comunicar com o servidor.</td></tr>');
    });
};

// ── Editar ────────────────────────────────────────────────────────────────────

const abrirEdit = (id, nome, email, nivel) => {
    $('#editId').val(id);
    $('#editNome').val(nome);
    $('#editEmail').val(email);
    $('#editNivel').val(nivel);
    $('#editSenha, #editSenhaConfirm').val('');
    $('#editErro').text('').hide();
    $('#editModal').addClass('confirmModal--open');
};

$(document).on('click', '.usuarios__btnEditar', function () {
    abrirEdit(
        $(this).data('id'),
        $(this).data('nome'),
        $(this).data('email'),
        $(this).data('nivel')
    );
});

$('#editCancelar').on('click', () => $('#editModal').removeClass('confirmModal--open'));

$('#editModal').on('click', function (e) {
    if ($(e.target).is('#editModal')) $(this).removeClass('confirmModal--open');
});

$('#editSalvar').on('click', function () {
    const senha  = $('#editSenha').val();
    const senhaC = $('#editSenhaConfirm').val();

    if (senha !== '' && (senha.length < 6 || senha.length > 20)) {
        $('#editErro').text('A senha deve ter entre 6 e 20 caracteres.').show();
        return;
    }
    if (senha !== senhaC) {
        $('#editErro').text('As senhas não coincidem.').show();
        return;
    }

    const btn = $(this).prop('disabled', true).text('Salvando...');
    $('#editErro').hide();

    $.post(ADMIN_BASE_URL + '/services/update_usuario.php', {
        id           : $('#editId').val(),
        nome_completo: $('#editNome').val().trim(),
        email        : $('#editEmail').val().trim(),
        nivel_acesso : $('#editNivel').val(),
        nova_senha   : senha,
    }, (res) => {
        if (res.success) {
            $('#editModal').removeClass('confirmModal--open');
            carregarUsuarios();
        } else {
            $('#editErro').text(res.message || 'Erro ao salvar.').show();
        }
        btn.prop('disabled', false).text('Salvar');
    }, 'json').fail(() => {
        $('#editErro').text('Erro ao comunicar com o servidor.').show();
        btn.prop('disabled', false).text('Salvar');
    });
});

// ── Deletar ───────────────────────────────────────────────────────────────────

let idParaDeletar = 0;

$(document).on('click', '.usuarios__btnDeletar', function () {
    idParaDeletar = parseInt($(this).data('id'));
    $('#deleteNome').text($(this).data('nome'));
    $('#deleteModal').addClass('confirmModal--open');
});

$('#deleteCancelar').on('click', () => {
    $('#deleteModal').removeClass('confirmModal--open');
    idParaDeletar = 0;
});

$('#deleteModal').on('click', function (e) {
    if ($(e.target).is('#deleteModal')) {
        $(this).removeClass('confirmModal--open');
        idParaDeletar = 0;
    }
});

$('#deleteConfirmar').on('click', function () {
    const btn = $(this).prop('disabled', true).text('Excluindo...');
    $.post(ADMIN_BASE_URL + '/services/delete_usuario.php', { id: idParaDeletar }, (res) => {
        if (res.success) {
            $('#deleteModal').removeClass('confirmModal--open');
            carregarUsuarios();
        } else {
            alert(res.message || 'Erro ao excluir.');
        }
        btn.prop('disabled', false).text('Sim, excluir');
        idParaDeletar = 0;
    }, 'json').fail(() => {
        alert('Erro ao comunicar com o servidor.');
        btn.prop('disabled', false).text('Sim, excluir');
        idParaDeletar = 0;
    });
});

// ── Init ──────────────────────────────────────────────────────────────────────

$(document).ready(() => {
    carregarUsuarios();
});
