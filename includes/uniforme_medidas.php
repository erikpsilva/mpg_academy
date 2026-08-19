<?php
/**
 * Tabelas de medidas do uniforme, renderizadas a partir de UNIFORME_MEDIDAS.
 *
 * Usado no modal do formulário de pedido e na área do aluno — as duas telas incluem ESTE
 * arquivo pra que as medidas nunca fiquem diferentes entre onde o aluno consulta e onde
 * ele escolhe. Editar a grade em config/uniformes.php atualiza os dois lugares.
 *
 * Variáveis de entrada (opcionais):
 *   $medidasGenero — 'masculino' | 'feminino' | null (null = mostra os dois)
 *   $medidasPeca   — 'camisa' | 'shorts' | null (null = mostra as duas)
 *
 * Requer: config/uniformes.php já incluído.
 */

$__generos = isset($medidasGenero) && $medidasGenero
    ? [$medidasGenero]
    : UNIFORME_GENEROS;

$__pecas = isset($medidasPeca) && $medidasPeca
    ? [$medidasPeca]
    : UNIFORME_PECAS;
?>
<div class="uniMedidas">
    <?php foreach ($__generos as $__g): ?>
        <div class="uniMedidas__linha uniMedidas__linha--<?= $__g ?>">
        <?php foreach ($__pecas as $__p): $__t = uniformeTabelaMedidas($__g, $__p); if (!$__t) continue; ?>
        <section class="uniMedidas__bloco uniMedidas__bloco--<?= $__g ?>">
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
            $__conv = uniformeTabelaConversao($__g, $__p);
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
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
<p class="uniMedidas__aviso"><?= UNIFORME_AVISO_MEDIDAS ?></p>
