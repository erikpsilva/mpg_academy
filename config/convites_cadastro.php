<?php

require_once __DIR__ . '/mensalidades.php';

function conviteBuscar(PDO $pdo, string $token, bool $bloquear = false): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $sql = "SELECT c.*, t.nome AS turma_nome, t.status AS turma_status
            FROM cadastro_convites c JOIN turmas t ON t.id=c.turma_id
            WHERE c.token_hash=? AND c.usado_em IS NULL AND c.expira_em>=NOW()" . ($bloquear ? ' FOR UPDATE' : '');
    $st = $pdo->prepare($sql); $st->execute([hash('sha256', $token)]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function conviteContarAulas(PDO $pdo, int $turmaId, string $inicio, string $fim): int
{
    $st=$pdo->prepare("SELECT DISTINCT qh.dia_semana FROM turma_horarios th JOIN quadra_horarios qh ON qh.id=th.horario_id WHERE th.turma_id=?");
    $st->execute([$turmaId]); $dias=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
    $total=0; for($d=new DateTime($inicio),$f=new DateTime($fim);$d<=$f;$d->modify('+1 day')) if(in_array((int)$d->format('w'),$dias,true)) $total++;
    return $total;
}

function conviteProporcional(PDO $pdo, int $turmaId, DateTime $entrada, float $valor): float
{
    $fim=$entrada->format('Y-m-').str_pad((string)min(30,(int)$entrada->format('t')),2,'0',STR_PAD_LEFT);
    $total=conviteContarAulas($pdo,$turmaId,$entrada->format('Y-m-01'),$fim);
    $restantes=conviteContarAulas($pdo,$turmaId,$entrada->format('Y-m-d'),$fim);
    if($total>0) return round(($restantes/$total)*$valor,2);
    return round((((int)$entrada->format('t')-(int)$entrada->format('j')+1)/(int)$entrada->format('t'))*$valor,2);
}

/** Matricula o aluno convidado e cria a primeira fatura (proporcional + matrícula). */
function conviteMatricularAluno(PDO $pdo, int $alunoId, int $turmaId): int
{
    $st=$pdo->prepare("SELECT valor_mensalidade,promo_valor,promo_meses,max_alunos FROM turmas WHERE id=? AND status='ativa'");
    $st->execute([$turmaId]); $turma=$st->fetch(PDO::FETCH_ASSOC);
    if(!$turma || $turma['valor_mensalidade']===null) throw new RuntimeException('A turma escolhida não está disponível para matrícula.');
    if($turma['max_alunos']!==null){$c=$pdo->prepare("SELECT COUNT(*) FROM turma_alunos WHERE turma_id=? AND status='ativo'");$c->execute([$turmaId]);if((int)$c->fetchColumn()>=(int)$turma['max_alunos']) throw new RuntimeException('A turma escolhida atingiu o limite de alunos.');}

    $entrada=new DateTime(); $data=$entrada->format('Y-m-d'); $base=(float)$turma['valor_mensalidade'];
    $promo=$turma['promo_valor']!==null && $turma['promo_meses']!==null && (float)$turma['promo_valor']<$base;
    $valorMes=$promo?(float)$turma['promo_valor']:$base;
    $cheia=(int)$entrada->format('j')<=7 && new DateTime($entrada->format('Y-m-10'))>=new DateTime();
    $proporcional=$cheia?null:conviteProporcional($pdo,$turmaId,$entrada,$valorMes);
    $valorFatura=$cheia?$valorMes:(float)$proporcional;
    $vencimento=$cheia?$entrada->format('Y-m-10'):(new DateTime())->modify('+3 days')->format('Y-m-d');
    $desconto=$promo?round($base-$valorMes,2):null; $descontoFim=null;
    if($promo){$fim=new DateTime($entrada->format('Y-m-01'));$fim->modify('+'.($cheia?((int)$turma['promo_meses']-1):(int)$turma['promo_meses']).' months');$descontoFim=$fim->format('Y-m-d');}

    $cfg=$pdo->prepare("SELECT chave,valor FROM configuracoes WHERE chave IN ('matricula_ativa','valor_matricula')");$cfg->execute();$c=array_column($cfg->fetchAll(PDO::FETCH_ASSOC),'valor','chave');
    $matricula=($c['matricula_ativa']??'1')==='0'?0:(float)($c['valor_matricula']??150);
    $uniforme=min($matricula,mensalidadeValorUniformeMatricula($pdo));

    $pdo->prepare("INSERT INTO turma_alunos(turma_id,aluno_id,data_entrada,desconto,desconto_tipo,desconto_inicio,desconto_fim,desconto_vitalicio,status) VALUES(?,?,?,?,'fixo',?,?,0,'ativo')")
        ->execute([$turmaId,$alunoId,$data,$desconto,$promo?$data:null,$descontoFim]);
    $pdo->prepare("INSERT INTO mensalidades(aluno_id,turma_id,referencia,valor,matricula_valor,matricula_uniforme_valor,proporcional_valor,vencimento,status) VALUES(?,?,?,?,?,?,?,?,'pendente')")
        ->execute([$alunoId,$turmaId,$entrada->format('Y-m'),round($valorFatura+$matricula,2),$matricula?:null,$uniforme?:null,$proporcional,$vencimento]);
    $id=(int)$pdo->lastInsertId();
    if($matricula>0)$pdo->prepare("UPDATE alunos SET matricula_cobrada=1 WHERE id=?")->execute([$alunoId]);
    return $id;
}
