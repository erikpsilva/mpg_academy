<?php if(!$times): ?><p class="bbEspecial__notice">Os times ainda não foram sorteados.</p><?php else: ?>
<div class="bbEspecial__teams"><?php foreach($times as $time): ?><article class="bbEspecial__team bbEspecial__team--<?= strtolower($time['cor']) ?>"><h3>Time <?= especialHtml($time['cor']) ?></h3><ol><?php foreach($time['jogadores'] as $j): ?><li><?= especialHtml($j['nome']) ?></li><?php endforeach; ?></ol></article><?php endforeach; ?></div>
<?php endif; ?>
