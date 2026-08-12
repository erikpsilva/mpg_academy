<?php
/**
 * Aviso da janela de inscrição do Bate Bola.
 *
 * Usado na home pública (/batebola) e na área do participante (/batebolainicio) — as duas
 * incluem ESTE arquivo pra que a regra apareça igual nos dois lugares e nunca fique
 * desencontrada. O estado (aberta/fechada) vem de batebolaEstadoJanela(), que deriva da
 * mesma função que o back-end usa pra aceitar ou barrar o pagamento.
 *
 * Requer: config/batebola.php já incluído.
 */

$__bbJanela = batebolaEstadoJanela();

$__diasSemana = [
    1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira',
    4 => 'quinta-feira',  5 => 'sexta-feira', 6 => 'sábado', 7 => 'domingo',
];

$__mudancaDia  = $__diasSemana[(int) $__bbJanela['proxima_mudanca']->format('N')];
$__mudancaHora = $__bbJanela['proxima_mudanca']->format('H\hi');
if (substr($__mudancaHora, -2) === '00') {
    $__mudancaHora = $__bbJanela['proxima_mudanca']->format('H') . 'h';
}
?>
<div class="bbJanela <?= $__bbJanela['aberta'] ? 'bbJanela--aberta' : 'bbJanela--fechada' ?>">
    <span class="bbJanela__status">
        <?= $__bbJanela['aberta'] ? 'Lista aberta agora' : 'Lista fechada agora' ?>
    </span>

    <div class="bbJanela__texto">
        <strong>
            <?php if ($__bbJanela['aberta']): ?>
                Dá pra garantir sua vaga até <?= $__mudancaDia ?>, <?= $__mudancaHora ?>.
            <?php else: ?>
                A lista reabre <?= $__mudancaDia ?>, às <?= $__mudancaHora ?>.
            <?php endif; ?>
        </strong>
        <span>
            A lista abre toda <b>segunda-feira às 06h</b> e fecha na <b>sexta-feira às 23h59</b>.
            O pagamento precisa ser feito dentro dessa janela pra valer a vaga no domingo.
        </span>
    </div>
</div>
