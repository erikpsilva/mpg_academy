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
    $whatsappIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" style="display:block; width:18px; height:18px; margin:0 auto;" fill="#ffffff"><path d="m17.507 14.307-.009.075c-2.199-1.096-2.429-1.242-2.713-.816-.197.295-.771.964-.944 1.162-.175.195-.349.21-.646.075-.3-.15-1.263-.465-2.403-1.485-.888-.795-1.484-1.77-1.66-2.07-.293-.506.32-.578.878-1.634.1-.21.049-.375-.025-.524-.075-.15-.672-1.62-.922-2.206-.24-.584-.487-.51-.672-.51-.576-.05-.997-.042-1.368.344-1.614 1.774-1.207 3.604.174 5.55 2.714 3.552 4.16 4.206 6.804 5.114.714.227 1.365.195 1.88.121.574-.091 1.767-.721 2.016-1.426.255-.705.255-1.29.18-1.425-.074-.135-.27-.21-.57-.345z"></path><path d="m20.52 3.449c-7.689-7.433-20.414-2.042-20.419 8.444 0 2.096.549 4.14 1.595 5.945l-1.696 6.162 6.335-1.652c7.905 4.27 17.661-1.4 17.665-10.449 0-3.176-1.24-6.165-3.495-8.411zm1.482 8.417c-.006 7.633-8.385 12.4-15.012 8.504l-.36-.214-3.75.975 1.005-3.645-.239-.375c-4.124-6.565.614-15.145 8.426-15.145 2.654 0 5.145 1.035 7.021 2.91 1.875 1.859 2.909 4.35 2.909 6.99z"></path></svg>';
    $emailIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="17" height="17" style="display:block; width:17px; height:17px; margin:0 auto;" fill="#ffffff"><path d="M467,76H45C20.137,76,0,96.262,0,121v270c0,24.885,20.285,45,45,45h422c24.655,0,45-20.03,45-45V121C512,96.306,491.943,76,467,76z M460.698,106c-9.194,9.145-167.415,166.533-172.878,171.967c-8.5,8.5-19.8,13.18-31.82,13.18s-23.32-4.681-31.848-13.208C220.478,274.284,64.003,118.634,51.302,106H460.698z M30,384.894V127.125L159.638,256.08L30,384.894z M51.321,406l129.587-128.763l22.059,21.943c14.166,14.166,33,21.967,53.033,21.967c20.033,0,38.867-7.801,53.005-21.939l22.087-21.971L460.679,406H51.321z M482,384.894L352.362,256.08L482,127.125V384.894z"></path></svg>';
    $siteIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="18" height="18" style="display:block; width:18px; height:18px; margin:0 auto;" fill="#ffffff"><path d="M16,1A15,15,0,1,0,31,16,15.017,15.017,0,0,0,16,1Zm0,28c-2.065,0-4.227-2.662-5.3-7H21.3C20.227,26.338,18.065,29,16,29Zm-5.706-9a27.358,27.358,0,0,1,0-8H21.706A26.651,26.651,0,0,1,22,16a26.651,26.651,0,0,1-.294,4ZM3,16a12.94,12.94,0,0,1,.636-4h4.65a28.115,28.115,0,0,0,0,8H3.636A12.94,12.94,0,0,1,3,16ZM16,3c2.065,0,4.227,2.662,5.3,7H10.7C11.773,5.662,13.935,3,16,3Zm7.714,9h4.65a12.9,12.9,0,0,1,0,8h-4.65A28.351,28.351,0,0,0,24,16,28.351,28.351,0,0,0,23.714,12Zm3.807-2H23.354a16.766,16.766,0,0,0-2.567-6.075A13.063,13.063,0,0,1,27.521,10ZM11.213,3.925A16.766,16.766,0,0,0,8.646,10H4.479A13.063,13.063,0,0,1,11.213,3.925ZM4.479,22H8.646a16.766,16.766,0,0,0,2.567,6.075A13.063,13.063,0,0,1,4.479,22Zm16.308,6.075A16.766,16.766,0,0,0,23.354,22h4.167A13.063,13.063,0,0,1,20.787,28.075Z"></path></svg>';

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
                  {$whatsappIcon}
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
                  {$emailIcon}
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
                  {$siteIcon}
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
