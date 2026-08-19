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

const UNIFORME_VALOR_PADRAO     = 115.00;  // uniforme completo (camisa + calção)
const UNIFORME_VALOR_EQUIPE_PADRAO = 49.90;  // camisa da equipe técnica, vendida sozinha
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

// ─────────────────────────────────────────────────────────────────────────────
// Uniforme da equipe técnica
// ─────────────────────────────────────────────────────────────────────────────
//
// Dois produtos diferentes convivem na mesma tabela de pedidos:
//   completo       — camisa + calção, com número e nome. É o do aluno, e também o que
//                    professor/admin podem pedir.
//   equipe_tecnica — SÓ a camisa, sem número, com um texto de cargo no lugar (Equipe
//                    Técnica ou Técnico) seguido do nome da pessoa.
//
// Equipe técnica é exclusivo de quem trabalha na academia: professor (tabela `professores`)
// ou usuário do painel (tabela `admin_usuarios`). Aluno nunca pode pedir — é o que separa
// a camisa da comissão da camisa de quem joga.
const UNIFORME_TIPOS = ['completo', 'equipe_tecnica'];

const UNIFORME_TIPO_LABEL = [
    'completo'       => 'Uniforme completo (camisa + calção)',
    'equipe_tecnica' => 'Equipe técnica (só camisa)',
];

/** Quem pode receber um pedido. Aluno não entra em equipe técnica. */
const UNIFORME_PESSOA_TIPOS = ['aluno', 'professor', 'admin'];

const UNIFORME_PESSOA_LABEL = [
    'aluno'     => 'Aluno',
    'professor' => 'Professor',
    'admin'     => 'Equipe MPG',
];

/** Texto que vai na camisa da equipe técnica, antes do nome. */
const UNIFORME_CARGOS = ['equipe_tecnica', 'tecnico'];

const UNIFORME_CARGO_LABEL = [
    'equipe_tecnica' => 'Equipe Técnica',
    'tecnico'        => 'Técnico',
];

/** Imagem de referência da camisa da equipe técnica. */
const UNIFORME_EQUIPE_IMAGEM = 'images/uniformes/equipetecnica.png';

/** As duas peças que o aluno escolhe tamanho separadamente. */
const UNIFORME_PECAS = ['camisa', 'shorts'];

/** Aviso do fabricante, exibido junto de toda tabela de medidas. */
const UNIFORME_AVISO_MEDIDAS = 'As medidas podem variar cerca de 3% por conta da costura.';

/**
 * Equivalência com a numeração tradicional (P/M/G e 38, 40, 42...).
 *
 * A tabela do fabricante é em centímetros, e muita gente não sabe traduzir isso pro tamanho
 * que costuma vestir — daí a dúvida na hora de pedir. Esta tabela existe só como referência
 * de apoio: é APROXIMADA, e quem manda continua sendo a medida em cm.
 *
 * Chaveada igual a UNIFORME_MEDIDAS (gênero → peça) pra poder ser exibida logo abaixo da
 * tabela correspondente sem precisar de nenhum de-para no meio do caminho.
 */
const UNIFORME_CONVERSAO_AVISO = 'Equivalência aproximada, só pra orientar. Na dúvida entre dois tamanhos, '
                               . 'confira as medidas em centímetros acima — e, pra um caimento mais folgado, prefira o maior.';

const UNIFORME_CONVERSAO = [
    'masculino' => [
        'camisa' => [
            'coluna' => 'Equivalência aproximada',
            'linhas' => [
                ['PP',  'PP / 34-36'],
                ['P',   'P / 36-38'],
                ['M',   'M / 40-42'],
                ['G',   'G / 44-46'],
                ['GG',  'GG / 48-50'],
                ['XG',  'XG / 52-54'],
                ['XG1', 'XGG / 56'],
                ['XG2', '3G / 58-60'],
                ['XG3', '4G / 62+'],
            ],
        ],
        'shorts' => [
            'coluna' => 'Equivalência aproximada',
            'linhas' => [
                ['PP',  'PP / 34-36'],
                ['P',   'P / 38'],
                ['M',   'M / 40-42'],
                ['G',   'G / 44-46'],
                ['GG',  'GG / 48'],
                ['EXG', 'XG / 50-52'],
                ['G1',  'XGG / 54-56'],
            ],
        ],
    ],
    'feminino' => [
        'camisa' => [
            'coluna' => 'Equivalência aproximada',
            'linhas' => [
                ['PP', 'PP / 34-36'],
                ['P',  'P / 36-38'],
                ['M',  'M / 40'],
                ['G',  'G / 42-44'],
                ['GG', 'GG / 46-48'],
                ['XG', 'XG / 50-52'],
            ],
        ],
        'shorts' => [
            // A bermuda feminina é modelo justo — aqui a referência é a numeração, não P/M/G.
            'coluna' => 'Numeração feminina aproximada',
            'linhas' => [
                ['P',  '36-38'],
                ['M',  '40-42'],
                ['G',  '44-46'],
                ['GG', '48-50'],
            ],
        ],
    ],
];

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
            'label'   => 'Camisa feminina baby look',
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

/**
 * Cor da peça, derivada do modelo — o líbero usa camisa contrastante, como manda a regra
 * do vôlei. Não existe coluna `cor` no banco de propósito: a cor é consequência do modelo,
 * então gravá-la separado abriria espaço pros dois divergirem.
 *
 * Vale igual pra uniforme de aluno e pra camisa da equipe técnica — os dois gravam modelo.
 */
const UNIFORME_COR_POR_MODELO = [
    'padrao' => 'Preto',
    'libero' => 'Amarelo',
];

function uniformeCor(?string $modelo): string
{
    return UNIFORME_COR_POR_MODELO[$modelo] ?? '—';
}

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
 * Preço da camisa da equipe técnica.
 *
 * Guardado à parte do uniforme completo porque é outro produto: só a camisa. Fica em
 * `configuracoes` (chave valor_uniforme_equipe) pra poder ser mudado pelo admin sem deploy,
 * igual ao valor da matrícula e do Bate Bola.
 */
function uniformeValorEquipe(PDO $pdo): float
{
    $st = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'valor_uniforme_equipe'");
    $st->execute();
    $row = $st->fetch();

    return $row ? (float) $row["valor"] : UNIFORME_VALOR_EQUIPE_PADRAO;
}

/** Equivalência com a numeração tradicional de uma peça — vazio se não houver. */
function uniformeTabelaConversao(string $genero, string $peca): array
{
    return UNIFORME_CONVERSAO[$genero][$peca] ?? [];
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

        // pessoa_tipo/pessoa_id são obrigatórios desde que o pedido passou a poder ser de
        // professor ou equipe MPG: é por esse par que as telas descobrem o nome de quem
        // pediu. Sem preencher aqui, o pedido aparecia como "(removido)" na listagem.
        $pdo->prepare("
            INSERT INTO pedidos_uniforme
                (pessoa_tipo, pessoa_id, tipo_uniforme,
                 aluno_id, turma_id, genero, modelo, nome_camisa, numero,
                 tamanho_camisa, tamanho_shorts, valor,
                 status_pagamento, status_pedido, pago_em, criado_por_usuario_id, visto_admin)
            VALUES ('aluno', ?, 'completo', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pago', 'pendente', NOW(), ?, 1)
        ")->execute([$alunoId, $alunoId, $turmaId, $genero, $modelo, $nomeCamisa, $numero,
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

// ─────────────────────────────────────────────────────────────────────────────
// Pedidos para professor e equipe MPG
// ─────────────────────────────────────────────────────────────────────────────

/** É gente da casa (professor ou usuário do painel)? Só esses podem pedir equipe técnica. */
function uniformeEhEquipe(string $pessoaTipo): bool
{
    return in_array($pessoaTipo, ['professor', 'admin'], true);
}

/** Texto que vai estampado na camisa da equipe técnica. */
function uniformeTextoEquipe(?string $cargo, string $nome): string
{
    $prefixo = UNIFORME_CARGO_LABEL[$cargo ?? ''] ?? 'Equipe Técnica';
    return $prefixo . ' — ' . $nome;
}

/**
 * Pessoas que podem receber pedido de equipe, já separadas por origem.
 *
 * Professor e admin vêm de tabelas diferentes (`professores` e `admin_usuarios`) e podem ter
 * o mesmo id, então é o par tipo+id que identifica a pessoa — nunca o id sozinho.
 *
 * @return array Lista de ['tipo','id','nome','email','rotulo'].
 */
function uniformeEquipeDisponivel(PDO $pdo): array
{
    $pessoas = [];

    $stProf = $pdo->query("
        SELECT id, TRIM(CONCAT(nome, ' ', COALESCE(sobrenome, ''))) AS nome, email
        FROM professores
        WHERE status = 'ativo'
        ORDER BY nome
    ");
    foreach ($stProf->fetchAll() as $p) {
        $pessoas[] = [
            'tipo'   => 'professor',
            'id'     => (int) $p['id'],
            'nome'   => $p['nome'],
            'email'  => $p['email'],
            'rotulo' => 'Professor',
        ];
    }

    // `batebola` é login só do módulo de vôlei avulso — não é equipe da academia.
    $stAdm = $pdo->query("
        SELECT id, nome_completo AS nome, email
        FROM admin_usuarios
        WHERE nivel_acesso IN ('admin', 'editor')
        ORDER BY nome_completo
    ");
    foreach ($stAdm->fetchAll() as $u) {
        $pessoas[] = [
            'tipo'   => 'admin',
            'id'     => (int) $u['id'],
            'nome'   => $u['nome'],
            'email'  => $u['email'],
            'rotulo' => 'Equipe MPG',
        ];
    }

    return $pessoas;
}

/** Confere que a pessoa existe de fato na tabela dela. Retorna o nome, ou null. */
function uniformePessoaNome(PDO $pdo, string $pessoaTipo, int $pessoaId): ?string
{
    if ($pessoaTipo === 'aluno') {
        $st = $pdo->prepare("SELECT nome FROM alunos WHERE id = ?");
    } elseif ($pessoaTipo === 'professor') {
        $st = $pdo->prepare("SELECT TRIM(CONCAT(nome, ' ', COALESCE(sobrenome, ''))) FROM professores WHERE id = ?");
    } elseif ($pessoaTipo === 'admin') {
        $st = $pdo->prepare("SELECT nome_completo FROM admin_usuarios WHERE id = ?");
    } else {
        return null;
    }

    $st->execute([$pessoaId]);
    $nome = $st->fetchColumn();

    return $nome !== false ? (string) $nome : null;
}

/**
 * Números já ocupados no uniforme COMPLETO da equipe.
 *
 * A equipe não está em turma nenhuma, então não cabe no balde turma+gênero do aluno — mas
 * também não pode ficar sem trava, senão dois professores pedem o mesmo número. Aqui o balde
 * é a própria equipe: professor e admin dividem a mesma numeração, separada por gênero.
 *
 * O número que já é da pessoa nunca aparece como ocupado pra ela (mesma regra do aluno).
 */
function uniformeNumerosDoBaldeEquipe(PDO $pdo, string $genero, string $pessoaTipo, int $pessoaId): array
{
    uniformeExpirarReservas($pdo);

    $st = $pdo->prepare("
        SELECT DISTINCT numero, pessoa_tipo, pessoa_id
        FROM pedidos_uniforme
        WHERE pessoa_tipo IN ('professor', 'admin')
          AND tipo_uniforme = 'completo'
          AND genero = ?
          AND numero IS NOT NULL
          AND (
                status_pagamento = 'pago'
                OR (status_pagamento = 'aguardando' AND reserva_expira_em > NOW())
              )
    ");
    $st->execute([$genero]);

    $ocupados = [];
    $meus     = [];

    foreach ($st->fetchAll() as $r) {
        $numero = (int) $r['numero'];
        if ($r['pessoa_tipo'] === $pessoaTipo && (int) $r['pessoa_id'] === $pessoaId) {
            $meus[] = $numero;
        } else {
            $ocupados[] = $numero;
        }
    }

    $ocupados = array_values(array_diff(array_unique($ocupados), $meus));
    sort($ocupados);
    $meus = array_values(array_unique($meus));
    sort($meus);

    return ['ocupados' => $ocupados, 'meus' => $meus];
}

/**
 * Cria um pedido para professor ou equipe MPG. Nasce já pago, com valor zero.
 *
 * A academia banca o uniforme da equipe, então não há cobrança nem passagem pelo Mercado
 * Pago: o pedido entra direto na fila de produção. Valor zero é de propósito — mantém o
 * pedido fora de Pagamentos Uniformes, que existe pra acompanhar dinheiro que entrou.
 *
 * @param string      $tipoUniforme  'completo' ou 'equipe_tecnica'.
 * @param int|null    $numero        Só no completo; equipe técnica não tem número.
 * @param string|null $tamanhoShorts Só no completo.
 * @param string|null $cargo         Só na equipe técnica: 'equipe_tecnica' ou 'tecnico'.
 *
 * @return array{success:bool, message?:string, pedido_id?:int}
 */
function uniformeCriarPedidoEquipe(
    PDO $pdo,
    string $pessoaTipo,
    int $pessoaId,
    string $tipoUniforme,
    string $genero,
    string $modelo,
    string $nomeCamisa,
    ?int $numero,
    string $tamanhoCamisa,
    ?string $tamanhoShorts,
    ?string $cargo,
    int $criadoPorUsuarioId
): array {
    if (!uniformeEhEquipe($pessoaTipo)) {
        return ['success' => false, 'message' => 'Esse pedido é só para professor ou equipe MPG.'];
    }

    if (!in_array($tipoUniforme, UNIFORME_TIPOS, true)) {
        return ['success' => false, 'message' => 'Tipo de uniforme inválido.'];
    }

    if (!in_array($genero, UNIFORME_GENEROS, true) || !in_array($modelo, UNIFORME_MODELOS, true)) {
        return ['success' => false, 'message' => 'Modelo de uniforme inválido.'];
    }

    $pessoaNome = uniformePessoaNome($pdo, $pessoaTipo, $pessoaId);
    if ($pessoaNome === null) {
        return ['success' => false, 'message' => 'Pessoa não encontrada.'];
    }

    if ($nomeCamisa === '') {
        return ['success' => false, 'message' => 'Informe o nome que vai na camisa.'];
    }

    if (!in_array($tamanhoCamisa, uniformeTamanhos($genero, 'camisa'), true)) {
        return ['success' => false, 'message' => 'Tamanho da camisa inválido para esse uniforme.'];
    }

    // ── O que é exigido muda com o tipo ──────────────────────────────────────
    if ($tipoUniforme === 'equipe_tecnica') {
        // Só camisa: número e calção não existem aqui.
        $numero        = null;
        $tamanhoShorts = null;

        if (!in_array($cargo, UNIFORME_CARGOS, true)) {
            return ['success' => false, 'message' => 'Escolha o texto da camisa (Equipe Técnica ou Técnico).'];
        }
    } else {
        $cargo = null;

        if ($numero === null || $numero < UNIFORME_NUMERO_MIN || $numero > UNIFORME_NUMERO_MAX) {
            return ['success' => false, 'message' => 'Escolha um número de ' . UNIFORME_NUMERO_MIN . ' a ' . UNIFORME_NUMERO_MAX . '.'];
        }

        if (!in_array((string) $tamanhoShorts, uniformeTamanhos($genero, 'shorts'), true)) {
            $peca = $genero === 'feminino' ? 'da bermuda' : 'do calção';
            return ['success' => false, 'message' => 'Tamanho ' . $peca . ' inválido para esse uniforme.'];
        }
    }


    // Cada produto tem seu preço: a camisa da equipe é vendida sozinha e custa menos que
    // o uniforme completo. Os dois saem de `configuracoes`, então mudam sem deploy.
    $valorPeca = $tipoUniforme === 'equipe_tecnica'
        ? uniformeValorEquipe($pdo)
        : uniformeValor($pdo);

    try {
        $pdo->beginTransaction();

        uniformeExpirarReservas($pdo);

        // Mesma trava do fluxo do aluno, no balde da equipe.
        if ($tipoUniforme === 'completo') {
            $stLock = $pdo->prepare("
                SELECT pessoa_tipo, pessoa_id
                FROM pedidos_uniforme
                WHERE pessoa_tipo IN ('professor', 'admin')
                  AND tipo_uniforme = 'completo'
                  AND genero = ?
                  AND numero = ?
                  AND (
                        status_pagamento = 'pago'
                        OR (status_pagamento = 'aguardando' AND reserva_expira_em > NOW())
                      )
                FOR UPDATE
            ");
            $stLock->execute([$genero, $numero]);

            foreach ($stLock->fetchAll() as $dono) {
                if ($dono['pessoa_tipo'] !== $pessoaTipo || (int) $dono['pessoa_id'] !== $pessoaId) {
                    $pdo->rollBack();
                    return [
                        'success' => false,
                        'message' => 'O número ' . $numero . ' já está em uso por outra pessoa da equipe.',
                    ];
                }
            }
        }

        $pdo->prepare("
            INSERT INTO pedidos_uniforme
                (pessoa_tipo, pessoa_id, tipo_uniforme, equipe_cargo,
                 aluno_id, turma_id, genero, modelo, nome_camisa, numero,
                 tamanho_camisa, tamanho_shorts, valor,
                 status_pagamento, status_pedido, pago_em, criado_por_usuario_id, visto_admin)
            VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, 'pago', 'pendente', NOW(), ?, 1)
        ")->execute([
            $pessoaTipo, $pessoaId, $tipoUniforme, $cargo,
            $genero, $modelo, $nomeCamisa, $numero,
            $tamanhoCamisa, $tamanhoShorts, $valorPeca, $criadoPorUsuarioId,
        ]);

        $pedidoId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[uniforme-pedido-equipe] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao criar o pedido. Tente novamente.'];
    }

    return ['success' => true, 'pedido_id' => $pedidoId];
}
