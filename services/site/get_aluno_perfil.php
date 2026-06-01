<?php
require_once __DIR__ . '/mobile_auth.php';
$aluno = mobileAuth();
unset($aluno['senha']);
echo json_encode(['success'=>true, 'data'=>$aluno]);
