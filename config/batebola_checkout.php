<?php
require_once __DIR__.'/batebola_especial.php';

function bbCheckoutDisponivel(PDO $pdo): bool {
    try { $pdo->query('SELECT id FROM batebola_pedidos LIMIT 0'); return true; }
    catch(PDOException $e) { if(($e->errorInfo[1]??0)===1146)return false; throw $e; }
}
function bbPedido(PDO $pdo,int $id,int $jogador): ?array {
    bbLimparReservas($pdo);
    $st=$pdo->prepare('SELECT * FROM batebola_pedidos WHERE id=? AND jogador_id=?');$st->execute([$id,$jogador]);return $st->fetch(PDO::FETCH_ASSOC)?:null;
}
function bbLimparReservas(PDO $pdo): void {
    if(!bbCheckoutDisponivel($pdo))return;
    $limite=(new DateTimeImmutable('now',new DateTimeZone(BATEBOLA_FUSO)))->modify('-15 minutes')->format('Y-m-d H:i:s');
    $pdo->prepare("UPDATE batebola_pedidos SET status='cancelado' WHERE status='reservado' AND criado_em<?")->execute([$limite]);
}
function bbItens(PDO $pdo,int $pedido): array {
    $st=$pdo->prepare('SELECT * FROM batebola_pedido_itens WHERE pedido_id=? ORDER BY data_evento,id');$st->execute([$pedido]);return $st->fetchAll(PDO::FETCH_ASSOC);
}
function bbReservas(PDO $pdo,string $chave): int {
    if(!bbCheckoutDisponivel($pdo))return 0;
    $st=$pdo->prepare("SELECT COUNT(*) FROM batebola_pedido_itens i JOIN batebola_pedidos p ON p.id=i.pedido_id WHERE i.evento_chave=? AND p.status IN ('reservado','pendente')");$st->execute([$chave]);return (int)$st->fetchColumn();
}
function bbOpcoes(PDO $pdo,int $jogador): array {
    bbLimparReservas($pdo);
    $data=batebolaProximoDomingo($pdo);
    $opcoes=[['chave'=>'domingo:'.$data,'tipo'=>'domingo','especial_id'=>null,'data_evento'=>$data,'titulo'=>'Bate-bola de domingo','horario'=>(new DateTimeImmutable($data))->format('d/m/Y').' • '.batebolaHorarioTexto($data),'valor'=>batebolaValorEvento($pdo,$data),'aberto'=>batebolaInscricoesAbertas(),'vagas'=>BATEBOLA_MAX_VAGAS]];
    if(especialDisponivel($pdo)) {
        $st=$pdo->prepare("SELECT * FROM batebola_especiais WHERE fim>? AND status!='encerrado' ORDER BY inicio");$st->execute([especialAgora()]);
        foreach($st->fetchAll() as $e)$opcoes[]=['chave'=>'especial:'.$e['id'],'tipo'=>'especial','especial_id'=>(int)$e['id'],'data_evento'=>substr($e['inicio'],0,10),'titulo'=>$e['titulo'],'horario'=>especialData($e),'valor'=>(float)$e['valor'],'aberto'=>especialAberto($e),'vagas'=>(int)$e['vagas']];
    }
    foreach($opcoes as &$o) {
        $t=$o['tipo']==='domingo'?'batebola_inscricoes':'batebola_especial_inscricoes';$col=$o['tipo']==='domingo'?'data_evento':'evento_id';$key=$o['especial_id']??$o['data_evento'];
        $st=$pdo->prepare("SELECT status FROM $t WHERE $col=? AND jogador_id=?");$st->execute([$key,$jogador]);$o['inscricao']=$st->fetchColumn()?:'';
        $st=$pdo->prepare("SELECT COUNT(*) FROM $t WHERE $col=? AND status IN ('pago','pendente')");$st->execute([$key]);
        $o['livres']=max(0,$o['vagas']-(int)$st->fetchColumn()-bbReservas($pdo,$o['chave']));
        $o['disponivel']=$o['aberto'] && $o['livres']>0 && !in_array($o['inscricao'],['pago','pendente'],true);
    } unset($o);return $opcoes;
}
/** Reserva todos os itens em uma transação. Preços e disponibilidade vêm do servidor. */
function bbPreparar(PDO $pdo,int $jogador,array $selecionados): int {
    $selecionados=array_values(array_unique(array_filter($selecionados,'is_string')));sort($selecionados);
    if(!$selecionados || count($selecionados)>20)throw new RuntimeException('Selecione pelo menos um bate-bola.');
    if((int)$pdo->query("SELECT GET_LOCK('mpg_bb_reservas',10)")->fetchColumn()!==1)throw new RuntimeException('Muitas inscrições ao mesmo tempo. Tente novamente.');
    try {
        $pdo->beginTransaction();
        bbLimparReservas($pdo);
        $st=$pdo->prepare('SELECT id FROM jogadores_batebola WHERE id=? FOR UPDATE');$st->execute([$jogador]);if(!$st->fetchColumn())throw new RuntimeException('Entre novamente na sua conta.');
        $st=$pdo->prepare("SELECT id FROM batebola_pedidos WHERE jogador_id=? AND status IN ('reservado','pendente') ORDER BY id DESC LIMIT 1 FOR UPDATE");$st->execute([$jogador]);
        if($ativo=$st->fetchColumn()) {
            $chaves=array_column(bbItens($pdo,(int)$ativo),'evento_chave');sort($chaves);
            if($chaves!==$selecionados)throw new RuntimeException('Você já tem uma seleção em andamento. Abra o pagamento pendente para continuar ou alterar a seleção antes de gerar o PIX.');
            $pdo->commit();return (int)$ativo;
        }
        $opcoes=array_column(bbOpcoes($pdo,$jogador),null,'chave');$total=0;
        foreach($selecionados as $chave){$o=$opcoes[$chave]??null;if(!$o || !$o['disponivel'])throw new RuntimeException('Um dos eventos não está mais disponível ou já tem inscrição. Atualize sua seleção.');$total+=(int)round($o['valor']*100);}
        $pdo->prepare('INSERT INTO batebola_pedidos(jogador_id,valor,chave_pagamento,criado_em) VALUES(?,?,?,?)')->execute([$jogador,$total/100,'bb-combo-'.bin2hex(random_bytes(20)),especialAgora()]);$id=(int)$pdo->lastInsertId();
        foreach($selecionados as $chave){$o=$opcoes[$chave];$pdo->prepare('INSERT INTO batebola_pedido_itens(pedido_id,evento_chave,tipo,data_evento,especial_id,titulo,horario,valor) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$chave,$o['tipo'],$o['data_evento'],$o['especial_id'],$o['titulo'],$o['horario'],$o['valor']]);}
        $pdo->commit();return $id;
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    finally{$pdo->query("SELECT RELEASE_LOCK('mpg_bb_reservas')");}
}
/** Um pagamento confirmado quita todas as inscrições ou nenhuma. */
function bbConfirmarPedido(PDO $pdo,int $id,array $payment): bool {
    if(($payment['status']??'')!=='approved' || ($payment['currency_id']??'')!=='BRL' || (int)($payment['metadata']['batebola_pedido_id']??0)!==$id || ($payment['external_reference']??'')!=='bb-pedido-'.$id || empty($payment['id']))return false;
    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare('SELECT * FROM batebola_pedidos WHERE id=? FOR UPDATE');$st->execute([$id]);$p=$st->fetch();
        if(!$p || $p['status']!=='pendente' || (int)round((float)$payment['transaction_amount']*100)!==(int)round((float)$p['valor']*100) || ($p['mp_payment_id'] && (string)$p['mp_payment_id']!==(string)$payment['id'])){$pdo->rollBack();return false;}
        $itens=bbItens($pdo,$id);$sum=0;foreach($itens as $i)$sum+=(int)round((float)$i['valor']*100);
        if(!$itens || $sum!==(int)round((float)$p['valor']*100))throw new RuntimeException('Total dos itens inconsistente.');
        foreach($itens as $i) {
            $table=$i['tipo']==='domingo'?'batebola_inscricoes':'batebola_especial_inscricoes';$col=$i['tipo']==='domingo'?'data_evento':'evento_id';$key=$i['especial_id']??$i['data_evento'];
            $st=$pdo->prepare("SELECT id,status FROM $table WHERE $col=? AND jogador_id=? FOR UPDATE");$st->execute([$key,$p['jogador_id']]);$exist=$st->fetch();
            if($exist && $exist['status']!=='cancelado')throw new RuntimeException('Conflito de inscrição; conciliar pagamento '.$id);
            if($exist){$ins=(int)$exist['id'];$pdo->prepare("UPDATE $table SET status='pago',valor=?,pago_em=?,mp_payment_id=NULL,pix_qr_code=NULL,pix_qr_code_base64=NULL WHERE id=?")->execute([$i['valor'],especialAgora(),$ins]);}
            elseif($i['tipo']==='domingo'){$pdo->prepare("INSERT INTO batebola_inscricoes(jogador_id,data_evento,valor,status,pago_em) VALUES(?,?,?,'pago',?)")->execute([$p['jogador_id'],$key,$i['valor'],especialAgora()]);$ins=(int)$pdo->lastInsertId();}
            else{$pdo->prepare("INSERT INTO batebola_especial_inscricoes(jogador_id,evento_id,valor,status,pago_em,chave_pagamento) VALUES(?,?,?,'pago',?,?)")->execute([$p['jogador_id'],$key,$i['valor'],especialAgora(),'pedido-'.$id.'-item-'.$i['id']]);$ins=(int)$pdo->lastInsertId();}
            $pdo->prepare('UPDATE batebola_pedido_itens SET inscricao_id=? WHERE id=?')->execute([$ins,$i['id']]);
        }
        $pdo->prepare("UPDATE batebola_pedidos SET status='pago',mp_payment_id=?,pago_em=? WHERE id=?")->execute([(string)$payment['id'],especialAgora(),$id]);$pdo->commit();return true;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
