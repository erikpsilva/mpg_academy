<?php
require_once ROOT.'/config/batebola_checkout.php';
$eventosHome=bbOpcoes($pdo,(int)$_SESSION['jogador']['id']);
$_SESSION['bb_checkout_csrf']=$_SESSION['bb_checkout_csrf']??bin2hex(random_bytes(24));
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/batebola-especial.css?v=3">
<section class="bbEspecial bbHomeEvents" aria-labelledby="eventosTitulo">
<header><span class="bbEspecial__eyebrow">AGENDA DA QUADRA</span><h2 id="eventosTitulo">Próximos bate-bolas</h2><p>Confira os encontros e a situação da sua vaga. Cada evento tem sua própria inscrição e seus times.</p></header>
<div class="bbEspecial__cards">
<?php foreach($eventosHome as $o): ?>
<article class="bbEspecial__card"><span class="bbEspecial__badge"><?= $o['tipo']==='domingo'?'Domingo tradicional':'Bate-bola especial' ?></span><h3><?= especialHtml($o['titulo']) ?></h3><p><?= especialHtml($o['horario']) ?><br><?= especialHtml(BATEBOLA_LOCAL_NOME) ?></p><strong class="bbEspecial__price">R$ <?= number_format((float)$o['valor'],2,',','.') ?></strong>
<p><?php if($o['inscricao']==='pago'): ?>✓ Sua vaga está confirmada<?php elseif($o['inscricao']==='pendente'): ?>Pagamento individual pendente<?php elseif(!$o['aberto']): ?>Inscrições fechadas<?php elseif(!$o['livres']): ?>Vagas esgotadas ou reservadas<?php else: ?><?= (int)$o['livres'] ?> vagas disponíveis<?php endif; ?></p>
<?php if($o['tipo']==='domingo'): ?><small>Inscrições de segunda, às 06h, até sábado, às 18h.</small><?php elseif($o['inscricao']==='pago'): ?><a href="<?= BASE_URL ?>/batebolaespecial?id=<?= (int)$o['especial_id'] ?>&legado=1">Ver confirmação e times</a><?php endif; ?>
<?php if($o['inscricao']==='pendente'): ?><a href="<?= BASE_URL ?>/<?= $o['tipo']==='domingo'?'batebolapagamento?legado=1':'batebolaespecial?id='.(int)$o['especial_id'].'&legado=1' ?>">Retomar PIX individual</a><?php endif; ?>
</article><?php endforeach; ?></div>
<section class="bbEspecial__panel bbHomeEvents__action"><div><h3>Vamos jogar?</h3><p>Com um encontro disponível, você segue direto ao pagamento. Com mais de um, escolha em quais quer jogar e pague tudo em um único PIX.</p></div><form action="<?= BASE_URL ?>/batebolaselecao" method="post"><input type="hidden" name="csrf" value="<?= especialHtml($_SESSION['bb_checkout_csrf']) ?>"><input type="hidden" name="acao" value="iniciar"><button class="bbEspecial__button" type="submit">Garantir vaga</button></form></section>
</section>
