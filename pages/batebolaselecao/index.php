<?php
if(empty($_SESSION['jogador']['id'])){header('Location: '.BASE_URL.'/batebola?selecionar=1');exit;}
require_once ROOT.'/config/database.php';require_once ROOT.'/config/batebola_checkout.php';
$pdo=getDbConnection();$jogadorId=(int)$_SESSION['jogador']['id'];$pronto=bbCheckoutDisponivel($pdo);
$_SESSION['bb_checkout_csrf']=$_SESSION['bb_checkout_csrf']??bin2hex(random_bytes(24));
$pedidoId=(int)($_GET['pedido']??0);$pedido=$pronto && $pedidoId?bbPedido($pdo,$pedidoId,$jogadorId):null;
$opcoes=$pronto?bbOpcoes($pdo,$jogadorId):[];$ativo=null;
if($pronto){$st=$pdo->prepare("SELECT id,status FROM batebola_pedidos WHERE jogador_id=? AND status IN ('reservado','pendente') ORDER BY id DESC LIMIT 1");$st->execute([$jogadorId]);$ativo=$st->fetch();}
$inicioErro='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['acao']??'')==='iniciar') {
    try {
        if(!hash_equals($_SESSION['bb_checkout_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException('Atualize a página e tente novamente.');
        if(!$pronto)throw new RuntimeException('A seleção de eventos está sendo atualizada. Tente novamente em instantes.');
        $disponiveis=array_values(array_filter($opcoes,fn($o)=>$o['disponivel']));
        if($ativo){header('Location: '.BASE_URL.'/batebolaselecao?pedido='.(int)$ativo['id']);exit;}
        if(count($disponiveis)===1){$novo=bbPreparar($pdo,$jogadorId,[$disponiveis[0]['chave']]);header('Location: '.BASE_URL.'/batebolaselecao?pedido='.$novo);exit;}
        if(!$disponiveis)$inicioErro='Não há novos eventos disponíveis para sua inscrição agora. Confira suas vagas confirmadas e os pagamentos pendentes abaixo.';
    }catch(RuntimeException $e){$inicioErro=$e->getMessage();}
}
?>
<!doctype html><html lang="pt-BR"><head><title>Escolha seus bate-bolas | MPG Academy</title><?php include ROOT.'/includes/assets.php'; ?><link rel="stylesheet" href="<?= BASE_URL ?>/styles/batebola-especial.css?v=2"><link rel="stylesheet" href="<?= BASE_URL ?>/styles/batebola-checkout.css?v=1"></head><body class="bbCheckoutPage">
<?php include ROOT.'/includes/header/header.php'; ?>
<main class="bbEspecial bbCheckout" data-endpoint="<?= BASE_URL ?>/services/site/batebola_checkout.php" data-csrf="<?= especialHtml($_SESSION['bb_checkout_csrf']) ?>" data-pedido="<?= $pedido?(int)$pedido['id']:0 ?>">
<div class="container"><a class="bbCheckout__back" href="<?= BASE_URL ?>/batebolainicio">← Minha área do bate-bola</a>
<header class="bbCheckout__heading"><span class="bbEspecial__eyebrow">SEU PRÓXIMO JOGO</span><h1><?= $pedido?'Pagamento dos bate-bolas':'Em quais bate-bolas você vai jogar?' ?></h1><p><?= $pedido?'Confira sua seleção. Um único PIX confirma todas as vagas deste pedido.':'Marque um ou mais encontros. Você paga o total em um único PIX e garante uma vaga em cada evento selecionado.' ?></p></header>
<p id="bbCheckoutError" class="bbEspecial__error" role="alert" tabindex="-1" <?= $inicioErro?'':'hidden' ?>><?= especialHtml($inicioErro) ?></p>
<?php if(!$pronto): ?><p class="bbEspecial__notice">Estamos preparando a seleção de eventos. Tente novamente em instantes.</p>
<?php elseif($pedidoId && !$pedido): ?><p class="bbEspecial__error">Pedido não encontrado.</p><a class="bbEspecial__button" href="<?= BASE_URL ?>/batebolaselecao">Voltar à seleção</a>
<?php elseif($pedido): ?>
<div class="bbCheckout__layout"><section class="bbEspecial__panel"><h2>Eventos selecionados</h2>
<?php foreach(bbItens($pdo,$pedidoId) as $item): ?><article class="bbCheckout__item"><div><span class="bbEspecial__badge"><?= $item['tipo']==='domingo'?'Tradicional':'Especial' ?></span><h3><?= especialHtml($item['titulo']) ?></h3><p><?= especialHtml($item['horario']) ?></p></div><strong>R$ <?= number_format((float)$item['valor'],2,',','.') ?></strong></article><?php endforeach; ?>
<div class="bbCheckout__total"><span>Total do pedido</span><strong>R$ <?= number_format((float)$pedido['valor'],2,',','.') ?></strong></div>
<p><?= especialHtml(BATEBOLA_LOCAL_NOME.' — '.BATEBOLA_LOCAL_ENDERECO) ?></p></section>
<section class="bbEspecial__panel">
<?php if($pedido['status']==='pago'): ?><h2>Vagas confirmadas! ✓</h2><p>Seu pagamento confirmou a inscrição em todos os eventos listados. As listas e os times continuam separados por encontro.</p><a class="bbEspecial__button" href="<?= BASE_URL ?>/batebolainicio">Ver meus bate-bolas</a>
<?php elseif($pedido['status']==='cancelado'): ?><h2>Seleção cancelada</h2><a class="bbEspecial__button" href="<?= BASE_URL ?>/batebolaselecao">Selecionar novamente</a>
<?php else: ?><h2>Pagar com PIX</h2><p>Valor total: <strong>R$ <?= number_format((float)$pedido['valor'],2,',','.') ?></strong></p>
<button class="bbEspecial__button" type="button" data-checkout-action="pagar"><?= $pedido['pix_qr_code']?'Mostrar PIX':'Gerar PIX do total' ?></button>
<div id="bbCheckoutPix" hidden><img id="bbCheckoutQr" class="bbEspecial__qr" alt="QR Code para pagamento dos eventos"><label for="bbCheckoutCode">PIX copia e cola</label><textarea id="bbCheckoutCode" readonly rows="4"></textarea><button id="bbCheckoutCopy" type="button" class="bbEspecial__button bbEspecial__button--secondary">Copiar código PIX</button><p role="status">Aguardando confirmação do pagamento...</p></div>
<div class="bbEspecial__actions"><button class="bbEspecial__button bbEspecial__button--secondary" type="button" data-checkout-action="status">Já paguei — verificar</button><?php if($pedido['status']==='reservado'): ?><button class="bbEspecial__button bbEspecial__button--secondary" type="button" data-checkout-action="cancelar">Alterar seleção</button><?php endif; ?></div>
<p>Você tem 15 minutos para gerar o PIX desta seleção. Depois de gerado, utilize este mesmo pagamento para evitar cobranças duplicadas.</p>
<?php endif; ?></section></div>
<?php else: ?>
<?php if($ativo): ?><p class="bbEspecial__notice">Você tem uma seleção em andamento. <a href="<?= BASE_URL ?>/batebolaselecao?pedido=<?= (int)$ativo['id'] ?>">Continuar pagamento ou revisar seleção →</a></p><?php endif; ?>
<form id="bbCheckoutSelect"><div class="bbCheckout__layout"><section class="bbCheckout__options" aria-label="Bate-bolas disponíveis">
<?php foreach($opcoes as $o): ?>
<article class="bbEspecial__card bbCheckout__option <?= !$o['disponivel']?'is-unavailable':'' ?>"><label>
<input type="checkbox" name="eventos[]" value="<?= especialHtml($o['chave']) ?>" data-cents="<?= (int)round($o['valor']*100) ?>" <?= !$o['disponivel']?'disabled':((int)($_GET['especial']??0) && $o['especial_id']===(int)$_GET['especial']?'checked':'') ?>>
<span><span class="bbEspecial__badge"><?= $o['tipo']==='domingo'?'Domingo tradicional':'Bate-bola especial' ?></span><strong class="bbCheckout__optionTitle"><?= especialHtml($o['titulo']) ?></strong><span class="bbCheckout__date"><?= especialHtml($o['horario']) ?></span><strong class="bbEspecial__price">R$ <?= number_format($o['valor'],2,',','.') ?></strong><span class="bbCheckout__date"><?php if($o['inscricao']==='pago'): ?>Sua vaga já está confirmada ✓<?php elseif($o['inscricao']==='pendente'): ?>Você já tem uma inscrição individual em andamento.<?php elseif(!$o['aberto']): ?>Inscrições fechadas<?php elseif(!$o['livres']): ?>Vagas esgotadas ou reservadas<?php else: ?><?= $o['livres'] ?> vagas disponíveis<?php endif; ?></span></span></label>
<?php if(in_array($o['inscricao'],['pendente','pago'],true)): ?><a class="bbCheckout__back" href="<?= BASE_URL ?>/<?= $o['tipo']==='domingo'?($o['inscricao']==='pago'?'batebolainicio':'batebolapagamento?legado=1'):'batebolaespecial?id='.$o['especial_id'].'&legado=1' ?>"><?= $o['inscricao']==='pago'?'Ver confirmação e times':'Retomar pagamento individual' ?></a><?php endif; ?><?php if($o['inscricao']==='pendente'): ?><button type="button" class="bbEspecial__button bbEspecial__button--secondary" data-cancel-individual="<?= especialHtml($o['chave']) ?>">Cancelar PIX individual e escolher novamente</button><?php endif; ?></article>
<?php endforeach; ?></section>
<aside class="bbEspecial__panel bbCheckout__summary"><h2>Sua seleção</h2><p id="bbCheckoutCount">Selecione os encontros ao lado.</p><div class="bbCheckout__total"><span>Total</span><strong id="bbCheckoutTotal">R$ 0,00</strong></div><button class="bbEspecial__button" type="submit" id="bbCheckoutContinue" disabled>Continuar para pagamento</button><p>Um único PIX para todos os encontros selecionados.</p><p>📍 <?= especialHtml(BATEBOLA_LOCAL_NOME) ?></p></aside></div></form>
<?php endif; ?></div></main>
<?php include ROOT.'/includes/footer/footer.php'; include ROOT.'/includes/scripts.php'; ?><script src="<?= BASE_URL ?>/scripts/batebola-checkout.js?v=2"></script></body></html>
