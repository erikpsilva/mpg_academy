<?php
$signatures = [
    [
        'slug' => 'erik-primao',
        'name' => 'Erik Primão',
        'role' => 'Gestão e Engenheiro de Software',
        'email' => 'erikprimao@mpgacademy.com.br',
        'phone' => '(11) 94230-7240',
    ],
    [
        'slug' => 'gabriel-ferrari',
        'name' => 'Gabriel Ferrari',
        'role' => 'Gestão e Gerente de Produto',
        'email' => 'gabrielferrari@mpgacademy.com.br',
        'phone' => '(27) 99776-8124',
    ],
    [
        'slug' => 'manoela-vieira',
        'name' => 'Manoela Vieira',
        'role' => 'Gestão e Gerente Comercial',
        'email' => 'manoelavieira@mpgacademy.com.br',
        'phone' => '(11) 94070-4029',
    ],
];

function signatureInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));

    if (!$parts || count($parts) === 0) {
        return 'MPG';
    }

    $first = substr($parts[0], 0, 1);
    $last = substr($parts[count($parts) - 1], 0, 1);

    return strtoupper($first . $last);
}

function renderMpgSignature(array $signature): string
{
    $name     = htmlspecialchars($signature['name'],  ENT_QUOTES, 'UTF-8');
    $role     = htmlspecialchars($signature['role'],  ENT_QUOTES, 'UTF-8');
    $email    = htmlspecialchars($signature['email'], ENT_QUOTES, 'UTF-8');
    $phone    = htmlspecialchars($signature['phone'], ENT_QUOTES, 'UTF-8');
    $initials = htmlspecialchars(signatureInitials($signature['name']), ENT_QUOTES, 'UTF-8');
    $logo     = 'https://www.mpgacademy.com.br/images/logo.png';
    $whatsappIcon = 'https://www.mpgacademy.com.br/images/icons/whatasApp.png';
    $emailIcon = 'https://www.mpgacademy.com.br/images/icons/email.png';
    $siteIcon = 'https://www.mpgacademy.com.br/images/icons/internet.png';

    return <<<HTML
<table cellpadding="0" cellspacing="0" border="0" style="font-family: Arial, sans-serif; width: 100%; max-width: 640px; border-radius: 14px; overflow: hidden; border: 1px solid #e5e5e5;">
  <tr>
    <td style="background: #0D0D0D; padding: 20px 24px 16px; border-radius: 14px 14px 0 0;">
      <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
          <td style="vertical-align: middle;">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="vertical-align: middle;">
                  <div style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid #F5C800; background: #1a1a1a; text-align: center; line-height: 50px; font-weight: 700; font-size: 15px; color: #F5C800;">{$initials}</div>
                </td>
                <td style="padding-left: 14px; vertical-align: middle;">
                  <p style="margin: 0; font-size: 17px; font-weight: 700; color: #FFFFFF;">{$name}</p>
                  <p style="margin: 3px 0 0; font-size: 10px; color: #F5C800; letter-spacing: 2px; text-transform: uppercase;">{$role}</p>
                </td>
              </tr>
            </table>
          </td>
          <td style="text-align: right; vertical-align: middle;">
            <img src="{$logo}" alt="MPG Academy" style="height: 56px; width: auto;">
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr><td style="height: 3px; background: #F5C800; font-size: 0;">&nbsp;</td></tr>
  <tr>
    <td style="background: #ffffff; padding: 18px 24px 14px;">
      <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
          <td style="padding-bottom: 12px;">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="width: 32px; height: 32px; background: #25D366; border-radius: 8px; text-align: center; vertical-align: middle;">
                  <img src="{$whatsappIcon}" alt="WhatsApp" style="display: block; width: 18px; height: 18px; margin: 0 auto; border: 0;">
                </td>
                <td style="padding-left: 12px; vertical-align: middle;">
                  <p style="margin: 0; font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: 1px;">WhatsApp</p>
                  <p style="margin: 1px 0 0; font-size: 13px; color: #111; font-weight: 700;">{$phone}</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr><td style="height: 0.5px; background: #eeeeee; font-size: 0; padding-bottom: 12px;">&nbsp;</td></tr>
        <tr>
          <td style="padding-bottom: 12px; padding-top: 12px;">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="width: 32px; height: 32px; background: #0D0D0D; border-radius: 8px; text-align: center; vertical-align: middle;">
                  <img src="{$emailIcon}" alt="E-mail" style="display: block; width: 17px; height: 17px; margin: 0 auto; border: 0;">
                </td>
                <td style="padding-left: 12px; vertical-align: middle;">
                  <p style="margin: 0; font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: 1px;">E-mail</p>
                  <a href="mailto:{$email}" style="display: block; margin: 1px 0 0; font-size: 13px; color: #111; font-weight: 700; text-decoration: none;">{$email}</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr><td style="height: 0.5px; background: #eeeeee; font-size: 0; padding-bottom: 12px;">&nbsp;</td></tr>
        <tr>
          <td style="padding-top: 12px;">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="width: 32px; height: 32px; background: #F5C800; border-radius: 8px; text-align: center; vertical-align: middle;">
                  <img src="{$siteIcon}" alt="Site" style="display: block; width: 18px; height: 18px; margin: 0 auto; border: 0;">
                </td>
                <td style="padding-left: 12px; vertical-align: middle;">
                  <p style="margin: 0; font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: 1px;">Site</p>
                  <a href="https://www.mpgacademy.com.br" style="display: block; margin: 1px 0 0; font-size: 13px; color: #111; font-weight: 700; text-decoration: none;">mpgacademy.com.br</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style="background: #0D0D0D; padding: 10px 24px; border-radius: 0 0 14px 14px;">
      <p style="margin: 0; font-size: 10px; color: #555; letter-spacing: 0.8px; text-transform: uppercase;">Escola de Vôlei &middot; São Paulo, SP</p>
    </td>
  </tr>
</table>
HTML;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>Assinaturas | MPG Academy</title>

<?php include ROOT . '/includes/assets.php';?>

</head>

<body>

<main class="signatures">
    <section class="signaturesHero">
        <div class="container">
            <header class="signaturesHeader">
                <a href="<?= BASE_URL ?>" class="signaturesBrand" aria-label="MPG Academy">
                    <img class="signaturesBrand__logo" src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">
                </a>

                <a class="signaturesHeader__back" href="<?= BASE_URL ?>">Voltar para o site</a>
            </header>

            <div class="signaturesHero__grid">
                <div class="signaturesHero__content">
                    <span class="signaturesTag">Assinaturas MPG Academy</span>
                    <h1 class="signaturesHero__title">Assinaturas de e-mail da equipe</h1>
                    <p class="signaturesHero__text">Selecione uma pessoa no menu para acessar a assinatura pronta.</p>
                </div>

                <nav class="signaturesMenu" aria-label="Menu de assinaturas">
                    <?php foreach ($signatures as $signature): ?>
                        <a class="signaturesMenu__item" href="#<?= htmlspecialchars($signature['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <span class="signaturesMenu__avatar"><?= htmlspecialchars(signatureInitials($signature['name']), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="signaturesMenu__content">
                                <strong><?= htmlspecialchars($signature['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars($signature['role'], ENT_QUOTES, 'UTF-8') ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </section>

    <section class="signaturesList">
        <div class="container">
            <div class="signaturesList__grid">
                <?php foreach ($signatures as $signature): ?>
                    <?php $signatureHtml = renderMpgSignature($signature); ?>
                    <article class="signatureCard" id="<?= htmlspecialchars($signature['slug'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="signatureCard__head">
                            <div>
                                <span class="signatureCard__eyebrow">MPG Academy</span>
                                <h2 class="signatureCard__title"><?= htmlspecialchars($signature['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="signatureCard__role"><?= htmlspecialchars($signature['role'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <button class="signatureCard__copy" type="button" data-signature-target="<?= htmlspecialchars($signature['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                Copiar HTML
                            </button>
                        </div>

                        <div class="signatureCard__preview" data-signature-id="<?= htmlspecialchars($signature['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= $signatureHtml ?>
                        </div>

                        <textarea class="signatureCard__source" id="source-<?= htmlspecialchars($signature['slug'], ENT_QUOTES, 'UTF-8') ?>" readonly><?= htmlspecialchars($signatureHtml, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php include ROOT . '/includes/scripts.php';?>

<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/assinaturas/assinaturas.js?' . $version . '"></script>';
?>

</body>
</html>
