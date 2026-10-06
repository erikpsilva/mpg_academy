<?php
if(session_status()===PHP_SESSION_NONE)session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__,3).'/config/api_security.php'; validateApiAccess($ALLOWED_ORIGINS);
if($_SERVER['REQUEST_METHOD']!=='POST'||empty($_SESSION['usuario'])){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Acesso não autorizado.']);exit;}
require_once dirname(__FILE__,3).'/config/database.php';
require_once dirname(__FILE__,3).'/config/app.php';
require_once dirname(__FILE__,3).'/services/whatsapp/zapi.php';
$nome=trim($_POST['nome']??'');$fone=preg_replace('/\D/','',$_POST['whatsapp']??'');if(strlen($fone)>11&&substr($fone,0,2)==='55')$fone=substr($fone,2);$turmaId=(int)($_POST['turma_id']??0);$extra=trim($_POST['mensagem']??'');
if($nome===''||!in_array(strlen($fone),[10,11],true)||$turmaId<=0){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Informe nome, WhatsApp com DDD e turma.']);exit;}
$pdo=getDbConnection();$st=$pdo->prepare("SELECT nome FROM turmas WHERE id=? AND status='ativa'");$st->execute([$turmaId]);$turma=$st->fetchColumn();
if(!$turma){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Turma não encontrada ou inativa.']);exit;}
$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
$pdo->prepare("INSERT INTO cadastro_convites(token_hash,turma_id,nome,whatsapp,enviado_por_usuario_id,expira_em) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))")
    ->execute([$hash,$turmaId,$nome,$fone,(int)$_SESSION['usuario']['id']]);
$url=appBaseUrl().'/cadastro?convite='.$token;$primeiro=explode(' ',$nome)[0];
$msg="Olá, {$primeiro}! 👋\n\nSua vaga na turma *{$turma}* da MPG Academy está pronta. Use o link individual abaixo para concluir seu cadastro e escolher o uniforme:\n\n{$url}\n\nO link expira em 7 dias e pode ser usado uma vez.";
if($extra!=='')$msg.="\n\n{$extra}";
if(!sendWhatsApp(formatPhoneZapi($fone),$msg)){$pdo->prepare("DELETE FROM cadastro_convites WHERE token_hash=?")->execute([$hash]);http_response_code(502);echo json_encode(['success'=>false,'message'=>'Não foi possível enviar pelo WhatsApp.']);exit;}
echo json_encode([
    'success'=>true,
    'message'=>'Link de cadastro enviado para o WhatsApp, vinculado à turma '.$turma.'.',
    'link'=>$url,
]);
