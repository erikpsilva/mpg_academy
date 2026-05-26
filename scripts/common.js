$(document).ready(function () {
    const mobileMenu = $('.headerMobileMenu');
    const menuButton = $('.header__menuButton');
    const loginModal = $('.loginModal');
    const loginForm = $('#siteLoginForm');
    const loginButton = $('.header__student:not(.header__student--logged)');
    const studentMenu = $('.header__studentMenu');
    const studentSidebar = $('.studentAreaSidebar');
    const studentMenuButton = $('.studentAreaTop__menuButton');

    function closeMobileMenu() {
        mobileMenu.removeClass('is-open').attr('aria-hidden', 'true');
        menuButton.attr('aria-expanded', 'false');
        $('body').removeClass('is-menu-open');
    }

    function openMobileMenu() {
        mobileMenu.addClass('is-open').attr('aria-hidden', 'false');
        menuButton.attr('aria-expanded', 'true');
        $('body').addClass('is-menu-open');
    }

    function closeLoginModal() {
        loginModal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('is-menu-open');
        $('.loginModal__message').text('');
    }

    function openLoginModal() {
        closeMobileMenu();
        loginModal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('is-menu-open');
        setTimeout(function () {
            $('#loginModalEmail').trigger('focus');
        }, 150);
    }

    function closeStudentMenu() {
        studentSidebar.removeClass('is-open');
        studentMenuButton.attr('aria-expanded', 'false');
        $('body').removeClass('is-menu-open');
    }

    function openStudentMenu() {
        studentSidebar.addClass('is-open');
        studentMenuButton.attr('aria-expanded', 'true');
        $('body').addClass('is-menu-open');
    }

    studentMenuButton.on('click', function () {
        if (studentSidebar.hasClass('is-open')) {
            closeStudentMenu();
            return;
        }

        openStudentMenu();
    });

    // Dropdown do usuário logado
    studentMenu.find('.header__student--logged').on('click', function () {
        const isOpen = studentMenu.hasClass('is-open');
        studentMenu.toggleClass('is-open');
        $(this).attr('aria-expanded', !isOpen);
        studentMenu.find('.header__studentDropdown').attr('aria-hidden', isOpen);
    });

    $(document).on('click', function (event) {
        if (studentMenu.length && !studentMenu.is(event.target) && !studentMenu.has(event.target).length) {
            studentMenu.removeClass('is-open');
            studentMenu.find('.header__student--logged').attr('aria-expanded', 'false');
            studentMenu.find('.header__studentDropdown').attr('aria-hidden', 'true');
        }
    });

    menuButton.on('click', openMobileMenu);
    $('.headerMobileMenu__close, .headerMobileMenu a').on('click', closeMobileMenu);
    loginButton.on('click', openLoginModal);
    $('[data-login-close]').on('click', closeLoginModal);

    $('.loginModal__passwordToggle').on('click', function togglePassword() {
        const password = $('#loginModalPassword');
        const isPassword = password.attr('type') === 'password';

        password.attr('type', isPassword ? 'text' : 'password');
        $(this)
            .attr('aria-label', isPassword ? 'Esconder senha' : 'Mostrar senha')
            .html(`<i class="${isPassword ? 'icon-esconder' : 'icon-ver'}" aria-hidden="true"></i>`);
    });

    loginForm.on('submit', function submitLogin(event) {
        event.preventDefault();

        const form = $(this);
        const message = $('.loginModal__message');
        const submit = form.find('.loginModal__submit');
        const email = $('#loginModalEmail').val().trim();
        const senha = $('#loginModalPassword').val().trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        message.text('');

        if (!emailRegex.test(email)) {
            message.text('Informe um e-mail valido.');
            return;
        }

        if (senha.length < 6) {
            message.text('Informe sua senha com pelo menos 6 caracteres.');
            return;
        }

        submit.prop('disabled', true).text('Entrando...');

        $.post(form.attr('action'), { email, senha }, function onSuccess(response) {
            if (response.success) {
                window.location.href = form.data('redirect');
                return;
            }

            message.text(response.message || 'Nao foi possivel entrar.');
        }, 'json').fail(function (xhr) {
            const response = xhr.responseJSON || {};
            message.text(response.message || 'Erro ao tentar realizar o login.');
        }).always(function () {
            submit.prop('disabled', false).html('Entrar <i class="icon-go" aria-hidden="true"></i>');
        });
    });

    $('.studentAreaSidebar a').on('click', closeStudentMenu);

    $('a[href^="#"]').on('click', function onAnchorClick(event) {
        const href = $(this).attr('href');

        if (href === '#') {
            return;
        }

        const target = $(href);

        if (!target.length) {
            return;
        }

        event.preventDefault();

        $('html, body').animate({
            scrollTop: target.offset().top - 20,
        }, 450);
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            closeLoginModal();
            closeMobileMenu();
            closeStudentMenu();
            studentMenu.removeClass('is-open');
            studentMenu.find('.header__student--logged').attr('aria-expanded', 'false');
        }
    });
});
