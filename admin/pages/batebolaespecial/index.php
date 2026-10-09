<?php
require ROOT.'/admin/includes/auth_check.php';
require_once ROOT.'/config/database.php';
require_once ROOT.'/config/batebola_especial.php';
require_once ROOT.'/config/batebola_checkout.php';
$pdo=getDbConnection(); $pronto=especialDisponivel($pdo); $erro='';
$_SESSION['especial_admin_csrf']=$_SESSION['especial_admin_csrf']??bin2hex(random_bytes(24));
$csrf=$_SESSION['especial_admin_csrf']; $admin=($_SESSION['usuario']['nivel_acesso']??'')==='admin';
if($pronto) require ROOT.'/admin/services/batebola_especial.php';
$eventos=$pronto?$pdo->query('SELECT * FROM batebola_especiais ORDER BY inicio DESC')->fetchAll():[];
$id=(int)($_GET['id']??0); $evento=$pronto && $id?especialBuscar($pdo,$id):null;
$aba=in_array($_GET['aba']??'',['informacoes','presenca','times'],true)?$_GET['aba']:'informacoes';
$inscritos=$evento?especialInscritos($pdo,$id):[];
$comboPendentes=[];
if($evento && bbCheckoutDisponivel($pdo)) {
    bbLimparReservas($pdo);
    $st=$pdo->prepare("SELECT j.nome,i.valor,p.status FROM batebola_pedido_itens i JOIN batebola_pedidos p ON p.id=i.pedido_id JOIN jogadores_batebola j ON j.id=p.jogador_id WHERE i.especial_id=? AND p.status IN ('reservado','pendente') ORDER BY j.nome");$st->execute([$id]);$comboPendentes=$st->fetchAll();
}
$pagos=array_values(array_filter($inscritos,fn($i)=>$i['status']==='pago'));
$editavel=$admin && (!$evento || especialEstado($evento)!=='encerrado');
$jogadoresDisponiveis=[];
if($evento && $editavel && $aba==='presenca') {
    $st=$pdo->prepare("SELECT j.id,j.nome FROM jogadores_batebola j WHERE NOT EXISTS (SELECT 1 FROM batebola_especial_inscricoes i WHERE i.jogador_id=j.id AND i.evento_id=? AND i.status='pago') ORDER BY j.nome,j.id");
    $st->execute([$id]); $jogadoresDisponiveis=$st->fetchAll();
}
function especialCamposAdmin(int $id,string $csrf,string $acao): void { echo '<input type="hidden" name="evento_id" value="'.$id.'"><input type="hidden" name="csrf" value="'.especialHtml($csrf).'"><input type="hidden" name="acao" value="'.especialHtml($acao).'">'; }
?>
<!doctype html><html lang="pt-BR"><head><title>Bate-bola especial | Admin</title>
<?php include ROOT.'/admin/includes/assets.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/batebola-especial.css?v=4"></head><body>
<?php include ROOT.'/admin/includes/header/header.php'; ?>
<div class="adminLayout"><?php include ROOT.'/admin/includes/sidebar/sidebar.php'; ?><main class="adminLayout__content bbEspecial">
<header class="bbEspecial__head"><div><span class="bbEspecial__eyebrow">ENCONTROS EXTRAS</span><h1>Bate-bola especial</h1><p>Eventos independentes do domingo tradicional. Confirmados e times em um só lugar.</p></div><a class="bbEspecial__button" href="<?= BASE_URL ?>/admin/batebolaespecial?novo=1">Criar evento</a></header>
<?php if(!$pronto): ?><p class="bbEspecial__notice">Aplique a migração database/2026-10-08_batebola_especial.sql para habilitar os eventos especiais.</p><?php else: ?>
<?php if($erro): ?><p class="bbEspecial__error" role="alert" tabindex="-1"><?= especialHtml($erro) ?></p><?php elseif(isset($_GET['salvo'])): ?><p class="bbEspecial__notice" role="status">Alteração salva.</p><?php endif; ?>
<div class="bbEspecial__layout"><aside class="bbEspecial__panel"><h2>Seus eventos</h2>
<?php if(!$eventos): ?><p>Nenhum evento criado ainda.</p><?php endif; ?>
<?php foreach($eventos as $e): ?><a class="bbEspecial__eventLink <?= $id==(int)$e['id']?'is-active':'' ?>" href="?id=<?= (int)$e['id'] ?>"><strong><?= especialHtml($e['titulo']) ?></strong><small><?= especialHtml(especialData($e)) ?></small><span class="bbEspecial__badge"><?= especialHtml(especialEstado($e)) ?></span></a><?php endforeach; ?>
</aside><section class="bbEspecial__panel">
<?php if($evento): ?><h2><?= especialHtml($evento['titulo']) ?></h2><p><?= especialHtml(especialData($evento)) ?></p>
<div class="bbEspecial__stats"><div><strong><?= count($pagos) ?>/<?= (int)$evento['vagas'] ?></strong><span>confirmados</span></div><div><strong><?= count(array_filter($pagos,fn($i)=>(float)$i['valor']===0.0)) ?></strong><span>isentos</span></div><div><strong>R$ <?= number_format(array_sum(array_column($pagos,'valor')),2,',','.') ?></strong><span>valor dos confirmados</span></div></div>
<nav class="bbEspecial__tabs" aria-label="Gestão do evento"><?php foreach(['informacoes'=>'Informações','presenca'=>'Lista de confirmados','times'=>'Sorteio dos times'] as $key=>$label): ?><a class="<?= $aba===$key?'is-active':'' ?>" href="?id=<?= $id ?>&aba=<?= $key ?>" <?= $aba===$key?'aria-current="page"':'' ?>><?= $label ?></a><?php endforeach; ?></nav><?php endif; ?>
<?php if(!$evento || $aba==='informacoes'): ?>
<h2><?= $evento?'Dados do evento':'Criar bate-bola especial' ?></h2>
<?php if($evento && ($inscritos || $comboPendentes)): ?><p class="bbEspecial__notice">Já existem inscrições ou reservas: data, horário, valor e vagas estão preservados.</p><?php endif; ?>
<form method="post" class="bbEspecial__form" data-special-form><?php especialCamposAdmin($id,$csrf,'salvar'); ?>
<fieldset <?= !$editavel || ($evento && ($inscritos || $comboPendentes))?'disabled':'' ?>><div class="bbEspecial__grid">
<label class="bbEspecial__wide">Nome do evento<input name="titulo" required maxlength="120" value="<?= especialHtml($_POST['titulo']??$evento['titulo']??'Bate-bola especial') ?>"></label>
<label>Data<input type="date" name="data" required value="<?= especialHtml($_POST['data']??($evento?substr($evento['inicio'],0,10):'')) ?>"></label>
<label>Início<input type="time" name="inicio" required value="<?= especialHtml($_POST['inicio']??($evento?substr($evento['inicio'],11,5):'19:00')) ?>"></label>
<label>Término<input type="time" name="fim" required value="<?= especialHtml($_POST['fim']??($evento?substr($evento['fim'],11,5):'21:00')) ?>"></label>
<label>Valor por pessoa (R$)<input type="number" name="valor" min="1" max="9999.99" step="0.01" required value="<?= especialHtml($_POST['valor']??$evento['valor']??'') ?>"></label>
<label>Vagas (até 4 times de 6)<input type="number" name="vagas" min="1" max="24" required value="<?= especialHtml($_POST['vagas']??$evento['vagas']??24) ?>"></label></div>
<p>Local: <?= especialHtml(BATEBOLA_LOCAL_NOME) ?>. Ao criar, as inscrições ficam disponíveis até o início do evento.</p>
<button class="bbEspecial__button" type="submit"><?= $evento?'Salvar alterações':'Criar e abrir inscrições' ?></button></fieldset></form>
<?php if($evento && $editavel): ?><form method="post" class="bbEspecial__actions"><?php especialCamposAdmin($id,$csrf,'status'); ?><label>Situação<select name="status"><option value="aberto" <?= $evento['status']==='aberto'?'selected':'' ?>>Inscrições abertas</option><option value="fechado" <?= $evento['status']==='fechado'?'selected':'' ?>>Inscrições fechadas</option><option value="encerrado">Encerrar evento</option></select></label><button class="bbEspecial__button bbEspecial__button--secondary">Atualizar situação</button></form><p>Encerrar mantém o histórico e não estorna pagamentos. Ao terminar o horário, o evento é encerrado automaticamente.</p><?php endif; ?>
<?php elseif($aba==='presenca'): ?>
<h2>Lista de confirmados</h2><p>Pagos e isentos têm vaga garantida e participam do sorteio. Excluir libera a vaga, mas não estorna pagamentos.</p>
<?php if($editavel): ?>
<form method="post" class="bbEspecial__form bbEspecial__manual" data-special-form>
<?php especialCamposAdmin($id,$csrf,'adicionar_pago'); ?>
<h3>Incluir jogador na lista</h3>
<p>Use para pagamentos recebidos por fora do site. A vaga será confirmada por R$ <?= number_format((float)$evento['valor'],2,',','.') ?>, sem gerar PIX. Escolha Isento para confirmar sem cobrança.</p>
<label for="buscarJogadorEspecial">Buscar jogador pelo nome<input id="buscarJogadorEspecial" type="search" placeholder="Digite para filtrar a lista" autocomplete="off" data-special-search></label>
<div class="bbEspecial__actions"><label for="jogadorEspecial">Jogador cadastrado<select id="jogadorEspecial" name="jogador_id" required><option value="">Selecione o jogador</option><?php foreach($jogadoresDisponiveis as $j): ?><option value="<?= (int)$j['id'] ?>"><?= especialHtml($j['nome']) ?> — #<?= (int)$j['id'] ?></option><?php endforeach; ?></select></label><label>Confirmação<select name="tipo_confirmacao"><option value="pago">Pago</option><option value="isento">Isento — sem cobrança</option></select></label><button type="submit" class="bbEspecial__button">Incluir na lista</button></div>
<small>Jogadores já confirmados não aparecem na lista. Pagamentos PIX em andamento precisam ser resolvidos antes da inclusão manual.</small>
</form>
<?php endif; ?>
<?php if($editavel): ?><form method="post"><?php especialCamposAdmin($id,$csrf,'conciliar'); ?><button class="bbEspecial__button bbEspecial__button--secondary">Atualizar pagamentos pendentes</button></form><?php endif; ?>
<?php if(!$inscritos && !$comboPendentes): ?><p>Ninguém se inscreveu neste evento ainda.</p><?php endif; ?>
<?php if($comboPendentes): ?><h3>Reservas aguardando pagamento</h3><div class="bbEspecial__people"><?php foreach($comboPendentes as $reserva): ?><article><div><strong><?= especialHtml($reserva['nome']) ?></strong><small><?= $reserva['status']==='reservado'?'Seleção em andamento':'PIX em andamento' ?> • R$ <?= number_format((float)$reserva['valor'],2,',','.') ?> neste evento</small></div></article><?php endforeach; ?></div><?php endif; ?>
<div class="bbEspecial__people"><?php foreach($inscritos as $i): if($i['status']==='cancelado')continue; $isento=$i['status']==='pago' && (float)$i['valor']===0.0; ?><article><div><strong><?= especialHtml($i['nome']) ?></strong><small><span class="bbEspecial__badge"><?= $isento?'Isento':($i['status']==='pago'?'Pago':'Pendente') ?></span> • R$ <?= number_format((float)$i['valor'],2,',','.') ?></small></div>
<?php if($i['status']==='pago' && $editavel): ?><div class="bbEspecial__rowActions">
<?php if(strpos($i['chave_pagamento'],'manual-')===0 && empty($i['mp_payment_id'])): ?><form method="post"><?php especialCamposAdmin($id,$csrf,'tipo_confirmacao'); ?><input type="hidden" name="inscricao_id" value="<?= (int)$i['id'] ?>"><label>Confirmação de <?= especialHtml($i['nome']) ?><select name="tipo_confirmacao"><option value="pago" <?= !$isento?'selected':'' ?>>Pago</option><option value="isento" <?= $isento?'selected':'' ?>>Isento</option></select></label><button class="bbEspecial__button bbEspecial__button--secondary">Salvar</button></form><?php else: ?><small>Pagamento online</small><?php endif; ?>
<form method="post" data-special-delete><?php especialCamposAdmin($id,$csrf,'excluir_confirmado'); ?><input type="hidden" name="inscricao_id" value="<?= (int)$i['id'] ?>"><button class="bbEspecial__button bbEspecial__button--danger">Excluir</button></form>
</div><?php endif; ?></article><?php endforeach; ?></div>
<?php else: ?>
<h2>Times equilibrados</h2><p>Sorteio entre todos os confirmados, pagos e isentos, usando nível, altura e composição dos times, como no bate-bola tradicional.</p>
<?php if($editavel): ?><div class="bbEspecial__actions"><form method="post"><?php especialCamposAdmin($id,$csrf,'sortear'); ?><button class="bbEspecial__button" <?= !$pagos?'disabled':'' ?>><?= $evento['seed']?'Sortear novamente':'Sortear times' ?></button></form><form method="post"><?php especialCamposAdmin($id,$csrf,'limpar'); ?><button class="bbEspecial__button bbEspecial__button--secondary">Limpar sorteio</button></form></div><?php endif; ?>
<?php $times=especialTimes($pdo,$evento); include ROOT.'/includes/batebola_especial_times.php'; ?>
<?php if($editavel && count($times)>1): ?><form method="post" class="bbEspecial__form"><?php especialCamposAdmin($id,$csrf,'trocar'); ?><h3>Ajustar jogadores entre times</h3><div class="bbEspecial__grid"><?php foreach(['jogador_a'=>'Primeiro jogador','jogador_b'=>'Segundo jogador'] as $key=>$label): ?><label><?= $label ?><select name="<?= $key ?>" required><option value="">Selecione</option><?php foreach($times as $time): foreach($time['jogadores'] as $j): ?><option value="<?= (int)$j['id'] ?>"><?= especialHtml($time['cor'].' — '.$j['nome']) ?></option><?php endforeach; endforeach; ?></select></label><?php endforeach; ?></div><button class="bbEspecial__button bbEspecial__button--secondary">Trocar jogadores</button></form><?php endif; ?>
<?php endif; ?></section></div><?php endif; ?></main></div>
<?php include ROOT.'/admin/includes/scripts.php'; ?><script src="<?= BASE_URL ?>/scripts/batebola-especial.js?v=3"></script></body></html>
