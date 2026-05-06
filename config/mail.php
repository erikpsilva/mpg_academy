<?php

/**
 * Configurações de envio de e-mail.
 *
 * Para usar SMTP (recomendado):
 *   1. Defina MAIL_SMTP_ACTIVE como true
 *   2. Preencha host, porta, usuário e senha com as credenciais do cPanel/Hostinger
 *
 * Se SMTP estiver desativado, o sistema usa a função mail() nativa do PHP como fallback.
 */
function getMpgMailConfig(): array
{
    return [
        'from_name'   => 'MPG Academy',
        'from_addr'   => 'contato@mpgacademy.com.br',
        'smtp_active' => true,
        'smtp_host'   => 'mail.mpgacademy.com.br',
        'smtp_port'   => 587,
        'smtp_user'   => 'contato@mpgacademy.com.br',
        'smtp_pass'   => 'Theking!@389518',
        'smtp_enc'    => 'tls',
    ];
}
