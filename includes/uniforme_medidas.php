<?php
/**
 * Tabelas de medidas do uniforme, renderizadas a partir de UNIFORME_MEDIDAS.
 *
 * Usado no modal do formulário de pedido e na área do aluno — as duas telas incluem ESTE
 * arquivo pra que as medidas nunca fiquem diferentes entre onde o aluno consulta e onde
 * ele escolhe. Editar a grade em config/uniformes.php atualiza os dois lugares.
 *
 * Variáveis de entrada (opcionais):
 *   $medidasGenero — 'masculino' | 'feminino' | 'infantil' | null (null = mostra todos)
 *   $medidasPeca   — 'camisa' | 'shorts' | 'regata' | null (null = mostra todas)
 *
 * Requer: config/uniformes.php já incluído.
 */

$__generos = isset($medidasGenero) && $medidasGenero
    ? [$medidasGenero]
    : UNIFORME_GENEROS;

$__pecaFiltro = isset($medidasPeca) && $medidasPeca ? $medidasPeca : null;

// A regata tem grade única (a mesma pra qualquer corte), então sai uma vez só no fim em vez
// de repetida embaixo de masculino e de feminino.
$__pecasPorCorte = array_values(array_filter(
    ['camisa', 'shorts'],
    fn($p) => $__pecaFiltro === null || $__pecaFiltro === $p
));

$__mostrarRegata = $__pecaFiltro === null || $__pecaFiltro === 'regata';

/** Uma tabela — usada tanto pelos cortes quanto pelo bloco da regata. */
$__renderTabela = function (array $__t, string $__classe, array $__conv = []) {
    if (!$__t) return;
    ?>
    <section class="uniMedidas__bloco uniMedidas__bloco--<?= $__classe ?>">
        <h4><?= htmlspecialchars($__t['label']) ?></h4>
        <div class="uniMedidas__scroll">
            <table>
                <thead>
                    <tr><?php foreach ($__t['colunas'] as $__c): ?><th><?= htmlspecialchars($__c) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach ($__t['linhas'] as $__linha): ?>
                    <tr>
                        <?php foreach ($__linha as $__i => $__v): ?>
                            <?php if ($__i === 0): ?><th><?= htmlspecialchars($__v) ?></th>
                            <?php else: ?><td><?= htmlspecialchars($__v) ?></td><?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        // Referência de apoio: a tabela acima é em centímetros, e muita gente não sabe
        // traduzir isso pro tamanho que costuma vestir. Vem depois, e não antes, porque
        // é aproximada — quem manda continua sendo a medida do fabricante.
        ?>
        <?php if ($__conv): ?>
        <details class="uniMedidas__conversao">
            <summary>Não sabe seu tamanho? Ver equivalência com a numeração tradicional</summary>

            <div class="uniMedidas__scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Tamanho do fabricante</th>
                            <th><?= htmlspecialchars($__conv['coluna']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($__conv['linhas'] as $__cl): ?>
                        <tr>
                            <th><?= htmlspecialchars($__cl[0]) ?></th>
                            <td><?= htmlspecialchars($__cl[1]) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="uniMedidas__conversaoAviso"><?= UNIFORME_CONVERSAO_AVISO ?></p>
        </details>
        <?php endif; ?>
    </section>
    <?php
};
?>
<div class="uniMedidas">
    <?php foreach ($__generos as $__g): ?>
        <?php
        // Cada corte mostra só as peças que ele realmente tem: a grade infantil é camiseta
        // e bermuda, e a regata não existe em infantil.
        $__doCorte = array_values(array_filter(
            $__pecasPorCorte,
            fn($p) => !empty(UNIFORME_MEDIDAS[$__g][$p])
        ));
        if (!$__doCorte) continue;
        ?>
        <div class="uniMedidas__linha uniMedidas__linha--<?= $__g ?>">
            <?php foreach ($__doCorte as $__p): ?>
                <?php $__renderTabela(uniformeTabelaMedidas($__g, $__p), $__g, uniformeTabelaConversao($__g, $__p)); ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($__mostrarRegata): ?>
        <div class="uniMedidas__linha uniMedidas__linha--regata">
            <?php $__renderTabela(UNIFORME_TABELA_REGATA, 'regata', UNIFORME_CONVERSAO_REGATA); ?>
            <p class="uniMedidas__nota">A regata tem grade única — a mesma tabela vale pra todo mundo.</p>
        </div>
    <?php endif; ?>
</div>
<p class="uniMedidas__aviso"><?= UNIFORME_AVISO_MEDIDAS ?></p>
