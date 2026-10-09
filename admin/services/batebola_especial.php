<?php
if (!defined('ROOT') || empty($_SESSION['usuario'])) { http_response_code(404); exit; }
// Incluído pela página autenticada, antes de qualquer saída HTML.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
try {
    if(($_SESSION['usuario']['nivel_acesso']??'')!=='admin') throw new RuntimeException('Somente administradores podem alterar eventos.');
    if(empty($_POST['csrf']) || !hash_equals($_SESSION['especial_admin_csrf'],(string)$_POST['csrf'])) throw new RuntimeException('Atualize a página e tente novamente.');
    $acao=$_POST['acao']??''; $id=(int)($_POST['evento_id']??0);
    if($acao==='conciliar' && !APP_IS_LOCAL) {
        require_once ROOT.'/config/conciliacao_mp.php';
        mpConciliarPendentes($pdo,['batebola'],true,15);
    }
    if((int)$pdo->query("SELECT GET_LOCK('mpg_bb_reservas',10)")->fetchColumn()!==1)throw new RuntimeException('Tente novamente em instantes.');
    $pdo->beginTransaction();
    $evento=$id?especialBuscar($pdo,$id,true):null;
    if($id && !$evento) throw new RuntimeException('Evento não encontrado.');
    if($acao==='salvar') {
        $titulo=trim($_POST['titulo']??''); $data=$_POST['data']??''; $inicio=$_POST['inicio']??''; $fim=$_POST['fim']??'';
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$data.' '.$inicio,new DateTimeZone(BATEBOLA_FUSO));
        $df=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$data.' '.$fim,new DateTimeZone(BATEBOLA_FUSO));
        $valor=filter_var(str_replace(',','.',$_POST['valor']??''),FILTER_VALIDATE_FLOAT); $vagas=filter_var($_POST['vagas']??'',FILTER_VALIDATE_INT);
        if($titulo==='' || mb_strlen($titulo)>120) throw new RuntimeException('Informe um título com até 120 caracteres.');
        if(!$dt || !$df || $dt->format('Y-m-d H:i')!==$data.' '.$inicio || $df->format('Y-m-d H:i')!==$data.' '.$fim || $df<=$dt || $dt->format('Y-m-d H:i:s')<=especialAgora()) throw new RuntimeException('Escolha uma data futura e horário final posterior ao inicial.');
        if($valor===false || $valor<1 || $valor>9999.99 || $vagas===false || $vagas<1 || $vagas>24) throw new RuntimeException('Informe um valor entre R$ 1 e R$ 9.999,99 e de 1 a 24 vagas.');
        if($evento) {
            if(especialEstado($evento)==='encerrado') throw new RuntimeException('Este evento já foi encerrado. Crie um novo evento.');
            $st=$pdo->prepare('SELECT COUNT(*) FROM batebola_especial_inscricoes WHERE evento_id=?'); $st->execute([$id]);
            if($st->fetchColumn()) throw new RuntimeException('O evento já tem inscrições. Data, horário, preço e vagas ficam preservados.');
            require_once ROOT.'/config/batebola_checkout.php';
            if(bbReservas($pdo,'especial:'.$id)>0)throw new RuntimeException('O evento tem vagas reservadas em pagamentos conjuntos. Os dados ficam preservados.');
            $pdo->prepare('UPDATE batebola_especiais SET titulo=?,inicio=?,fim=?,valor=?,vagas=? WHERE id=?')->execute([$titulo,$dt->format('Y-m-d H:i:s'),$df->format('Y-m-d H:i:s'),round($valor,2),$vagas,$id]);
        } else {
            $pdo->prepare('INSERT INTO batebola_especiais(titulo,inicio,fim,valor,vagas) VALUES(?,?,?,?,?)')->execute([$titulo,$dt->format('Y-m-d H:i:s'),$df->format('Y-m-d H:i:s'),round($valor,2),$vagas]); $id=(int)$pdo->lastInsertId();
        }
    } else {
        if(!$evento) throw new RuntimeException('Selecione um evento.');
        if(especialEstado($evento)==='encerrado') throw new RuntimeException('Evento encerrado: o histórico é somente leitura.');
        if($acao==='status') {
            $status=$_POST['status']??'';
            if(!in_array($status,['aberto','fechado','encerrado'],true)) throw new RuntimeException('Status inválido.');
            if($status==='aberto' && $evento['inicio']<=especialAgora()) throw new RuntimeException('Este evento já começou.');
            $pdo->prepare('UPDATE batebola_especiais SET status=? WHERE id=?')->execute([$status,$id]);
        } elseif($acao==='adicionar_pago') {
            require_once ROOT.'/config/batebola_especial_manual.php';
            $tipo=$_POST['tipo_confirmacao']??'pago';
            if(!in_array($tipo,['pago','isento'],true))throw new RuntimeException('Selecione Pago ou Isento.');
            especialAdicionarPago($pdo,$evento,(int)($_POST['jogador_id']??0),$tipo==='isento');
        } elseif(in_array($acao,['excluir_confirmado','tipo_confirmacao'],true)) {
            require_once ROOT.'/config/batebola_especial_manual.php';
            especialEditarConfirmado($pdo,$evento,(int)($_POST['inscricao_id']??0),$acao,(string)($_POST['tipo_confirmacao']??''));
        } elseif($acao==='presenca') {
            $pdo->prepare("UPDATE batebola_especial_inscricoes SET presente=? WHERE id=? AND evento_id=? AND status='pago'")->execute([empty($_POST['presente'])?0:1,(int)($_POST['inscricao_id']??0),$id]);
        } elseif($acao==='conciliar') {
            if(APP_IS_LOCAL) throw new RuntimeException('Consulta externa de pagamentos desabilitada no ambiente local.');
            require_once ROOT.'/config/mercadopago.php';
            $pendentes=$pdo->prepare("SELECT id,mp_payment_id FROM batebola_especial_inscricoes WHERE evento_id=? AND status='pendente' AND mp_payment_id IS NOT NULL"); $pendentes->execute([$id]);
            foreach($pendentes->fetchAll() as $pendente) {
                $payment=mpConsultarPagamento(mpAccessToken($pdo),$pendente['mp_payment_id']);
                if($payment) {
                    especialConfirmar($pdo,(int)$pendente['id'],$payment);
                    if(in_array($payment['status']??'',['cancelled','rejected','refunded','charged_back'],true)) $pdo->prepare("UPDATE batebola_especial_inscricoes SET status='cancelado' WHERE id=? AND status='pendente'")->execute([$pendente['id']]);
                }
            }
        } elseif($acao==='sortear') {
            $st=$pdo->prepare("SELECT COUNT(*) FROM batebola_especial_inscricoes WHERE evento_id=? AND status='pago'"); $st->execute([$id]);
            if(!$st->fetchColumn()) throw new RuntimeException('Ainda não há jogadores confirmados para sortear.');
            $pdo->prepare('UPDATE batebola_especiais SET seed=?,trocas=NULL WHERE id=?')->execute([random_int(1,2147483647),$id]);
        } elseif($acao==='limpar') {
            $pdo->prepare('UPDATE batebola_especiais SET seed=NULL,trocas=NULL WHERE id=?')->execute([$id]);
        } elseif($acao==='trocar') {
            $a=(int)($_POST['jogador_a']??0); $b=(int)($_POST['jogador_b']??0); $times=especialTimes($pdo,$evento);
            $pa=batebolaPosicaoJogador($times,$a); $pb=batebolaPosicaoJogador($times,$b);
            if(!$pa || !$pb || $pa[0]===$pb[0]) throw new RuntimeException('Selecione jogadores de times diferentes.');
            $trocas=json_decode($evento['trocas']??'[]',true)?:[]; $trocas[]=[$a,$b];
            $pdo->prepare('UPDATE batebola_especiais SET trocas=? WHERE id=?')->execute([json_encode($trocas),$id]);
        } else throw new RuntimeException('Ação inválida.');
    }
    $pdo->commit();
    $aba=in_array($acao,['sortear','limpar','trocar'],true)?'times':(in_array($acao,['presenca','conciliar','adicionar_pago','excluir_confirmado','tipo_confirmacao'],true)?'presenca':'informacoes');
    header('Location: '.BASE_URL.'/admin/batebolaespecial?id='.$id.'&aba='.$aba.'&salvo=1'); exit;
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('[bb-especial-admin] '.$e->getMessage());
    $erro=$e instanceof RuntimeException?$e->getMessage():'Não foi possível salvar o evento.';
}
