<?php
$routeParts   = explode('/', $_GET['url'] ?? 'inicio');
$currentRoute = preg_replace('/[^a-zA-Z0-9_-]/', '', $routeParts[0] ?? 'inicio');

$defaultTitle       = 'MPG Academy — Escola de Vôlei na Zona Norte de São Paulo';
$defaultDescription = 'A MPG Academy é uma escola de vôlei na Zona Norte de São Paulo com treinos de qualidade, estrutura completa e turmas para todos os níveis. Venha jogar e evoluir!';

$seoTitles = [
    'inicio'       => 'MPG Academy — Escola de Vôlei na Zona Norte de São Paulo',
    'quemsomos'    => 'Quem Somos — MPG Academy | Escola de Vôlei Zona Norte SP',
    'turmastreino' => 'Turmas e Valores — MPG Academy | Vôlei Zona Norte SP',
    'cadastro'     => 'Matricule-se — MPG Academy | Aulas de Vôlei em São Paulo',
    'patrocinio'   => 'Seja Patrocinador — MPG Academy | Vôlei Zona Norte SP',
    'comunicados'  => 'Comunicados — MPG Academy',
    'contato'      => 'Contato — MPG Academy | Escola de Vôlei SP',
];

$seoDescriptions = [
    'inicio'       => $defaultDescription,
    'quemsomos'    => 'Conheça a MPG Academy, escola de vôlei da Zona Norte de São Paulo. Metodologia moderna, ambiente familiar e suporte qualificado para quem quer aprender e evoluir no vôlei.',
    'turmastreino' => 'Turmas e valores da MPG Academy: vôlei para iniciantes e avançados na Zona Norte de SP. Horários organizados, professores qualificados e estrutura completa.',
    'cadastro'     => 'Faça sua matrícula na MPG Academy e comece a jogar vôlei na Zona Norte de São Paulo. Treinos planejados e acompanhamento profissional para todos os níveis.',
    'patrocinio'   => 'Conecte sua marca à MPG Academy e alcance uma comunidade ativa de vôlei adulto na Zona Norte de São Paulo. Conheça nossos planos de patrocínio.',
    'comunicados'  => 'Fique por dentro das novidades, avisos e comunicados da MPG Academy, escola de vôlei da Zona Norte de SP.',
    'contato'      => 'Entre em contato com a MPG Academy, escola de vôlei da Zona Norte de São Paulo. Tire suas dúvidas e saiba mais sobre as aulas.',
];

$siteTitle      = $seoTitles[$currentRoute]       ?? $defaultTitle;
$seoDescription = $seoDescriptions[$currentRoute] ?? $defaultDescription;
$siteUrl        = BASE_URL . '/' . ($currentRoute !== 'inicio' ? $currentRoute : '');
$canonicalUrl   = htmlspecialchars(rtrim($siteUrl, '/')) ?: BASE_URL;
$ogImage        = BASE_URL . '/images/logoComFundo.jpg';

$seoKeywords = 'MPG Academy, volei, vôlei, jogar vôlei, escola de vôlei, vôlei zona norte, treino de vôlei, aula de vôlei, volei adulto, volei iniciante, volei intermediário, voleibol, São Paulo, Zona Norte São Paulo';

// Páginas de login/privadas não devem ser indexadas
$noIndexRoutes = ['areadoaluno', 'treinos', 'meuperfil', 'mensalidades', 'pagamento', 'termo', 'assinaturas'];
$robotsContent = in_array($currentRoute, $noIndexRoutes) ? 'noindex, nofollow' : 'index, follow';
?>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-54VBY2Q50P"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-54VBY2Q50P');
</script>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">

<title><?= htmlspecialchars($siteTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
<meta name="keywords"    content="<?= htmlspecialchars($seoKeywords) ?>">
<meta name="author"      content="MPG Academy">
<meta name="robots"      content="<?= $robotsContent ?>">
<link rel="canonical"    href="<?= $canonicalUrl ?>">

<!-- Open Graph (Facebook / Instagram) -->
<meta property="og:type"        content="website">
<meta property="og:locale"      content="pt_BR">
<meta property="og:site_name"   content="MPG Academy">
<meta property="og:title"       content="<?= htmlspecialchars($siteTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
<meta property="og:url"         content="<?= $canonicalUrl ?>">
<meta property="og:image"       content="<?= $ogImage ?>">
<meta property="og:image:type"  content="image/jpeg">
<meta property="og:image:alt"   content="MPG Academy — Escola de Vôlei Zona Norte SP">

<!-- Twitter Card -->
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= htmlspecialchars($siteTitle) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($seoDescription) ?>">
<meta name="twitter:image"       content="<?= $ogImage ?>">
<meta name="twitter:image:alt"   content="MPG Academy — Escola de Vôlei Zona Norte SP">

<?php if ($currentRoute === 'inicio'): ?>
<!-- Schema.org — LocalBusiness -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SportsActivityLocation",
  "name": "MPG Academy",
  "description": "<?= addslashes($defaultDescription) ?>",
  "url": "<?= BASE_URL ?>",
  "logo": "<?= BASE_URL ?>/images/logoComFundo.jpg",
  "image": "<?= BASE_URL ?>/images/logoComFundo.jpg",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "São Paulo",
    "addressRegion": "SP",
    "addressCountry": "BR"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "addressLocality": "Zona Norte, São Paulo"
  },
  "sameAs": [
    "https://www.instagram.com/mpgacademy"
  ],
  "sport": "Volleyball",
  "openingHoursSpecification": {
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],
    "opens": "07:00",
    "closes": "22:00"
  }
}
</script>
<?php endif; ?>

<link rel="icon" href="<?= BASE_URL ?>/images/favicon.png" type="image/x-icon">

<?php
$version = time();
echo '<link rel="stylesheet" type="text/css" href="' . BASE_URL . '/styles/style.min.css?v' . $version . '">';
?>
