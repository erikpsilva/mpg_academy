<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT
        ae.id, ae.status, ae.data_agendada, ae.criado_em,
        at.id AS aluno_teste_id, at.nome, at.email, at.celular,
        at.is_menor, at.data_nascimento, at.responsavel_nome, at.responsavel_email, at.responsavel_cpf, at.responsavel_celular,
        t.id AS turma_id, t.nome AS turma_nome, t.nivel, t.max_alunos,
        q.nome AS quadra_nome,
        (SELECT COUNT(*) FROM turma_alunos ta
            WHERE ta.turma_id = t.id AND ta.status = 'ativo') AS alunos_ativos,
        ts.id AS termo_id,
        ts.token AS termo_token,
        ts.assinante_escola_nome,
        ts.assinado_escola_em,
        ts.responsavel_nome_assinado,
        ts.assinado_responsavel_em
    FROM aulas_experimentais ae
    JOIN alunos_teste at ON at.id = ae.aluno_teste_id
    JOIN turmas t        ON t.id  = ae.turma_id
    JOIN quadras q       ON q.id  = t.quadra_id
    LEFT JOIN termo_assinaturas ts ON ts.aula_experimental_id = ae.id
    WHERE ae.status IN ('agendada', 'fila')
    ORDER BY t.id ASC,
             FIELD(ae.status, 'agendada', 'fila') ASC,
             ae.data_agendada ASC,
             ae.criado_em ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$turmasMap = [];
foreach ($rows as $r) {
    $tid = (int) $r['turma_id'];

    if (!isset($turmasMap[$tid])) {
        $maxAlunos    = $r['max_alunos'] !== null ? (int) $r['max_alunos'] : null;
        $alunosAtivos = (int) $r['alunos_ativos'];

        // Capacidade de teste que a turma tem EM CADA DIA. O desconto dos já agendados é
        // feito data a data mais abaixo — um teste no dia 21 não tira vaga do dia 28.
        $vagasPorData = $maxAlunos !== null ? max(0, $maxAlunos - $alunosAtivos) : null;

        $turmasMap[$tid] = [
            'turma_id'      => $tid,
            'turma_nome'    => $r['turma_nome'],
            'quadra_nome'   => $r['quadra_nome'],
            'nivel'         => $r['nivel'],
            'max_alunos'     => $maxAlunos,
            'alunos_ativos'  => $alunosAtivos,
            'vagas_por_data' => $vagasPorData,
            'agendados'      => [],   // lista achatada, usada pelos totais do topo
            'datas'          => [],   // agendados agrupados por dia, cada um com sua vaga
            'fila'           => [],
        ];
    }

    // Calcula status do termo
    $termoStatus = null;
    if ($r['termo_id']) {
        $escSigned  = !empty($r['assinado_escola_em']);
        $respSigned = !empty($r['assinado_responsavel_em']);
        if ($escSigned && $respSigned) $termoStatus = 'concluido';
        elseif ($escSigned)            $termoStatus = 'aguardando_responsavel';
        elseif ($respSigned)           $termoStatus = 'aguardando_escola';
        else                           $termoStatus = 'pendente';
    }

    $menor = (bool)(int)$r['is_menor'];

    $entrada = [
        'id'               => (int) $r['id'],
        'aluno_teste_id'   => (int) $r['aluno_teste_id'],
        'turma_id'         => $tid,
        'nome'             => $r['nome'],
        'email'            => $r['email'],
        'celular'          => $r['celular'],
        'data_nascimento'  => $r['data_nascimento'],
        'menor'            => $menor,
        'responsavel_nome'    => $r['responsavel_nome'],
        'responsavel_email'   => $r['responsavel_email'],
        'responsavel_cpf'     => $r['responsavel_cpf'],
        'responsavel_celular' => $r['responsavel_celular'],
        'data_agendada'    => $r['data_agendada'],
        'criado_em'        => $r['criado_em'],
        'status'           => $r['status'],
        'termo_id'         => $r['termo_id'] ? (int)$r['termo_id'] : null,
        'termo_token'      => $r['termo_token'],
        'termo_status'     => $termoStatus,
        'assinante_escola_nome'      => $r['assinante_escola_nome'],
        'assinado_escola_em'         => $r['assinado_escola_em'],
        'responsavel_nome_assinado'  => $r['responsavel_nome_assinado'],
        'assinado_responsavel_em'    => $r['assinado_responsavel_em'],
    ];

    if ($r['status'] === 'agendada') {
        $turmasMap[$tid]['agendados'][] = $entrada;
    } else {
        $turmasMap[$tid]['fila'][] = $entrada;
    }
}

// ── Agrupamento por data ─────────────────────────────────────────────────────
//
// Cada dia é uma "sessão" independente: a vaga de teste vale para aquela data e não é
// consumida pelas outras. É isso que permite imprimir a folha do dia 21 sem misturar com
// a do dia 28, e agendar 10 pessoas em cada um deles numa turma de 10 vagas.
foreach ($turmasMap as $tid => $turma) {
    $porData = [];

    foreach ($turma['agendados'] as $a) {
        $dia = $a['data_agendada'] ?: 'sem-data';
        $porData[$dia][] = $a;
    }

    ksort($porData);   // datas no formato Y-m-d ordenam corretamente como texto

    $datas = [];
    foreach ($porData as $dia => $itens) {
        $datas[] = [
            'data'        => $dia === 'sem-data' ? null : $dia,
            'agendados'   => $itens,
            'total'       => count($itens),
            // Vaga restante NAQUELE dia. null = turma sem limite.
            'vagas_teste' => $turma['vagas_por_data'] === null
                ? null
                : max(0, $turma['vagas_por_data'] - count($itens)),
        ];
    }

    $turmasMap[$tid]['datas'] = $datas;
}

// Quem já realizou a aula experimental é consultado em `todosalunos-teste`
// ("Todos Agendamentos Experimentais") — esta tela lista só agendados e fila.
echo json_encode([
    'success' => true,
    'turmas'  => array_values($turmasMap),
]);
