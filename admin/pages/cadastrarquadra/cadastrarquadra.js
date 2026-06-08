
const DIAS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

let horarioCount = 0;
let turmaCount   = 0;

// ─── VIA CEP ─────────────────────────────────────────────────────────────────

const buscaCep = () => {
    const cep = $('#cep').val().replace(/\D/g, '');
    if (cep.length !== 8) return;

    $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');

    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(r => r.json())
        .then(data => {
            if (data.erro) {
                alert('CEP não encontrado.');
                return;
            }
            $('#rua').val(data.logradouro || '');
            $('#bairro').val(data.bairro || '');
            $('#cidade').val(data.localidade || '');
            $('#estado').val(data.uf || '');
            $('#numero').focus();
        })
        .catch(() => alert('Erro ao consultar o ViaCEP. Verifique sua conexão.'))
        .finally(() => $('.overlayForm').remove());
};

// ─── HORÁRIOS ────────────────────────────────────────────────────────────────

const getHorariosAtivos = () => {
    const lista = [];
    $('#horariosLista .horario-row').each(function () {
        lista.push({
            _rowId:      parseInt($(this).data('id')),
            dia_semana:  parseInt($(this).find('.horario-dia').val()),
            hora_inicio: $(this).find('.horario-inicio').val(),
            hora_fim:    $(this).find('.horario-fim').val(),
        });
    });
    return lista;
};

const diaLabel = (h) => DIAS[h.dia_semana] + ' &nbsp; ' + h.hora_inicio + '–' + h.hora_fim;

const updateTurmaCheckboxes = () => {
    const horarios = getHorariosAtivos();

    $('.turma-item').each(function () {
        const checked = new Set();
        $(this).find('.turma-horarios-list input:checked').each(function () {
            checked.add(parseInt($(this).val()));
        });

        let html = '';
        if (horarios.length === 0) {
            html = '<span class="cadastrarQuadra__turmaEmpty">Adicione horários acima para selecionar.</span>';
        } else {
            horarios.forEach(h => {
                const isChecked = checked.has(h._rowId) ? 'checked' : '';
                html += '<label class="cadastrarQuadra__checkLabel">' +
                    '<input type="checkbox" value="' + h._rowId + '" ' + isChecked + '>' +
                    '<span>' + diaLabel(h) + '</span>' +
                    '</label>';
            });
        }
        $(this).find('.turma-horarios-list').html(html);
    });
};

const addHorario = () => {
    horarioCount++;
    const id = horarioCount;

    const diasOptions = DIAS.map((d, i) => '<option value="' + i + '">' + d + '</option>').join('');

    const row = '<div class="horario-row" data-id="' + id + '">' +
        '<div class="horario-row__inner">' +
            '<select class="input horario-dia">' + diasOptions + '</select>' +
            '<div class="horario-row__times">' +
                '<input class="input horario-inicio" type="time" value="19:00">' +
                '<span class="horario-row__sep">às</span>' +
                '<input class="input horario-fim" type="time" value="21:00">' +
            '</div>' +
            '<button type="button" class="btn btn--gray horario-remover" data-id="' + id + '">✕</button>' +
        '</div>' +
    '</div>';

    $('#horariosLista').append(row);
    updateTurmaCheckboxes();
    return id;
};

const removeHorario = (id) => {
    $('.horario-row[data-id="' + id + '"]').remove();
    updateTurmaCheckboxes();
};

// ─── TURMAS ──────────────────────────────────────────────────────────────────

const addTurma = (dbId) => {
    turmaCount++;
    const id = turmaCount;
    const dbAttr = dbId ? ' data-db-id="' + parseInt(dbId) + '"' : '';

    const horarios = getHorariosAtivos();
    let checkboxesHtml = '';
    if (horarios.length === 0) {
        checkboxesHtml = '<span class="cadastrarQuadra__turmaEmpty">Adicione horários acima para selecionar.</span>';
    } else {
        horarios.forEach(h => {
            checkboxesHtml += '<label class="cadastrarQuadra__checkLabel">' +
                '<input type="checkbox" value="' + h._rowId + '">' +
                '<span>' + diaLabel(h) + '</span>' +
                '</label>';
        });
    }

    const html = '<div class="turma-item" data-id="' + id + '"' + dbAttr + '>' +
        '<div class="turma-item__head">' +
            '<div class="formGroup__item turma-item__nomeWrap">' +
                '<label>Nome da turma *</label>' +
                '<input class="input turma-nome" type="text" placeholder="Ex: Turma Qua/Sex – Iniciante">' +
            '</div>' +
            '<div class="formGroup__item turma-item__valorWrap">' +
                '<label>Mensalidade (R$)</label>' +
                '<input class="input turma-valor" type="number" min="0" step="0.01" placeholder="Ex: 250">' +
            '</div>' +
            '<div class="formGroup__item turma-item__maxWrap">' +
                '<label>Máx. alunos</label>' +
                '<input class="input turma-max-alunos" type="number" min="1" step="1" placeholder="Ex: 16">' +
            '</div>' +
            '<button type="button" class="btn btn--gray turma-remover" data-id="' + id + '">✕ Remover</button>' +
        '</div>' +
        '<div class="turma-item__meta">' +
            '<div class="formGroup__item">' +
                '<label>Gênero</label>' +
                '<select class="input turma-genero">' +
                    '<option value="misto">Misto</option>' +
                    '<option value="masculino">Masculino</option>' +
                    '<option value="feminino">Feminino</option>' +
                '</select>' +
            '</div>' +
            '<div class="formGroup__item">' +
                '<label>Nível</label>' +
                '<select class="input turma-nivel">' +
                    '<option value="iniciante">Iniciante</option>' +
                    '<option value="intermediario">Intermediário</option>' +
                    '<option value="avancado">Avançado</option>' +
                '</select>' +
            '</div>' +
        '</div>' +
        '<div class="turma-item__promoToggle">' +
            '<label class="cadastrarQuadra__checkLabel">' +
                '<input type="checkbox" class="turma-promo-toggle">' +
                '<span>Ativar promoção nesta turma</span>' +
            '</label>' +
        '</div>' +
        '<div class="turma-item__promoFields">' +
            '<div class="turma-item__promoRow">' +
                '<div class="formGroup__item">' +
                    '<label>Valor promocional (R$)</label>' +
                    '<input class="input turma-promo-valor" type="number" min="0" step="0.01" placeholder="Ex: 200">' +
                '</div>' +
                '<div class="formGroup__item">' +
                    '<label>Duração (meses)</label>' +
                    '<input class="input turma-promo-meses" type="number" min="1" step="1" placeholder="Ex: 3">' +
                '</div>' +
            '</div>' +
            '<p class="turma-item__promoHint">Alunos que entrarem nesta turma receberão o desconto automaticamente. Pode ser ajustado individualmente no perfil do aluno.</p>' +
        '</div>' +
        '<div class="turma-item__horarios">' +
            '<span class="turma-item__label">Horários desta turma:</span>' +
            '<div class="turma-horarios-list">' + checkboxesHtml + '</div>' +
        '</div>' +
    '</div>';

    $('#turmasLista').append(html);
    return id;
};

const removeTurma = (id) => {
    $('.turma-item[data-id="' + id + '"]').remove();
};

// ─── PRÉ-PREENCHIMENTO (modo edição) ─────────────────────────────────────────

const prefillEdit = (editData) => {
    $('#nome').val(editData.nome || '');
    $('#email').val(editData.email || '');
    $('#instagram').val(editData.instagram || '');
    $('#rua').val(editData.rua || '');
    $('#numero').val(editData.numero || '');
    $('#bairro').val(editData.bairro || '');
    $('#complemento').val(editData.complemento || '');
    $('#cidade').val(editData.cidade || '');
    $('#estado').val(editData.estado || '');
    $('#valorMensal').val(editData.valor_mensal || '');
    $('#diaPagamento').val(editData.dia_pagamento || 10);
    $('#dataInicioContrato').val(editData.data_inicio_contrato || '');

    // Apply mask to phone before setting value
    $('#telefone').val(editData.telefone || '').trigger('input');

    // CEP: format with mask (mask plugin needs 99999-999 pattern)
    const rawCep = (editData.cep || '').replace(/\D/g, '');
    const fmtCep = rawCep.length === 8 ? rawCep.substring(0, 5) + '-' + rawCep.substring(5) : rawCep;
    $('#cep').val(fmtCep);

    // Clear the default empty horario added on page load
    $('#horariosLista').empty();
    horarioCount = 0;

    // Add each horario and track DB id → row id mapping
    const dbIdToRowId = {};
    (editData.horarios || []).forEach(h => {
        const rowId = addHorario();
        dbIdToRowId[h.id] = rowId;

        const row = $('.horario-row[data-id="' + rowId + '"]');
        row.find('.horario-dia').val(parseInt(h.dia_semana));
        row.find('.horario-inicio').val((h.hora_inicio || '').substring(0, 5));
        row.find('.horario-fim').val((h.hora_fim || '').substring(0, 5));
    });

    updateTurmaCheckboxes();

    // Add each turma and check the right horarios
    (editData.turmas || []).forEach(t => {
        const turmaRowId = addTurma(t.id); // passa o db id para rastreamento no update
        const turmaEl = $('.turma-item[data-id="' + turmaRowId + '"]');
        turmaEl.find('.turma-nome').val(t.nome || '');
        if (t.valor_mensalidade != null) turmaEl.find('.turma-valor').val(t.valor_mensalidade);
        turmaEl.find('.turma-genero').val(t.genero || 'misto');
        turmaEl.find('.turma-nivel').val(t.nivel  || 'iniciante');
        if (t.promo_valor != null && t.promo_meses != null) {
            turmaEl.find('.turma-promo-toggle').prop('checked', true);
            turmaEl.find('.turma-item__promoFields').show();
            turmaEl.find('.turma-promo-valor').val(t.promo_valor);
            turmaEl.find('.turma-promo-meses').val(t.promo_meses);
        }
        if (t.max_alunos != null) turmaEl.find('.turma-max-alunos').val(t.max_alunos);

        (t.horario_ids || []).forEach(dbId => {
            const rowId = dbIdToRowId[parseInt(dbId)];
            if (rowId !== undefined) {
                turmaEl.find('.turma-horarios-list input[value="' + rowId + '"]').prop('checked', true);
            }
        });
    });
};

// ─── COLETA E VALIDAÇÃO ───────────────────────────────────────────────────────

const setFieldState = (selector, isValid) => {
    const parent = $(selector).closest('.formGroup__item');
    isValid ? parent.removeClass('error') : parent.addClass('error');
    return isValid;
};

const validarForm = () => {
    const checks = [
        setFieldState('#nome',          $('#nome').val().trim().length >= 2),
        setFieldState('#telefone',      $('#telefone').val().trim().length >= 10),
        setFieldState('#cep',           $('#cep').val().replace(/\D/g,'').length === 8),
        setFieldState('#rua',           $('#rua').val().trim() !== ''),
        setFieldState('#numero',        $('#numero').val().trim() !== ''),
        setFieldState('#bairro',        $('#bairro').val().trim() !== ''),
        setFieldState('#cidade',        $('#cidade').val().trim() !== ''),
        setFieldState('#estado',        $('#estado').val().trim().length === 2),
        setFieldState('#diaPagamento',  parseInt($('#diaPagamento').val()) >= 1 && parseInt($('#diaPagamento').val()) <= 31),
    ];
    return checks.every(Boolean);
};

const coletarDados = () => {
    const horarios = [];
    const rowIdToIdx = {};

    $('#horariosLista .horario-row').each(function () {
        const rowId = parseInt($(this).data('id'));
        rowIdToIdx[rowId] = horarios.length;
        horarios.push({
            dia_semana:  parseInt($(this).find('.horario-dia').val()),
            hora_inicio: $(this).find('.horario-inicio').val(),
            hora_fim:    $(this).find('.horario-fim').val(),
        });
    });

    const turmas = [];
    $('.turma-item').each(function () {
        const nome = $(this).find('.turma-nome').val().trim();
        if (!nome) return;

        const indices = [];
        $(this).find('.turma-horarios-list input:checked').each(function () {
            const idx = rowIdToIdx[parseInt($(this).val())];
            if (idx !== undefined) indices.push(idx);
        });

        const valorRaw = $(this).find('.turma-valor').val();
        const valor = valorRaw !== '' ? parseFloat(valorRaw) : null;

        const genero = $(this).find('.turma-genero').val() || 'misto';
        const nivel  = $(this).find('.turma-nivel').val()  || 'iniciante';

        const temPromo   = $(this).find('.turma-promo-toggle').is(':checked');
        const promoValor = temPromo ? (parseFloat($(this).find('.turma-promo-valor').val()) || null) : null;
        const promoMeses = temPromo ? (parseInt($(this).find('.turma-promo-meses').val())   || null) : null;

        const maxRaw    = $(this).find('.turma-max-alunos').val();
        const maxAlunos = maxRaw !== '' ? (parseInt(maxRaw) || null) : null;
        const dbId      = $(this).data('dbId') || null; // data-db-id → jQuery converte para dbId

        turmas.push({ id: dbId, nome, horario_indices: indices, valor_mensalidade: valor, genero, nivel, promo_valor: promoValor, promo_meses: promoMeses, max_alunos: maxAlunos });
    });

    return {
        nome:          $('#nome').val().trim(),
        telefone:      $('#telefone').val().trim(),
        email:         $('#email').val().trim(),
        instagram:     $('#instagram').val().trim(),
        cep:           $('#cep').val().replace(/\D/g, ''),
        rua:           $('#rua').val().trim(),
        numero:        $('#numero').val().trim(),
        bairro:        $('#bairro').val().trim(),
        complemento:   $('#complemento').val().trim(),
        cidade:        $('#cidade').val().trim(),
        estado:        $('#estado').val().trim(),
        valor_mensal:           parseFloat($('#valorMensal').val()) || 0,
        dia_pagamento:          parseInt($('#diaPagamento').val()) || 10,
        data_inicio_contrato:   $('#dataInicioContrato').val() || null,
        horarios,
        turmas,
    };
};

// ─── ENVIO ───────────────────────────────────────────────────────────────────

const salvarQuadra = () => {
    if (!validarForm()) {
        const firstError = $('.formGroup__item.error').first();
        if (firstError.length) {
            $('html, body').animate({ scrollTop: firstError.offset().top - 120 }, 350);
        }
        return;
    }

    $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');

    const dados = coletarDados();
    const isEdit = QUADRA_EDIT !== null;

    if (isEdit) dados.id = QUADRA_EDIT.id;

    const url = isEdit
        ? ADMIN_BASE_URL + '/services/update_quadra.php'
        : ADMIN_BASE_URL + '/services/save_quadra.php';

    $.post(url, { dados: JSON.stringify(dados) }, (res) => {
        $('.overlayForm').remove();
        if (res.success) {
            window.location.href = BASE_URL + '/admin/quadras?id=' + res.id;
        } else {
            alert(res.message || 'Erro ao salvar quadra.');
        }
    }, 'json').fail(() => {
        $('.overlayForm').remove();
        alert('Erro ao comunicar com o servidor.');
    });
};

// ─── INIT ─────────────────────────────────────────────────────────────────────

$(document).ready(() => {
    $('#telefone').mask('(99) 99999-9999');
    $('#cep').mask('99999-999');

    $('#cep').on('blur', function () {
        if ($(this).val().replace(/\D/g, '').length === 8) buscaCep();
    });

    $('#btnAddHorario').on('click', addHorario);
    $('#btnAddTurma').on('click', addTurma);
    $('#btnSalvarQuadra').on('click', salvarQuadra);

    $(document).on('click', '.horario-remover', function () {
        removeHorario(parseInt($(this).data('id')));
    });

    $(document).on('change', '.horario-dia, .horario-inicio, .horario-fim', function () {
        updateTurmaCheckboxes();
    });

    $(document).on('click', '.turma-remover', function () {
        removeTurma(parseInt($(this).data('id')));
    });

    $(document).on('change', '.turma-promo-toggle', function () {
        $(this).closest('.turma-item').find('.turma-item__promoFields').toggle($(this).is(':checked'));
    });

    if (QUADRA_EDIT) {
        prefillEdit(QUADRA_EDIT);
    } else {
        addHorario();
    }
});
