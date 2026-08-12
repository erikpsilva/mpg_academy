(function () {
    'use strict';

    var form   = document.getElementById('bateBolaSignupForm');
    var status = document.getElementById('bateBolaSignupStatus');
    if (!form) return;

    if (window.jQuery && jQuery.fn.mask) {
        jQuery(form).find('[name="celular"]').mask('(99) 99999-9999');
        jQuery(form).find('[name="altura_cm"]').mask('999');
    }

    var submit = form.querySelector('.bateBolaSignupForm__submit');

    var fotoInput = document.getElementById('bateBolaFoto');
    if (fotoInput) {
        fotoInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file || !file.type.startsWith('image/')) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                document.querySelector('.bateBolaSignup__photoPreview').innerHTML =
                    '<img src="' + e.target.result + '" alt="Foto">';
            };
            reader.readAsDataURL(file);
        });
    }

    function showStatus(text, ok) {
        status.textContent = text;
        status.className = 'bateBolaSignupForm__status ' + (ok ? 'is-success' : 'is-error');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        status.textContent = '';
        status.className = 'bateBolaSignupForm__status';

        var senha = form.querySelector('[name="senha"]').value;
        var confirmarSenha = form.querySelector('[name="confirmar_senha"]').value;

        if (senha.length < 6) {
            showStatus('A senha precisa ter pelo menos 6 caracteres.', false);
            return;
        }
        if (senha !== confirmarSenha) {
            showStatus('As senhas não coincidem.', false);
            return;
        }

        submit.disabled = true;
        submit.textContent = 'Criando conta...';

        var formData = new FormData(form);

        fetch(form.getAttribute('action'), { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    showStatus(res.message, true);
                    form.reset();
                    setTimeout(function () {
                        window.location.href = form.dataset.redirect;
                    }, 2000);
                    return;
                }
                showStatus(res.message || 'Não foi possível criar sua conta.', false);
                submit.disabled = false;
                submit.innerHTML = 'Criar minha conta <i class="icon-go" aria-hidden="true"></i>';
            })
            .catch(function () {
                showStatus('Erro ao tentar criar sua conta. Tente novamente.', false);
                submit.disabled = false;
                submit.innerHTML = 'Criar minha conta <i class="icon-go" aria-hidden="true"></i>';
            });
    });
}());
