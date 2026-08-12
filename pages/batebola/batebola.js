(function () {
    'use strict';

    const modal = document.getElementById('bateBolaLoginModal');
    if (!modal) return;

    const email = document.getElementById('bateBolaEmail');
    const openButtons = document.querySelectorAll('[data-batebola-login-open]');
    const closeButtons = modal.querySelectorAll('[data-batebola-login-close]');
    const password = document.getElementById('bateBolaPassword');
    const passwordToggle = modal.querySelector('.bateBolaLogin__passwordToggle');
    const loginForm = document.getElementById('bateBolaLoginForm');
    const loginMessage = modal.querySelector('.bateBolaLogin__message');
    const loginSubmit = modal.querySelector('.bateBolaLogin__submit');

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('bateBolaModalOpen');
        window.setTimeout(function () { email.focus(); }, 100);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('bateBolaModalOpen');
    }

    openButtons.forEach(function (button) { button.addEventListener('click', openModal); });
    closeButtons.forEach(function (button) { button.addEventListener('click', closeModal); });
    passwordToggle.addEventListener('click', function () {
        const showPassword = password.type === 'password';
        password.type = showPassword ? 'text' : 'password';
        passwordToggle.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            event.preventDefault();
            loginMessage.textContent = '';

            var formData = new FormData(loginForm);
            loginSubmit.disabled = true;
            loginSubmit.textContent = 'Entrando...';

            fetch(loginForm.getAttribute('action'), { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        window.location.href = loginForm.dataset.redirect;
                        return;
                    }
                    loginMessage.textContent = res.message || 'Não foi possível entrar.';
                    loginSubmit.disabled = false;
                    loginSubmit.innerHTML = 'Entrar <i class="icon-go" aria-hidden="true"></i>';
                })
                .catch(function () {
                    loginMessage.textContent = 'Erro ao tentar realizar o login.';
                    loginSubmit.disabled = false;
                    loginSubmit.innerHTML = 'Entrar <i class="icon-go" aria-hidden="true"></i>';
                });
        });
    }
}());
