<?php
if (empty($_SESSION['aluno'])) { header('Location: ' . BASE_URL . '/cadastro'); exit; }
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/uniformes.php';
$pdo = getDbConnection(); $aluno = $_SESSION['aluno'];
$st = $pdo->prepare("SELECT id FROM pedidos_uniforme WHERE aluno_id=? AND origem_cobranca='matricula' LIMIT 1");
$st->execute([(int)$aluno['id']]); $pedidoExistente = (bool)$st->fetchColumn();
$st = $pdo->prepare("SELECT sexo FROM alunos WHERE id=?"); $st->execute([(int)$aluno['id']]);
$sexo = (string)$st->fetchColumn(); $generoPadrao = in_array($sexo, UNIFORME_GENEROS, true) ? $sexo : 'masculino';
$turmas = uniformeTurmasDoAluno($pdo,(int)$aluno['id']);
?>
<!DOCTYPE html><html lang="pt-BR"><head><title>MPG Academy | Uniforme da matrícula</title><?php include ROOT.'/includes/assets.php'; ?></head><body>
<?php $isStudentArea=true; include ROOT.'/includes/header/header.php'; ?>
<main class="uniformOrder enrollmentUniform"><div class="container">
<?php if ($pedidoExistente): ?>
<section class="enrollmentUniform__done"><span>Pedido recebido</span><h1>Seu uniforme já foi escolhido</h1><p>Assim que sua turma for definida, a equipe entrará em contato para escolher o número da camiseta.</p><a class="uniformOrder__submit" href="<?= BASE_URL ?>/areadoaluno">Ir para a área do aluno</a></section>
<?php else: ?>
<header class="uniformOrder__head enrollmentUniform__head"><span>Última etapa do cadastro</span><h1>Monte o seu uniforme</h1><p>O uniforme completo é um item <strong>incluído na taxa de matrícula</strong>. Escolha o corte, o modelo e os tamanhos que combinam com você.</p></header>
<form class="uniformOrder__form" id="enrollmentUniformForm" novalidate>
<section class="uniformOrder__block"><h2><span>1</span> Seus dados</h2><div class="uniformOrder__student">
<?php if (!empty($aluno['foto'])): ?><img src="<?= BASE_URL ?>/<?= htmlspecialchars($aluno['foto']) ?>" alt="<?= htmlspecialchars($aluno['nome']) ?>"><?php else: ?><span class="uniformOrder__avatar"><i class="icon-user"></i></span><?php endif; ?>
<div><strong><?= htmlspecialchars($aluno['nome']) ?></strong><small><?= htmlspecialchars($aluno['email']) ?></small></div></div>
<p class="enrollmentUniform__included"><strong>Uniforme completo incluído na matrícula</strong><small>Camisa e calção/bermuda. Nenhum pagamento adicional será solicitado nesta etapa.</small></p></section>

<section class="uniformOrder__block uniformOrder__measureCallout"><div class="uniformOrder__measureCalloutText"><span>Antes de escolher</span><h2>Tabela de medidas</h2><p>Veja juntas as grades masculina, feminina e infantil de camisa e calção/bermuda.</p></div><button type="button" class="uniformOrder__measureCalloutButton" id="enrollmentAllMeasures">Ver todas as medidas</button></section>

<section class="uniformOrder__block"><h2><span>2</span> Escolha o corte e o modelo</h2><div class="uniformOrder__models enrollmentUniform__models">
<?php $modelos=[
['masculino','padrao','uniformeMasculinoPadrao.jpg','Masculino','Modelo padrão'],['masculino','libero','uniformeMasculinoLibero.jpg','Masculino','Modelo líbero'],
['feminino','padrao','uniformeFemininoPadrao.jpg','Feminino','Modelo padrão'],['feminino','libero','uniformeFemininoLibero.jpg','Feminino','Modelo líbero'],
['infantil','padrao','uniformeMasculinoPadrao.jpg','Infantil','Modelo padrão'],['infantil','libero','uniformeMasculinoLibero.jpg','Infantil','Modelo líbero']];
foreach($modelos as $m): $checked=$m[0]===$generoPadrao && $m[1]==='padrao'; ?>
<label class="uniformOrderModel"><input type="radio" name="modelo_uniforme" value="<?= $m[0].'|'.$m[1] ?>" data-genero="<?= $m[0] ?>" data-modelo="<?= $m[1] ?>" <?= $checked?'checked':'' ?>><span class="uniformOrderModel__box"><span class="uniformOrderModel__tag"><?= $m[3] ?></span><img src="<?= BASE_URL ?>/images/uniformes/<?= $m[2] ?>" alt="<?= $m[3].' '.$m[4] ?>"><span class="uniformOrderModel__name"><?= $m[4] ?></span><?php if($m[0]==='infantil'): ?><span class="uniformOrderModel__hint">Modelagem infantil (2 a 14 anos)</span><?php endif; ?></span></label>
<?php endforeach; ?></div><input type="hidden" name="genero" id="enrollmentUniformGender"><input type="hidden" name="modelo" id="enrollmentUniformModel"></section>

<section class="uniformOrder__block"><h2><span>3</span> Personalização e tamanhos</h2>
<label class="uniformOrder__field"><span>Nome na camiseta</span><input type="text" name="nome_camisa" id="enrollmentUniformName" maxlength="<?= UNIFORME_NOME_MAX ?>" placeholder="Ex.: FELIPE" required><small>Será estampado em caixa alta.</small></label>
<div class="uniformOrder__field"><span>Tamanho da camisa</span><div class="uniformOrder__sizes" id="enrollmentUniformShirtSizes"></div><input type="hidden" name="tamanho_camisa" id="enrollmentUniformShirt"><small><button type="button" class="uniformOrder__measureLink" data-piece="camisa">Ver medidas da camisa</button></small></div>
<div class="uniformOrder__field"><span>Tamanho do calção/bermuda</span><div class="uniformOrder__sizes" id="enrollmentUniformShortsSizes"></div><input type="hidden" name="tamanho_shorts" id="enrollmentUniformShorts"><small><button type="button" class="uniformOrder__measureLink" data-piece="shorts">Ver medidas</button></small></div>
<?php if($turmas): ?><div class="uniformOrder__field"><span>Número da camiseta</span><button type="button" class="uniformOrder__numberPick" id="enrollmentNumberPick"><em id="enrollmentNumberLabel">Escolher número</em><i class="icon-go"></i></button><input type="hidden" name="numero" id="enrollmentUniformNumber"><input type="hidden" name="turma_id" id="enrollmentUniformClass" value="<?= (int)$turmas[0]['id'] ?>"><small>Números disponíveis na turma <?= htmlspecialchars($turmas[0]['nome']) ?>.</small></div><?php else: ?><p class="enrollmentUniform__numberInfo"><strong>Número da camiseta</strong><span>Será escolhido quando sua turma for vinculada.</span></p><?php endif; ?></section>

<section class="uniformOrder__block uniformOrder__block--summary"><h2><span>4</span> Confira sua escolha</h2><dl class="uniformOrder__summary"><div><dt>Corte</dt><dd id="summaryGender">—</dd></div><div><dt>Modelo</dt><dd id="summaryModel">—</dd></div><div><dt>Nome</dt><dd id="summaryName">—</dd></div><div><dt>Tam. camisa</dt><dd id="summaryShirt">—</dd></div><div><dt>Tam. calção</dt><dd id="summaryShorts">—</dd></div><div class="enrollmentUniform__summaryIncluded"><dt>Pagamento</dt><dd>Incluído na matrícula</dd></div></dl><p class="uniformOrder__error" id="enrollmentUniformError" role="alert" aria-live="assertive"></p><button type="submit" class="uniformOrder__submit" id="enrollmentUniformSubmit">Confirmar meu uniforme</button></section>
</form><?php endif; ?></div></main>
<div class="uniformMeasures" id="enrollmentMeasures" aria-hidden="true" role="dialog" aria-modal="true"><button type="button" class="uniformMeasures__backdrop js-measures-close"></button><div class="uniformMeasures__dialog"><div class="uniformMeasures__head"><div><span>Uniformes MPG Academy</span><h2 id="enrollmentMeasuresTitle">Tabela de medidas</h2><p>Compare com uma peça que você já usa.</p></div><button type="button" class="uniformMeasures__close js-measures-close">&times;</button></div><div class="uniformMeasures__body" id="enrollmentMeasuresBody"></div></div></div>
<div class="uniformNumbers" id="enrollmentNumbers" aria-hidden="true" role="dialog" aria-modal="true"><button type="button" class="uniformNumbers__backdrop js-enrollment-numbers-close"></button><div class="uniformNumbers__dialog"><div class="uniformNumbers__head"><div><h2>Escolha seu número</h2><p>Números disponíveis na sua turma.</p></div><button type="button" class="uniformNumbers__close js-enrollment-numbers-close">&times;</button></div><div class="uniformNumbers__grid" id="enrollmentNumbersGrid"></div></div></div>
<?php include ROOT.'/includes/footer/footer.php'; include ROOT.'/includes/scripts.php'; ?>
<script>window.ENROLLMENT_UNIFORM_MEASURES=<?= json_encode(UNIFORME_MEDIDAS,JSON_UNESCAPED_UNICODE) ?>;window.ENROLLMENT_UNIFORM_LABELS=<?= json_encode(['genero'=>UNIFORME_GENERO_LABEL,'modelo'=>UNIFORME_MODELO_LABEL],JSON_UNESCAPED_UNICODE) ?>;window.BASE_URL=<?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/pages/uniformematricula/uniformematricula.js?v=<?= time() ?>"></script></body></html>
