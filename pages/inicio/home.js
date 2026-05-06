const homeModal = {
    open(type, message) {
        $('.homeModal').remove();

        const title = type === 'success' ? 'Tudo certo!' : 'Não foi possível enviar';
        const button = type === 'success' ? 'Fechar' : 'Tentar novamente';

        $('body').append(`
            <div class="homeModal homeModal--${type}" role="dialog" aria-modal="true" aria-labelledby="homeModalTitle">
                <div class="homeModal__box">
                    <button class="homeModal__close" type="button" aria-label="Fechar modal">×</button>
                    <span class="homeModal__status"></span>
                    <h3 class="homeModal__title" id="homeModalTitle">${title}</h3>
                    <p class="homeModal__text">${message}</p>
                    <button class="homeModal__button" type="button">${button}</button>
                </div>
            </div>
        `);
    },

    close() {
        $('.homeModal').remove();
    },
};

const homeForm = {
    form: null,

    init() {
        this.form = $('#homeLeadForm');
        this.insertMask();
        this.bindSubmit();
        this.bindModal();
    },

    insertMask() {
        $('#celular').mask('(99) 99999-9999');
    },

    bindSubmit() {
        this.form.on('submit', (event) => {
            event.preventDefault();

            const validationMessage = this.getValidationMessage();
            if (validationMessage) {
                homeModal.open('error', validationMessage);
                return;
            }

            this.send();
        });
    },

    bindModal() {
        $('body').on('click', '.homeModal__close, .homeModal__button', () => {
            homeModal.close();
        });
    },

    getValidationMessage() {
        const nome = $('#nome').val().trim();
        const email = $('#email').val().trim();
        const celular = $('#celular').val().replace(/[^\d]/g, '');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!nome || !email || !celular) {
            return 'Preencha nome, e-mail e celular antes de enviar.';
        }

        if (nome.length < 3) {
            return 'Informe um nome com pelo menos 3 caracteres.';
        }

        if (!emailRegex.test(email)) {
            return 'Informe um e-mail válido.';
        }

        if (celular.length !== 11) {
            return 'Informe um celular válido com DDD.';
        }

        return '';
    },

    setLoading(isLoading) {
        const button = this.form.find('.homeForm__button');
        button.prop('disabled', isLoading);
        button.text(isLoading ? 'Enviando...' : 'Quero fazer parte');
    },

    send() {
        this.setLoading(true);

        $.ajax({
            url: this.form.attr('action'),
            type: 'POST',
            dataType: 'json',
            data: this.form.serialize(),
        }).done((response) => {
            if (response.success) {
                this.form[0].reset();
                homeModal.open('success', response.message);
                return;
            }

            homeModal.open('error', response.message || 'Tente novamente em alguns instantes.');
        }).fail((xhr) => {
            const response = xhr.responseJSON || {};
            homeModal.open('error', response.message || 'Erro ao tentar enviar seu cadastro.');
        }).always(() => {
            this.setLoading(false);
        });
    },
};

$(document).ready(() => {
    homeForm.init();
});
