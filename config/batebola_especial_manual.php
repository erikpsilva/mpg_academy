<?php
require_once __DIR__.'/batebola_checkout.php';

/** Executado sob a transação e o lock mpg_bb_reservas do controlador admin. */
function especialAdicionarPago(PDO $pdo, array $evento, int $jogador, bool $isento=false): void {
    if (!$pdo->inTransaction()) throw new LogicException('Transação obrigatória.');
    if (especialEstado($evento)==='encerrado') throw new RuntimeException('Evento encerrado: o histórico é somente leitura.');
    $id=(int)$evento['id'];
    $st=$pdo->prepare('SELECT id FROM jogadores_batebola WHERE id=? FOR UPDATE');
    $st->execute([$jogador]);
    if (!$st->fetchColumn()) throw new RuntimeException('Selecione um jogador cadastrado.');
    bbLimparReservas($pdo);
    if (bbCheckoutDisponivel($pdo)) {
        $st=$pdo->prepare("SELECT p.id FROM batebola_pedidos p JOIN batebola_pedido_itens i ON i.pedido_id=p.id WHERE p.jogador_id=? AND i.especial_id=? AND p.status IN ('reservado','pendente') LIMIT 1");
        $st->execute([$jogador,$id]);
        if ($st->fetchColumn()) throw new RuntimeException('Este jogador tem uma seleção ou PIX conjunto em andamento. Resolva esse pagamento antes de incluí-lo manualmente, para evitar cobrança duplicada.');
    }
    $st=$pdo->prepare('SELECT * FROM batebola_especial_inscricoes WHERE evento_id=? AND jogador_id=? FOR UPDATE');
    $st->execute([$id,$jogador]); $ins=$st->fetch(PDO::FETCH_ASSOC);
    if ($ins && $ins['status']==='pago') throw new RuntimeException('Este jogador já está confirmado como pago.');
    if ($ins && $ins['status']==='pendente' && (!empty($ins['mp_payment_id']) || !empty($ins['pix_qr_code']))) throw new RuntimeException('Este jogador tem um PIX em andamento. Atualize os pagamentos pendentes antes de fazer uma confirmação manual.');
    $st=$pdo->prepare("SELECT COUNT(*) FROM batebola_especial_inscricoes WHERE evento_id=? AND status IN ('pago','pendente') AND jogador_id<>?");
    $st->execute([$id,$jogador]);
    if ((int)$st->fetchColumn()+bbReservas($pdo,'especial:'.$id)>=(int)$evento['vagas']) throw new RuntimeException('Não há vagas disponíveis. As reservas de pagamentos pendentes também ocupam vagas.');
    $valor=$isento?0:$evento['valor'];
    if ($ins) {
        $pdo->prepare("UPDATE batebola_especial_inscricoes SET status='pago',valor=?,pago_em=?,presente=0,mp_payment_id=NULL,pix_qr_code=NULL,pix_qr_code_base64=NULL,chave_pagamento=? WHERE id=?")->execute([$valor,especialAgora(),'manual-'.bin2hex(random_bytes(20)),$ins['id']]);
    } else {
        $pdo->prepare("INSERT INTO batebola_especial_inscricoes(evento_id,jogador_id,valor,status,pago_em,chave_pagamento) VALUES(?,?,?,'pago',?,?)")->execute([$id,$jogador,$valor,especialAgora(),'manual-'.bin2hex(random_bytes(20))]);
    }
}

/** Isentos continuam confirmados (status pago), mas com valor zero. */
function especialEditarConfirmado(PDO $pdo,array $evento,int $inscricao,string $acao,string $tipo=''):void {
    if(!$pdo->inTransaction())throw new LogicException('Transação obrigatória.');
    if(especialEstado($evento)==='encerrado')throw new RuntimeException('Evento encerrado: histórico somente leitura.');
    $st=$pdo->prepare("SELECT * FROM batebola_especial_inscricoes WHERE id=? AND evento_id=? AND status='pago' FOR UPDATE");
    $st->execute([$inscricao,$evento['id']]);$ins=$st->fetch(PDO::FETCH_ASSOC);
    if(!$ins)throw new RuntimeException('Confirmação não encontrada neste evento.');
    if($acao==='excluir_confirmado'){
        // Remoção da lista, não estorno: conserva dados do pagamento.
        $pdo->prepare("UPDATE batebola_especial_inscricoes SET status='cancelado',presente=0 WHERE id=?")->execute([$inscricao]);
        $pdo->prepare('UPDATE batebola_especiais SET trocas=NULL WHERE id=?')->execute([$evento['id']]);
        return;
    }
    if($acao!=='tipo_confirmacao' || !in_array($tipo,['pago','isento'],true))throw new RuntimeException('Tipo de confirmação inválido.');
    if(strpos($ins['chave_pagamento'],'manual-')!==0 || !empty($ins['mp_payment_id']))throw new RuntimeException('Só inclusões manuais podem alternar entre pago e isento. Pagamentos online são preservados.');
    $pdo->prepare('UPDATE batebola_especial_inscricoes SET valor=? WHERE id=?')->execute([$tipo==='isento'?0:$evento['valor'],$inscricao]);
}
