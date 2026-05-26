$(document).ready(() => {

    // ─── Máscaras ────────────────────────────────────────────────────────────
    if ($.fn.mask) {
        $('#studentCpf').mask('999.999.999-99');
        $('#studentBirthDate').mask('99/99/9999');
        $('#studentCep').mask('99999-999');
        $('.studentPhone').mask('(99) 99999-9999');
    }

    // ─── Utilitários de validação ─────────────────────────────────────────────
    function validarCPF(cpf) {
        cpf = cpf.replace(/[^\d]/g, '');
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;

        let soma = 0;
        for (let i = 0; i < 9; i++) soma += parseInt(cpf[i]) * (10 - i);
        let resto = (soma * 10) % 11;
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf[9])) return false;

        soma = 0;
        for (let i = 0; i < 10; i++) soma += parseInt(cpf[i]) * (11 - i);
        resto = (soma * 10) % 11;
        if (resto === 10 || resto === 11) resto = 0;
        return resto === parseInt(cpf[10]);
    }

    function validarEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/.test(email.trim());
    }

    function validarData(data) {
        const parts = data.split('/');
        if (parts.length !== 3) return false;
        const [d, m, a] = parts.map(Number);
        if (!d || !m || !a || a < 1900 || a > new Date().getFullYear() - 5) return false;
        return !isNaN(new Date(`${a}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`).getTime());
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
            .find('.studentField__error')
            .remove();
    }

    function validateInput(input) {
        const el = $(input);
        const name = el.attr('name');
        const val = el.val();

        const rules = {
            nome() {
                if (!val.trim()) return 'Nome é obrigatório.';
                if (val.trim().length < 3) return 'Mínimo de 3 caracteres.';
                return '';
            },
            cpf() {
                if (!val.trim()) return 'CPF é obrigatório.';
                if (!validarCPF(val)) return 'CPF inválido. Verifique os dígitos.';
                return '';
            },
            nascimento() {
                if (!val.trim()) return 'Data de nascimento é obrigatória.';
                if (!validarData(val)) return 'Data inválida. Use DD/MM/AAAA.';
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
            email() {
                if (!val.trim()) return 'E-mail é obrigatório.';
                if (!validarEmail(val)) return 'E-mail inválido. Ex: nome@dominio.com';
                return '';
            },
            whatsapp() {
                if (!val.trim()) return 'WhatsApp é obrigatório.';
                if (!telefoneValido(val)) return 'Número inválido. Inclua o DDD.';
                return '';
            },
            cep() {
                if (!val.trim()) return 'CEP é obrigatório.';
                if (val.replace(/\D/g, '').length !== 8) return 'CEP inválido.';
                return '';
            },
            rua() {
                if (!val.trim()) return 'Rua é obrigatória.';
                return '';
            },
            numero() {
                if (!val.trim()) return 'Número é obrigatório.';
                return '';
            },
            bairro() {
                if (!val.trim()) return 'Bairro é obrigatório.';
                return '';
            },
            cidade() {
                if (!val.trim()) return 'Cidade é obrigatória.';
                return '';
            },
            estado() {
                if (!val) return 'Selecione o estado.';
                return '';
            },
            senha() {
                if (!val) return 'Senha é obrigatória.';
                if (val.length < 8) return 'Mínimo de 8 caracteres.';
                return '';
            },
            confirmar_senha() {
                const senha = $('[name="senha"]').val();
                if (!val) return 'Confirme sua senha.';
                if (val !== senha) return 'As senhas não coincidem.';
                return '';
            },
        };

        const rule = rules[name];
        if (!rule) return true;

        const msg = rule();
        if (msg) {
            fieldError(input, msg);
            return false;
        }
        fieldOk(input);
        return true;
    }

    // ─── Validação em blur (ao sair do campo) ─────────────────────────────────
    $('#studentSignupForm input, #studentSignupForm select').on('blur', function () {
        if ($(this).attr('type') === 'checkbox') return;
        validateInput(this);
    });

    // Remove erro ao comecar a digitar
    $('#studentSignupForm input, #studentSignupForm select').on('input change', function () {
        if ($(this).closest('.studentField').hasClass('studentField--error')) {
            validateInput(this);
        }
    });

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
                    btn.prop('disabled', false).text('Buscar CEP');
                    return;
                }

                $('[name="rua"]').val(data.logradouro || '').trigger('input');
                $('[name="bairro"]').val(data.bairro || '').trigger('input');
                $('[name="cidade"]').val(data.localidade || '').trigger('input');
                $('[name="estado"]').val(data.uf || '').trigger('change');
                fieldOk('#studentCep');

                $('[name="numero"]').trigger('focus');
            })
            .fail(() => {
                fieldError('#studentCep', 'Erro ao buscar o CEP. Tente novamente.');
            })
            .always(() => {
                btn.prop('disabled', false).text('Buscar CEP');
            });
    }

    $('#studentSearchCep').on('click', buscarCep);

    $('#studentCep').on('blur', function () {
        const digits = $(this).val().replace(/\D/g, '');
        if (digits.length === 8) {
            buscarCep();
        } else {
            validateInput(this);
        }
    });

    // ─── Envio do formulário ──────────────────────────────────────────────────
    $('#studentSignupForm').on('submit', function submitSignup(event) {
        event.preventDefault();

        const form = $(this);
        const submit = form.find('[type="submit"]');

        $('.studentSignupNotice').remove();

        // Valida todos os campos obrigatórios
        let valido = true;
        form.find('input:not([type="checkbox"]):not([type="file"]), select').each(function () {
            const name = $(this).attr('name');
            if (!name || name === 'complemento' || name === 'origem') return;
            if (!validateInput(this)) valido = false;
        });

        // Valida termos
        if (!$('[name="termos"]').is(':checked')) {
            form.find('.studentSignupTerms').addClass('studentSignupTerms--error');
            valido = false;
        } else {
            form.find('.studentSignupTerms').removeClass('studentSignupTerms--error');
        }

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

        submit.prop('disabled', true).html('Criando conta... <i class="icon-go" aria-hidden="true"></i>');

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
                form[0].reset();
                $('.studentSignup__photoPreview').html('<i class="icon-areadoaluno" aria-hidden="true"></i>');
                $('html, body').animate({ scrollTop: 0 }, 400);
                setTimeout(() => {
                    window.location.href = form.data('redirect');
                }, 2500);
                return;
            }
            form.prepend(`<p class="studentSignupNotice studentSignupNotice--error">${response.message || 'Não foi possível criar sua conta.'}</p>`);
        }).fail((xhr) => {
            const response = xhr.responseJSON || {};
            form.prepend(`<p class="studentSignupNotice studentSignupNotice--error">${response.message || 'Erro ao tentar criar sua conta. Tente novamente.'}</p>`);
        }).always(() => {
            submit.prop('disabled', false).html('Criar minha conta <i class="icon-go" aria-hidden="true"></i>');
        });
    });
});
