<?php

/**
 * Catálogo dos avisos automáticos — o que o sistema manda sozinho, sem ninguém clicar.
 *
 * Esta lista é a fonte única de três coisas: os toggles que aparecem em Configurações →
 * Avisos e notificações, as chaves que o save_configuracao.php aceita gravar, e a checagem
 * que cada disparo faz antes de enviar. Aviso novo entra aqui primeiro; se ficar só no
 * código do disparo, ele não aparece no admin e não tem como ser desligado.
 *
 * O estado fica em `configuracoes` como '1' / '0'. Chave ausente conta como LIGADO: ao
 * subir esta função pela primeira vez nada muda de comportamento até alguém desligar algo.
 *
 * Fica de fora de propósito o que é disparado por uma pessoa no admin (botões "Disparar
 * cobrança", "Enviar lembrete", "Enviar notificações"): se o admin clicou, ele quer enviar.
 * E a cobrança automática no cartão não é aviso — é cobrança, e não entra aqui.
 */

function avisosCatalogo(): array
{
    return [
        'Alunos matriculados' => [
            'aviso_treino_dia' => [
                'titulo'    => 'Hoje é dia de treino',
                'descricao' => 'Lembra o aluno no próprio dia do treino, com horário, endereço e link do mapa.',
                'quando'    => 'Todo dia às 05:00',
                'canal'     => 'WhatsApp',
            ],
            'aviso_mensalidade_5dias' => [
                'titulo'    => 'Mensalidade vence em 5 dias',
                'descricao' => 'Aviso antecipado do vencimento, com o valor.',
                'quando'    => 'Todo dia às 08:10',
                'canal'     => 'WhatsApp',
            ],
            'aviso_mensalidade_vencimento' => [
                'titulo'    => 'Mensalidade vence hoje',
                'descricao' => 'Enviado no dia do vencimento, uma vez por mensalidade.',
                'quando'    => 'Todo dia às 08:10',
                'canal'     => 'WhatsApp',
            ],
            'aviso_mensalidade_atraso' => [
                'titulo'    => 'Mensalidade em atraso',
                'descricao' => 'Repete a cada 2 dias enquanto a mensalidade estiver atrasada, informando há quantos dias.',
                'quando'    => 'Todo dia às 08:10',
                'canal'     => 'WhatsApp',
            ],
            'aviso_comunicado' => [
                'titulo'    => 'Novo comunicado publicado',
                'descricao' => 'Avisa todos os alunos ativos quando um comunicado é publicado pela primeira vez.',
                'quando'    => 'Na hora da publicação',
                'canal'     => 'WhatsApp',
            ],
        ],

        'Aula experimental' => [
            'aviso_teste_confirmacao' => [
                'titulo'    => 'Confirmação do agendamento',
                'descricao' => 'Mensagem "Sua aula experimental está confirmada", com data, horário e mapa. Menor de idade: o termo de autorização continua indo para o responsável mesmo com este aviso desligado.',
                'quando'    => 'Na hora do agendamento',
                'canal'     => 'WhatsApp e e-mail',
            ],
            'aviso_teste_reagendamento' => [
                'titulo'    => 'Reagendamento',
                'descricao' => 'Avisa a nova data quando uma aula cancelada é reagendada.',
                'quando'    => 'Na hora do reagendamento',
                'canal'     => 'WhatsApp',
            ],
            'aviso_teste_3dias' => [
                'titulo'    => 'Lembrete 3 dias antes',
                'descricao' => 'Primeiro lembrete da aula experimental.',
                'quando'    => 'Todo dia às 08:00',
                'canal'     => 'WhatsApp',
            ],
            'aviso_teste_2dias' => [
                'titulo'    => 'Lembrete 2 dias antes',
                'descricao' => 'Segundo lembrete da aula experimental.',
                'quando'    => 'Todo dia às 08:05',
                'canal'     => 'WhatsApp',
            ],
            'aviso_teste_vespera' => [
                'titulo'    => 'Lembrete na véspera',
                'descricao' => 'Lembrete no dia anterior à aula experimental.',
                'quando'    => 'Todo dia às 18:00',
                'canal'     => 'WhatsApp',
            ],
            'aviso_teste_dia' => [
                'titulo'    => 'Lembrete no dia da aula',
                'descricao' => 'Lembrete na manhã da aula experimental.',
                'quando'    => 'Todo dia às 06:00',
                'canal'     => 'WhatsApp',
            ],
        ],

        'Site' => [
            'aviso_interesse_email' => [
                'titulo'    => 'Confirmação de interesse',
                'descricao' => 'E-mail automático para quem preenche o formulário de interesse no site.',
                'quando'    => 'Na hora do envio do formulário',
                'canal'     => 'E-mail',
            ],
        ],
    ];
}

/** Todas as chaves de aviso, sem o agrupamento. */
function avisosChaves(): array
{
    $chaves = [];
    foreach (avisosCatalogo() as $avisos) {
        $chaves = array_merge($chaves, array_keys($avisos));
    }
    return $chaves;
}

/**
 * Se um aviso está ligado. Chave ausente no banco conta como ligado.
 *
 * Lê todas as chaves de uma vez e guarda para o resto da requisição: o cron de mensalidade
 * consulta três avisos seguidos e não precisa ir três vezes ao banco.
 */
function avisoAtivo(PDO $pdo, string $chave): bool
{
    static $estado = null;

    if ($estado === null) {
        $estado = [];
        $chaves = avisosChaves();
        $marcas = implode(',', array_fill(0, count($chaves), '?'));

        $st = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ($marcas)");
        $st->execute($chaves);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $estado[$r['chave']] = $r['valor'] !== '0';
        }
    }

    return $estado[$chave] ?? true;
}

/**
 * Para os crons: encerra se o aviso estiver desligado, deixando o motivo na saída.
 *
 * Sem a frase, o log da KingHost mostraria só "0 enviados" — igual a um dia sem ninguém para
 * avisar, ou a uma falha. Assim quem ler o log sabe que foi desligado de propósito.
 */
function avisoCronExigeAtivo(PDO $pdo, string $chave, string $rotulo): void
{
    if (avisoAtivo($pdo, $chave)) return;

    echo "{$rotulo}: desligado em Configurações > Avisos e notificações.\n";
    exit;
}
