<?php
$routeParts = explode('/', $_GET['url'] ?? 'inicio');
$currentRoute = preg_replace('/[^a-zA-Z0-9_-]/', '', $routeParts[0] ?? 'inicio');
$siteTitle = 'MPG Academy';
$siteUrl = BASE_URL . '/' . ($currentRoute !== 'inicio' ? $currentRoute : '');
$defaultDescription = 'A MPG Academy é uma escola de volei na Zona Norte de São Paulo, com treinos de qualidade, estrutura completa, equipamentos adequados e turmas para quem quer aprender, jogar e evoluir no volei.';
$seoDescriptions = [
    'inicio' => $defaultDescription,
    'quemsomos' => 'Conheça a MPG Academy, escola de volei da Zona Norte de São Paulo criada para desenvolver alunos com metodologia moderna, ambiente familiar, suporte qualificado e treinos de qualidade.',
    'turmastreino' => 'Veja turmas e valores da MPG Academy: escola de volei na Zona Norte com aulas para iniciantes, treinos por nível, horários organizados e estrutura de qualidade.',
    'cadastro' => 'Cadastre-se na MPG Academy e venha jogar volei na Zona Norte de São Paulo com treinos planejados, acompanhamento profissional e ambiente acolhedor.',
    'areadoaluno' => 'Área do aluno MPG Academy para acompanhar treinos, comunicados, perfil e informações da sua jornada no volei.',
    'treinos' => 'Agenda de treinos da MPG Academy, escola de volei na Zona Norte de São Paulo com aulas planejadas para evolução técnica e física.',
    'comunicados' => 'Comunicados da MPG Academy com novidades, avisos e informações importantes para alunos da escola de volei.',
    'meuperfil' => 'Perfil do aluno MPG Academy com dados cadastrais e informações da conta.',
];
$seoDescription = $seoDescriptions[$currentRoute] ?? $defaultDescription;
$seoKeywords = 'MPG Academy, volei, jogar volei, jogar vole, volei zona norte, zona norte volei, zona noire volei, jogar volei na zona norte, treino de volei, treinos de volei, aula de volei, aulas de volei, escola de volei, escola de volei zona norte, volei adulto, volei iniciante, volei intermediário, volei avançado, voleibol, escola de voleibol, São Paulo, Zona Norte São Paulo';
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">

<meta name="title" content="<?= htmlspecialchars($siteTitle) ?>">
<meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
<meta name="keywords" content="<?= htmlspecialchars($seoKeywords) ?>">
<meta name="author" content="MPG Academy">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= htmlspecialchars(rtrim($siteUrl, '/')) ?>">

<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($siteTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
<meta property="og:url" content="<?= htmlspecialchars(rtrim($siteUrl, '/')) ?>">
<meta property="og:image" content="<?= BASE_URL ?>/images/logo.png">
<meta property="og:site_name" content="MPG Academy">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($siteTitle) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($seoDescription) ?>">
<meta name="twitter:image" content="<?= BASE_URL ?>/images/logo.png">

<link rel="icon" href="<?= BASE_URL ?>/images/favicon.png" type="image/x-icon"/>

<?php
$version = time();
echo '<link rel="stylesheet" type="text/css" href="' . BASE_URL . '/styles/style.min.css?v' . $version . '">';
?>
