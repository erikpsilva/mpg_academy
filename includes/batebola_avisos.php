<?php
/**
 * Avisos do Bate Bola: a edição especial com hora extra e a mudança de horário.
 *
 * Mesma ideia do includes/batebola_janela.php — vive num arquivo só, incluído pela home
 * pública (/batebola) e pela área do participante (/batebolainicio), pra que as duas
 * telas contem a mesma história.
 *
 * Nenhum dos dois precisa ser desligado à mão:
 *   - o da edição especial só aparece enquanto 06/09 for o próximo domingo;
 *   - o do horário some quando 13/09 virar o próximo domingo.
 *
 * Requer: config/batebola.php incluído e $dataEvento definido.
 */

$__especial = batebolaEhEspecial($dataEvento);
$__horario  = batebolaAvisoHorarioNovo($dataEvento);

if (!$__especial && !$__horario) return;

$__fmtData = fn (string $d): string => (new DateTime($d))->format('d/m');
?>

<?php if ($__especial): ?>
<div class="bbAviso bbAviso--especial">
    <span class="bbAviso__icone" aria-hidden="true">🏐</span>
    <div class="bbAviso__texto">
        <strong>Domingo <?= $__fmtData(BATEBOLA_ESPECIAL_DATA) ?> é especial: uma hora a mais de jogo</strong>
        <span>
            Em vez de terminar às 13h, esse encontro vai <b>das <?= batebolaHorarioTexto(BATEBOLA_ESPECIAL_DATA) ?></b>.
            Por causa da hora extra, o PIX desse domingo é de
            <b>R$ <?= number_format(BATEBOLA_ESPECIAL_VALOR, 2, ',', '.') ?></b> em vez dos R$ 17,00 de sempre.
            A partir de <?= $__fmtData(BATEBOLA_HORARIO_NOVO_DESDE) ?> o valor volta a ser <b>R$ 17,00</b>.
        </span>
    </div>
</div>
<?php endif; ?>

<?php if ($__horario): ?>
<div class="bbAviso bbAviso--horario">
    <span class="bbAviso__icone" aria-hidden="true">⏰</span>
    <div class="bbAviso__texto">
        <strong>Horário novo a partir de <?= $__fmtData(BATEBOLA_HORARIO_NOVO_DESDE) ?></strong>
        <span>
            O Bate Bola passa a ser das <b><?= batebolaHorarioTexto(BATEBOLA_HORARIO_NOVO_DESDE) ?></b>,
            e segue assim nos domingos seguintes.
        </span>
    </div>
</div>
<?php endif; ?>
