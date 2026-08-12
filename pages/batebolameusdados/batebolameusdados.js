(function () {
    'use strict';

    var form   = document.getElementById('bateBolaDadosForm');
    var status = document.getElementById('bbDadosStatus');
    if (!form) return;

    if (window.jQuery && jQuery.fn.mask) {
        jQuery(form).find('[name="celular"]').mask('(99) 99999-9999');
        jQuery(form).find('[name="altura_cm"]').mask('999');
    }

    var submit = form.querySelector('.bateBolaSignupForm__submit');

    var fotoInput = document.getElementById('bbDadosFoto');
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

        if (senha !== '' && senha.length < 6) {
            showStatus('A nova senha precisa ter pelo menos 6 caracteres.', false);
            return;
        }
        if (senha !== confirmarSenha) {
            showStatus('As senhas não coincidem.', false);
            return;
        }

        submit.disabled = true;
        submit.textContent = 'Salvando...';

        var formData = new FormData(form);

        fetch(BASE_URL + '/services/site/update_jogador.php', { method: 'POST', credentials: 'same-origin', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                showStatus(res.message || (res.success ? 'Dados atualizados!' : 'Erro ao salvar.'), res.success);
                submit.disabled = false;
                submit.innerHTML = 'Salvar alterações <i class="icon-go" aria-hidden="true"></i>';
                if (res.success) {
                    form.querySelector('[name="senha"]').value = '';
                    form.querySelector('[name="confirmar_senha"]').value = '';
                }
            })
            .catch(function () {
                showStatus('Erro ao tentar salvar. Tente novamente.', false);
                submit.disabled = false;
                submit.innerHTML = 'Salvar alterações <i class="icon-go" aria-hidden="true"></i>';
            });
    });
}());
