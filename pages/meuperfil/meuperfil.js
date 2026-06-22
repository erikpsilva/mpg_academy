$(document).ready(() => {

    // ─── Máscaras ─────────────────────────────────────────────────────────────
    if ($.fn.mask) {
        $('#studentCep').mask('99999-999');
        $('.studentPhone').mask('(99) 99999-9999');
    }

    // ─── Toggle de senha ──────────────────────────────────────────────────────
    $('.studentField__toggle').on('click', function () {
        const input = $(this).siblings('.studentPassword');
        const isPassword = input.attr('type') === 'password';
        input.attr('type', isPassword ? 'text' : 'password');
        $(this)
            .attr('aria-label', isPassword ? 'Esconder senha' : 'Mostrar senha')
            .html(`<i class="${isPassword ? 'icon-esconder' : 'icon-ver'}" aria-hidden="true"></i>`);
    });

    // ─── Preview da foto ──────────────────────────────────────────────────────
    $('#studentPhoto').on('change', function () {
        const file = this.files && this.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            $('.studentSignup__photoPreview').html(`<img src="${e.target.result}" alt="Foto do aluno">`);
        };
        reader.readAsDataURL(file);
    });

    // ─── Utilitários de validação ─────────────────────────────────────────────
    function validarEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/.test(email.trim());
    }

    function telefoneValido(val) {
        return val.replace(/\D/g, '').length === 11;
    }

    // ─── Sistema de erros inline ──────────────────────────────────────────────
    function fieldError(input, msg) {
        const field = $(input).closest('.studentField');
        field.addClass('studentField--error').find('.studentField__error').remove();
        if (msg) field.append(`<span class="studentField__error">${msg}</span>`);
    }

    function fieldOk(input) {
        $(input).closest('.studentField')
            .removeClass('studentField--error')
            .find('.studentField__error').remove();
    }

    function validateInput(input) {
        const el = $(input);
        const name = el.attr('name');
        const val = el.val();

        const rules = {
            email() {
                if (!val.trim()) return 'E-mail é obrigatório.';
                if (!validarEmail(val)) return 'Informe um e-mail válido.';
                return '';
            },
            sexo() {
                if (!val) return 'Selecione o sexo.';
                return '';
            },
            celular() {
                if (!val.trim()) return 'Celular é obrigatório.';
                if (!telefoneValido(val)) return 'Celular inválido. Inclua o DDD.';
                return '';
            },
            cep() {
                if (!val.trim()) return 'CEP é obrigatório.';
                if (val.replace(/\D/g, '').length !== 8) return 'CEP inválido.';
                return '';
            },
            rua() { return val.trim() ? '' : 'Rua é obrigatória.'; },
            numero() { return val.trim() ? '' : 'Número é obrigatório.'; },
            bairro() { return val.trim() ? '' : 'Bairro é obrigatório.'; },
            cidade() { return val.trim() ? '' : 'Cidade é obrigatória.'; },
            estado() { return val ? '' : 'Selecione o estado.'; },
            senha() {
                if (!val) return '';
                if (val.length < 8) return 'Mínimo de 8 caracteres.';
                return '';
            },
            confirmar_senha() {
                const senha = $('[name="senha"]').val();
                if (!senha && !val) return '';
                if (val !== senha) return 'As senhas não coincidem.';
                return '';
            },
        };

        const rule = rules[name];
        if (!rule) return true;

        const msg = rule();
        if (msg) { fieldError(input, msg); return false; }
        fieldOk(input);
        return true;
    }

    // ─── Validação em blur ────────────────────────────────────────────────────
    $('#studentEditDataForm input:not([disabled]), #studentEditDataForm select').on('blur', function () {
        validateInput(this);
    });

    $('#studentEditDataForm input:not([disabled]), #studentEditDataForm select').on('input change', function () {
        if ($(this).closest('.studentField').hasClass('studentField--error')) {
            validateInput(this);
        }
    });

    // ─── ViaCEP ───────────────────────────────────────────────────────────────
    function buscarCep() {
        const cep = $('#studentCep').val().replace(/\D/g, '');
        const btn = $('#studentSearchCep');

        if (cep.length !== 8) {
            fieldError('#studentCep', 'CEP inválido.');
            return;
        }

        btn.prop('disabled', true).text('Buscando...');

        $.getJSON(`https://viacep.com.br/ws/${cep}/json/`)
            .done((data) => {
                if (data.erro) {
                    fieldError('#studentCep', 'CEP não encontrado.');
                    return;
                }
                $('[name="rua"]').val(data.logradouro || '').trigger('input');
                $('[name="bairro"]').val(data.bairro || '').trigger('input');
                $('[name="cidade"]').val(data.localidade || '').trigger('input');
                $('[name="estado"]').val(data.uf || '').trigger('change');
                fieldOk('#studentCep');
                $('[name="numero"]').trigger('focus');
            })
            .fail(() => fieldError('#studentCep', 'Erro ao buscar o CEP. Tente novamente.'))
            .always(() => btn.prop('disabled', false).text('Buscar CEP'));
    }

    $('#studentSearchCep').on('click', buscarCep);

    $('#studentCep').on('blur', function () {
        const digits = $(this).val().replace(/\D/g, '');
        if (digits.length === 8) buscarCep();
        else validateInput(this);
    });

    // ─── Envio do formulário ──────────────────────────────────────────────────
    $('#studentEditDataForm').on('submit', function (event) {
        event.preventDefault();

        const form = $(this);
        const submit = form.find('[type="submit"]');

        $('.studentSignupNotice').remove();

        let valido = true;
        form.find('input:not([disabled]):not([type="file"]):not([type="checkbox"]), select').each(function () {
            const name = $(this).attr('name');
            if (!name || name === 'complemento') return;
            if (!validateInput(this)) valido = false;
        });

        if (!valido) {
            const primeiroErro = form.find('.studentField--error').first();
            if (primeiroErro.length) {
                $('html, body').animate({ scrollTop: primeiroErro.offset().top - 80 }, 300);
            }
            return;
        }

        const formData = new FormData(this);
        const photoFile = $('#studentPhoto')[0].files[0];
        if (photoFile) formData.set('foto', photoFile);

        submit.prop('disabled', true).html('Salvando... <i class="icon-go" aria-hidden="true"></i>');

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
        }).done((response) => {
            if (response.success) {
                form.prepend(`<p class="studentSignupNotice studentSignupNotice--success">${response.message}</p>`);
                $('html, body').animate({ scrollTop: 0 }, 400);
                $('[name="senha"], [name="confirmar_senha"]').val('');
                return;
            }
            form.prepend(`<p class="studentSignupNotice studentSignupNotice--error">${response.message || 'Não foi possível salvar.'}</p>`);
        }).fail((xhr) => {
            const response = xhr.responseJSON || {};
            form.prepend(`<p class="studentSignupNotice studentSignupNotice--error">${response.message || 'Erro ao salvar. Tente novamente.'}</p>`);
        }).always(() => {
            submit.prop('disabled', false).html('Salvar dados <i class="icon-go" aria-hidden="true"></i>');
        });
    });

    // ─── Pagamento automático (cartão salvo) ─────────────────────────────────
    (function () {
        const container = document.getElementById('autoPagBrick_container');
        if (!container) return;

        function exibirCartaoSalvo(bandeira, final4) {
            $('#autoPagForm').hide();
            const nomeBandeira = bandeira ? bandeira.charAt(0).toUpperCase() + bandeira.slice(1) : 'Cartão';
            const html = `
                <div class="autoPagCard" id="autoPagCardInfo">
                    <div class="autoPagCard__info">
                        <i class="icon-creditcard" aria-hidden="true"></i>
                        <span>${nomeBandeira} final ${final4}</span>
                    </div>
                    <label class="autoPagCard__switch">
                        <input type="checkbox" id="chkAutoPagamento" checked>
                        <span>Cobrança automática ativada</span>
                    </label>
                    <button type="button" id="btnTrocarCartao" class="autoPagCard__link">Trocar cartão</button>
                    <button type="button" id="btnRemoverCartao" class="autoPagCard__link autoPagCard__link--danger">Remover cartão</button>
                </div>`;
            $('#autoPagCardInfo').replaceWith(html);
            bindCardActions();
        }

        function bindCardActions() {
            $('#chkAutoPagamento').off('change').on('change', function () {
                const ativar = $(this).is(':checked');
                $.ajax({
                    url: BASE_URL + '/services/site/toggle_auto_pagamento.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ ativar }),
                    dataType: 'json',
                }).done((resp) => {
                    if (!resp.success) {
                        alert(resp.message || 'Não foi possível atualizar.');
                        $('#chkAutoPagamento').prop('checked', !ativar);
                    }
                });
            });

            $('#btnTrocarCartao').off('click').on('click', function () {
                $('#autoPagForm').show();
                $('html, body').animate({ scrollTop: $('#autoPagamentoBox').offset().top - 80 }, 300);
            });

            $('#btnRemoverCartao').off('click').on('click', function () {
                if (!confirm('Remover o cartão salvo? A cobrança automática será desativada.')) return;
                $.ajax({
                    url: BASE_URL + '/services/site/remover_cartao.php',
                    type: 'POST',
                    dataType: 'json',
                }).done((resp) => {
                    if (resp.success) {
                        $('#autoPagCardInfo').replaceWith('<div id="autoPagCardInfo" style="display:none;"></div>');
                        $('#autoPagForm').show();
                    } else {
                        alert(resp.message || 'Não foi possível remover o cartão.');
                    }
                });
            });
        }

        function carregarBrick() {
            if (!window.MercadoPago || !window.MP_PUBLIC_KEY) return;
            const mp = new MercadoPago(window.MP_PUBLIC_KEY, { locale: 'pt-BR' });
            const bricksBuilder = mp.bricks();

            bricksBuilder.create('cardPayment', 'autoPagBrick_container', {
                initialization: {
                    amount: 1,
                    payer:  { email: window.ALUNO_EMAIL || '' },
                },
                customization: {
                    paymentMethods: { maxInstallments: 1 },
                },
                callbacks: {
                    onReady: function () {},
                    onSubmit: function (formData) {
                        return new Promise((resolve, reject) => {
                            $.ajax({
                                url: BASE_URL + '/services/site/salvar_cartao.php',
                                type: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify({ token: formData.token }),
                                dataType: 'json',
                            }).done((resp) => {
                                if (resp.success) {
                                    exibirCartaoSalvo(resp.bandeira, resp.final4);
                                    resolve();
                                } else {
                                    alert(resp.message || 'Não foi possível salvar o cartão.');
                                    reject();
                                }
                            }).fail((xhr) => {
                                const resp = xhr.responseJSON || {};
                                alert(resp.message || 'Erro ao salvar cartão. Tente novamente.');
                                reject();
                            });
                        });
                    },
                    onError: function (error) {
                        console.error('MP Brick error:', error);
                    },
                },
            });
        }

        bindCardActions();
        carregarBrick();
    }());
});
