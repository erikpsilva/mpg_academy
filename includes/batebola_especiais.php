<?php
require_once ROOT.'/config/batebola_especial.php';
if(especialDisponivel($pdo)) {
    $specialQuery=$pdo->prepare("SELECT e.*, (SELECT COUNT(*) FROM batebola_especial_inscricoes i WHERE i.evento_id=e.id AND i.status='pago') confirmados FROM batebola_especiais e WHERE e.fim>? AND e.status!='encerrado' ORDER BY e.inicio");
    $specialQuery->execute([especialAgora()]); $specialEvents=$specialQuery->fetchAll();
    if($specialEvents): ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/batebola-especial.css?v=1">
<section class="bbEspecial bbEspecial__public"><span class="bbEspecial__eyebrow">MAIS DIAS PARA JOGAR</span><h2>Bate-bola especial</h2><p>Encontros extras, com inscrição própria e independente do bate-bola de domingo.</p><div class="bbEspecial__cards">
<?php foreach($specialEvents as $special): ?><article class="bbEspecial__card"><span class="bbEspecial__badge">Bate-bola especial</span><h3><?= especialHtml($special['titulo']) ?></h3><p><?= especialHtml(especialData($special)) ?><br><?= especialHtml(BATEBOLA_LOCAL_NOME) ?></p><strong class="bbEspecial__price">R$ <?= number_format((float)$special['valor'],2,',','.') ?></strong><p><?= (int)$special['confirmados'] ?>/<?= (int)$special['vagas'] ?> vagas confirmadas</p><a class="bbEspecial__button" href="<?= BASE_URL ?>/batebolaespecial?id=<?= (int)$special['id'] ?>"><?= especialAberto($special)?'Quero garantir minha vaga':'Ver evento e times' ?></a></article><?php endforeach; ?>
</div></section>
<?php endif; } ?>
