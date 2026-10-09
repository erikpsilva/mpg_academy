<?php
require_once ROOT.'/config/database.php'; require_once ROOT.'/config/batebola_especial.php';
$pdo=getDbConnection(); $id=(int)($_GET['id']??0); $evento=especialDisponivel($pdo)?especialBuscar($pdo,$id):null;
if($evento && empty($_SESSION['jogador']['id'])) {
    header('Location: '.BASE_URL.'/batebola?especial_login='.$id);
    exit;
}
if($evento && !isset($_GET['legado'])) {
    header('Location: '.BASE_URL.'/batebolainicio');
    exit;
}
$_SESSION['especial_csrf']=$_SESSION['especial_csrf']??bin2hex(random_bytes(24));
$ins=null;
if($evento && !empty($_SESSION['jogador']['id'])) { $st=$pdo->prepare('SELECT status FROM batebola_especial_inscricoes WHERE evento_id=? AND jogador_id=?'); $st->execute([$id,(int)$_SESSION['jogador']['id']]); $ins=$st->fetch(); }
if(!$evento)http_response_code(404);
?>
<!doctype html><html lang="pt-BR"><head><title>Bate-bola especial | MPG Academy</title><?php include ROOT.'/includes/assets.php'; ?><link rel="stylesheet" href="<?= BASE_URL ?>/styles/batebola-especial.css?v=2"><link rel="stylesheet" href="<?= BASE_URL ?>/styles/batebola-checkout.css?v=1"></head><body class="bbCheckoutPage">
<?php include ROOT.'/includes/header/header.php'; ?>
<main class="bbEspecial bbEspecial__checkout" style="padding-top:130px"><a class="bbEspecial__button bbEspecial__button--secondary" href="<?= BASE_URL ?>/<?= empty($_SESSION['jogador'])?'batebola':'batebolainicio' ?>">Voltar ao bate-bola</a>
<?php if(!$evento): ?><p class="bbEspecial__error">Evento não encontrado.</p><?php else: ?>
<section class="bbEspecial__panel" style="margin-top:20px"><span class="bbEspecial__eyebrow">BATE-BOLA ESPECIAL</span><h1><?= especialHtml($evento['titulo']) ?></h1><p><?= especialHtml(especialData($evento)) ?></p><p><?= especialHtml(BATEBOLA_LOCAL_NOME.' — '.BATEBOLA_LOCAL_ENDERECO) ?></p><strong class="bbEspecial__price">R$ <?= number_format((float)$evento['valor'],2,',','.') ?> por pessoa</strong><p>Esta inscrição vale exclusivamente para este evento especial.</p>
<?php if(empty($_SESSION['jogador'])): ?><p>Entre com seu cadastro do bate-bola para garantir sua vaga.</p><a class="bbEspecial__button" href="<?= BASE_URL ?>/batebola">Entrar na minha conta</a>
<?php else: ?>
<div id="specialPayment" data-endpoint="<?= BASE_URL ?>/services/site/batebola_especial.php" data-id="<?= $id ?>" data-csrf="<?= especialHtml($_SESSION['especial_csrf']) ?>">
<p id="specialError" class="bbEspecial__error" role="alert" tabindex="-1" hidden></p>
<?php if(($ins['status']??'')==='pago'): ?><p class="bbEspecial__notice">Sua vaga está confirmada! Confira abaixo os times.</p>
<?php elseif(especialAberto($evento)): ?><button type="button" class="bbEspecial__button" data-special-pay>Garantir minha vaga — pagar com PIX</button><p>O pagamento confirma sua inscrição. Vagas com PIX em andamento ficam reservadas.</p>
<?php else: ?><p class="bbEspecial__notice"><?= especialEstado($evento)==='encerrado'?'Este evento foi encerrado.':'As inscrições estão fechadas.' ?></p><?php endif; ?>
<?php if(($ins['status']??'')==='pendente'): ?><button type="button" class="bbEspecial__button bbEspecial__button--secondary" data-special-status>Já paguei — verificar confirmação</button><?php endif; ?>
<div id="specialPix" hidden><h2>Pague com PIX</h2><img id="specialQr" class="bbEspecial__qr" alt="QR Code PIX"><label for="specialCode">PIX copia e cola</label><textarea id="specialCode" rows="4" readonly></textarea><div class="bbEspecial__actions"><button type="button" class="bbEspecial__button" id="specialCopy">Copiar código PIX</button><button type="button" class="bbEspecial__button bbEspecial__button--secondary" data-special-status>Já paguei — verificar</button></div><p role="status">Aguardando pagamento. Sua confirmação será atualizada automaticamente.</p></div>
</div><?php $times=especialTimes($pdo,$evento); include ROOT.'/includes/batebola_especial_times.php'; ?>
<?php endif; ?></section><?php endif; ?></main>
<?php include ROOT.'/includes/footer/footer.php'; include ROOT.'/includes/scripts.php'; ?><script src="<?= BASE_URL ?>/scripts/batebola-especial.js?v=1"></script></body></html>
