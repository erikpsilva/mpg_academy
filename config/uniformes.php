<?php

/**
 * Regras e helpers dos pedidos de uniforme.
 *
 * A numeração da camisa (1 a 99) é controlada por "balde": cada combinação de
 * TURMA + GÊNERO do uniforme tem sua própria lista de números. Ou seja, alunos de
 * turmas diferentes podem repetir número, e um aluno e uma aluna da mesma turma
 * também podem — só não pode repetir dentro do mesmo balde.
 *
 * O número do próprio aluno pertence a ele: ele pode reusar o mesmo número em
 * quantos pedidos quiser (ex.: camisa padrão + camisa de líbero).
 */

/**
 * Interruptor de visibilidade do módulo de uniformes na ÁREA DO ALUNO.
 *
 * false = some da tela do aluno (vitrine, tabela de medidas, botão de pedido e
 * acompanhamento dos pedidos) e as rotas /pedidouniforme e /pagamentouniforme redirecionam
 * de volta. Nada é apagado: pedidos já feitos continuam no banco e o admin segue enxergando
 * tudo. Pra reativar, basta voltar pra true.
 */
const UNIFORMES_VISIVEL_ALUNO = true;

const UNIFORME_VALOR_PADRAO     = 115.00;
const UNIFORME_NUMERO_MIN       = 1;
const UNIFORME_NUMERO_MAX       = 99;
const UNIFORME_NOME_MAX         = 14;   // caracteres que cabem nas costas da camisa
const UNIFORME_RESERVA_MINUTOS  = 30;   // quanto tempo o número fica preso aguardando pagamento

/**
 * Máximo de parcelas oferecido no cartão. O valor de cada parcela (com ou sem juros) é
 * calculado pelo próprio Brick do Mercado Pago a partir do preço à vista — nunca por nós.
 * Enviamos ao criar o pagamento sempre o valor À VISTA (`transaction_amount`) + o número de
 * parcelas escolhido; se aquela quantidade de parcelas tiver juros (conforme configurado na
 * conta MP), quem paga é o cliente na fatura do cartão — a MPG Academy recebe o valor cheio
 * de qualquer forma. O Brick só oferece as opções que o emissor do cartão permitir pro valor.
 */
const UNIFORME_PARCELAS_MAX = 12;

const UNIFORME_GENEROS = ['masculino', 'feminino'];
const UNIFORME_MODELOS = ['padrao', 'libero'];

/** As duas peças que o aluno escolhe tamanho separadamente. */
const UNIFORME_PECAS = ['camisa', 'shorts'];

/** Aviso do fabricante, exibido junto de toda tabela de medidas. */
const UNIFORME_AVISO_MEDIDAS = 'As medidas podem variar cerca de 3% por conta da costura.';

/**
 * Tabelas de medidas do fabricante — a fonte da verdade dos tamanhos.
 *
 * Camisa e shorts têm grades DIFERENTES entre si e entre os gêneros (a camisa masculina vai
 * até XG3, a feminina só até XG; o calção masculino mede cós/perna/altura, a bermuda
 * feminina mede largura/cavalo/elástico). Por isso o aluno escolhe o gênero primeiro e só
 * então vê a grade certa de cada peça — e por isso o tamanho é gravado por peça no pedido.
 *
 * Os tamanhos disponíveis saem da primeira coluna de `linhas`, então basta editar a tabela
 * aqui pra mudar a grade em todo o sistema (formulário, área do aluno e admin).
 */
const UNIFORME_MEDIDAS = [
    'masculino' => [
        'camisa' => [
            'label'   => 'Camisa masculina',
            'colunas' => ['Tamanho', 'Altura', 'Largura'],
            'linhas'  => [
                ['PP',  '65 cm', '43 cm'],
                ['P',   '66 cm', '45 cm'],
                ['M',   '68 cm', '48 cm'],
                ['G',   '72 cm', '52 cm'],
                ['GG',  '73 cm', '54 cm'],
                ['XG',  '75 cm', '59 cm'],
                ['XG1', '76 cm', '62 cm'],
                ['XG2', '78 cm', '64 cm'],
                ['XG3', '80 cm', '65 cm'],
            ],
        ],
        'shorts' => [
            'label'   => 'Calção masculino',
            'colunas' => ['Tamanho', 'Cós', 'Larg. da perna', 'Altura'],
            'linhas'  => [
                ['PP',  '31~42 cm', '35 cm', '44 cm'],
                ['P',   '31~43 cm', '36 cm', '47 cm'],
                ['M',   '34~47 cm', '37 cm', '48 cm'],
                ['G',   '36~48 cm', '38 cm', '50 cm'],
                ['GG',  '36~48 cm', '39 cm', '52 cm'],
                ['EXG', '38~50 cm', '40 cm', '54 cm'],
                ['G1',  '38~50 cm', '41 cm', '56 cm'],
            ],
        ],
    ],
    'feminino' => [
        'camisa' => [
            'label'   => 'Camisa feminina',
            'colunas' => ['Tamanho', 'Altura', 'Largura'],
            'linhas'  => [
                ['PP', '52 cm', '37 cm'],
                ['P',  '54 cm', '39 cm'],
                ['M',  '56 cm', '42 cm'],
                ['G',  '58 cm', '44 cm'],
                ['GG', '61 cm', '49 cm'],
                ['XG', '65 cm', '54 cm'],
            ],
        ],
        'shorts' => [
            'label'   => 'Bermuda feminina',
            'colunas' => ['Tamanho', 'Largura', 'Cavalo', 'Elástico'],
            'linhas'  => [
                ['P',  '44 cm', '25 cm',   '62 cm'],
                ['M',  '48 cm', '26,5 cm', '65 cm'],
                ['G',  '49 cm', '27 cm',   '69 cm'],
                ['GG', '51 cm', '29 cm',   '72 cm'],
            ],
        ],
    ],
];

/** Fluxo de produção do pedido, na ordem em que o admin avança. */
const UNIFORME_STATUS_FLUXO = ['pendente', 'enviado', 'pronto', 'finalizado', 'entregue'];

const UNIFORME_STATUS_LABEL = [
    'pendente'   => 'Pendente',
    'enviado'    => 'Enviado para confecção',
    'pronto'     => 'Pronto',
    'finalizado' => 'Finalizado',
    'entregue'   => 'Entregue',
];

const UNIFORME_MODELO_LABEL = [
    'padrao' => 'Modelo padrão',
    'libero' => 'Modelo líbero',
];

/** Valor do conjunto (camisa + shorts + meião). Editável em admin/configuracoes. */
function uniformeValor(PDO $pdo): float
{
    $st = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'valor_uniforme'");
    $st->execute();
    $row = $st->fetch();

    return $row ? (float) $row['valor'] : UNIFORME_VALOR_PADRAO;
}

/** A tabela de medidas de uma peça (camisa/shorts) num gênero. */
function uniformeTabelaMedidas(string $genero, string $peca): array
{
    return UNIFORME_MEDIDAS[$genero][$peca] ?? UNIFORME_MEDIDAS['masculino'][$peca] ?? [];
}

/**
 * Tamanhos disponíveis de uma peça, derivados da própria tabela de medidas — nunca são
 * escritos à mão em outro lugar, pra grade e medidas jamais divergirem.
 */
function uniformeTamanhos(string $genero, string $peca = 'camisa'): array
{
    $tabela = uniformeTabelaMedidas($genero, $peca);

    return array_map(fn($linha) => $linha[0], $tabela['linhas'] ?? []);
}

/** Todas as tabelas de um gênero (camisa + shorts), pra montar a tela de medidas. */
function uniformeMedidasDoGenero(string $genero): array
{
    return UNIFORME_MEDIDAS[$genero] ?? [];
}

/** Nome da peça de baixo muda por gênero: calção (masc) x bermuda (fem). */
function uniformeLabelPeca(string $genero, string $peca): string
{
    return uniformeTabelaMedidas($genero, $peca)['label'] ?? ucfirst($peca);
}

/**
 * Turmas ativas do aluno. A numeração é por turma, então o pedido sempre guarda em
 * qual turma ele foi feito. Hoje ninguém está em mais de uma turma ao mesmo tempo,
 * mas o modelo permite — nesse caso o formulário pede pro aluno escolher.
 */
function uniformeTurmasDoAluno(PDO $pdo, int $alunoId): array
{
    $st = $pdo->prepare("
        SELECT t.id, t.nome
        FROM turma_alunos ta
        JOIN turmas t ON t.id = ta.turma_id
        WHERE ta.aluno_id = ? AND ta.status = 'ativo' AND t.status = 'ativa'
        ORDER BY t.nome ASC
    ");
    $st->execute([$alunoId]);

    return $st->fetchAll();
}

/**
 * Marca como expirados os pedidos que reservaram um número mas não tiveram o pagamento
 * confirmado dentro da janela — devolve os números pro pool. Chamado antes de qualquer
 * leitura/escrita de disponibilidade, então não depende de cron.
 */
function uniformeExpirarReservas(PDO $pdo): void
{
    $pdo->query("
        UPDATE pedidos_uniforme
        SET status_pagamento = 'expirado'
        WHERE status_pagamento = 'aguardando'
          AND reserva_expira_em IS NOT NULL
          AND reserva_expira_em < NOW()
    ");
}

/**
 * Situação dos 99 números pro balde (turma + gênero) informado.
 *
 * @return array{ocupados: int[], meus: int[]} `ocupados` = travados por OUTROS alunos;
 *         `meus` = números que já são deste aluno (ele pode reusar à vontade).
 */
function uniformeNumerosDoBalde(PDO $pdo, int $turmaId, string $genero, int $alunoId): array
{
    uniformeExpirarReservas($pdo);

    $st = $pdo->prepare("
        SELECT DISTINCT numero, aluno_id
        FROM pedidos_uniforme
        WHERE turma_id = ?
          AND genero = ?
          AND (
                status_pagamento = 'pago'
                OR (status_pagamento = 'aguardando' AND reserva_expira_em > NOW())
              )
    ");
    $st->execute([$turmaId, $genero]);

    $ocupados = [];
    $meus     = [];

    foreach ($st->fetchAll() as $row) {
        $numero = (int) $row['numero'];
        if ((int) $row['aluno_id'] === $alunoId) {
            $meus[] = $numero;
        } else {
            $ocupados[] = $numero;
        }
    }

    // Um número que já é do aluno nunca deve aparecer como bloqueado pra ele.
    $ocupados = array_values(array_diff(array_unique($ocupados), $meus));
    sort($ocupados);

    $meus = array_values(array_unique($meus));
    sort($meus);

    return ['ocupados' => $ocupados, 'meus' => $meus];
}

/** Se o aluno pode usar esse número no balde (livre ou já pertencente a ele). */
function uniformeNumeroDisponivel(PDO $pdo, int $turmaId, string $genero, int $alunoId, int $numero): bool
{
    if ($numero < UNIFORME_NUMERO_MIN || $numero > UNIFORME_NUMERO_MAX) {
        return false;
    }

    $balde = uniformeNumerosDoBalde($pdo, $turmaId, $genero, $alunoId);

    return !in_array($numero, $balde['ocupados'], true);
}

/**
 * Normaliza o nome que vai estampado na camisa: só letras (com acento), espaço, ponto e
 * hífen, em caixa alta, colapsando espaços repetidos.
 */
function uniformeNormalizarNome(string $nome): string
{
    $nome = preg_replace('/\s+/u', ' ', trim($nome));
    $nome = preg_replace('/[^\p{L}0-9 .\-]/u', '', $nome);

    return mb_strtoupper(mb_substr($nome, 0, UNIFORME_NOME_MAX), 'UTF-8');
}

/**
 * Confirma o pagamento de um pedido de uniforme. Chamado pelo webhook do Mercado Pago
 * (via metadata.pedido_uniforme_id) e pelo retorno síncrono do pagamento com cartão —
 * mesmo padrão de mpMarcarMensalidadePaga() e batebolaConfirmarInscricao().
 *
 * Confirmar o pagamento é o que torna o número definitivamente do aluno: some a
 * `reserva_expira_em` e o pedido entra na fila do admin com status 'pendente'.
 */
function uniformeConfirmarPedido(PDO $pdo, int $pedidoId, string $mpPaymentId, ?array $payment = null): bool
{
    $st = $pdo->prepare("
        SELECT id, aluno_id, turma_id, genero, numero, valor, status_pagamento
        FROM pedidos_uniforme WHERE id = ?
    ");
    $st->execute([$pedidoId]);
    $pedido = $st->fetch();

    if (!$pedido || $pedido['status_pagamento'] === 'pago') {
        return false;
    }

    // O PIX confirma por webhook, que pode chegar depois da reserva ter expirado — e nesse
    // meio-tempo outro aluno pode ter pago o mesmo número. O dinheiro entrou, então o pedido
    // é confirmado de qualquer jeito; o conflito fica sinalizado pro admin resolver.
    $stConflito = $pdo->prepare("
        SELECT COUNT(*) FROM pedidos_uniforme
        WHERE turma_id = ? AND genero = ? AND numero = ?
          AND aluno_id != ? AND status_pagamento = 'pago' AND id != ?
    ");
    $stConflito->execute([
        (int) $pedido['turma_id'], $pedido['genero'], (int) $pedido['numero'],
        (int) $pedido['aluno_id'], $pedidoId,
    ]);
    $conflito = ((int) $stConflito->fetchColumn() > 0) ? 1 : 0;

    // Guarda como o aluno pagou (payment_type_id do MP: bank_transfer/credit_card/...) —
    // é o que alimenta a coluna "Forma" do Controle Financeiro.
    $metodo = $payment['payment_type_id'] ?? null;

    $pdo->prepare("
        UPDATE pedidos_uniforme
        SET status_pagamento  = 'pago',
            mp_payment_id     = ?,
            mp_payment_method = ?,
            pago_em           = NOW(),
            reserva_expira_em = NULL,
            conflito_numero   = ?
        WHERE id = ? AND status_pagamento != 'pago'
    ")->execute([$mpPaymentId, $metodo, $conflito, $pedidoId]);

    // Uniforme NÃO entra em lancamentos_financeiros: é controle à parte, em
    // admin/pagamentos-uniformes, pra não misturar com a receita de mensalidades no
    // Controle Financeiro. A taxa do MP fica guardada no próprio pedido — é o que a
    // página nova usa pra mostrar bruto x líquido.
    try {
        $valorBruto = (float) $pedido['valor'];

        $taxa = null;
        if ($payment !== null && function_exists('mpExtrairTaxaELiquido')) {
            $taxa = mpExtrairTaxaELiquido($payment, $valorBruto)['taxa'] ?? null;
        }

        $pdo->prepare("
            UPDATE pedidos_uniforme SET mp_taxa_valor = ?, mp_valor_liquido = ? WHERE id = ?
        ")->execute([$taxa, $taxa !== null ? round($valorBruto - $taxa, 2) : null, $pedidoId]);
    } catch (Throwable $e) {
        // Falha ao registrar a taxa nunca deve desfazer a confirmação do pedido.
        error_log('[uniforme-taxa] ' . $e->getMessage());
    }

    return true;
}

/**
 * Cria um pedido de uniforme já PAGO, direto pelo admin — para o aluno que teve
 * dificuldade em usar o formulário sozinho. O admin cobra por fora (link externo do
 * Mercado Pago, PIX manual, dinheiro etc.) e só registra o pedido aqui depois de já ter
 * recebido; por isso nasce sem passar pela reserva de 30 minutos nem pelo webhook.
 *
 * Mesma trava de disponibilidade do fluxo do aluno (SELECT ... FOR UPDATE no balde
 * turma+gênero) — o número continua não podendo repetir dentro do mesmo balde.
 *
 * @return array{success:bool, message?:string, pedido_id?:int}
 */
function uniformeCriarPedidoManual(
    PDO $pdo,
    int $alunoId,
    int $turmaId,
    string $genero,
    string $modelo,
    string $nomeCamisa,
    int $numero,
    string $tamanhoCamisa,
    string $tamanhoShorts,
    float $valor,
    int $criadoPorUsuarioId
): array {
    if (!in_array($genero, UNIFORME_GENEROS, true) || !in_array($modelo, UNIFORME_MODELOS, true)) {
        return ['success' => false, 'message' => 'Modelo de uniforme inválido.'];
    }

    if ($numero < UNIFORME_NUMERO_MIN || $numero > UNIFORME_NUMERO_MAX) {
        return ['success' => false, 'message' => 'Escolha um número de 1 a 99.'];
    }

    // Cada peça tem sua própria grade — a do shorts não bate com a da camisa.
    if (!in_array($tamanhoCamisa, uniformeTamanhos($genero, 'camisa'), true)) {
        return ['success' => false, 'message' => 'Tamanho da camisa inválido para esse uniforme.'];
    }

    if (!in_array($tamanhoShorts, uniformeTamanhos($genero, 'shorts'), true)) {
        // "a bermuda" (fem) x "o calção" (masc) — o artigo muda com a peça.
        $peca = $genero === 'feminino' ? 'da bermuda' : 'do calção';
        return ['success' => false, 'message' => 'Tamanho ' . $peca . ' inválido para esse uniforme.'];
    }

    try {
        $pdo->beginTransaction();

        uniformeExpirarReservas($pdo);

        $stLock = $pdo->prepare("
            SELECT aluno_id
            FROM pedidos_uniforme
            WHERE turma_id = ?
              AND genero = ?
              AND numero = ?
              AND (
                    status_pagamento = 'pago'
                    OR (status_pagamento = 'aguardando' AND reserva_expira_em > NOW())
                  )
            FOR UPDATE
        ");
        $stLock->execute([$turmaId, $genero, $numero]);
        $donos = $stLock->fetchAll(PDO::FETCH_COLUMN);

        foreach ($donos as $donoId) {
            if ((int) $donoId !== $alunoId) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'O número ' . $numero . ' já está em uso por outro aluno dessa turma/gênero.',
                ];
            }
        }

        $pdo->prepare("
            INSERT INTO pedidos_uniforme
                (aluno_id, turma_id, genero, modelo, nome_camisa, numero,
                 tamanho_camisa, tamanho_shorts, valor,
                 status_pagamento, status_pedido, pago_em, criado_por_usuario_id, visto_admin)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pago', 'pendente', NOW(), ?, 1)
        ")->execute([$alunoId, $turmaId, $genero, $modelo, $nomeCamisa, $numero,
                     $tamanhoCamisa, $tamanhoShorts, $valor, $criadoPorUsuarioId]);

        $pedidoId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[uniforme-pedido-manual] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao criar o pedido. Tente novamente.'];
    }

    // Sem lançamento no caixa: uniforme é controle à parte, em admin/pagamentos-uniformes
    // (mesma regra do fluxo pago pelo site — ver uniformeConfirmarPedido()). O pedido já
    // nasce com status_pagamento = 'pago' e criado_por_usuario_id preenchido, que é o que
    // identifica o pagamento externo naquela página.

    return ['success' => true, 'pedido_id' => $pedidoId];
}
