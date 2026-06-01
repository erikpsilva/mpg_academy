<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__,3).'/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['success'=>false]);exit;}
if (empty($_SESSION['usuario'])){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Acesso não autorizado.']);exit;}

$id           = (int)($_POST['id'] ?? 0);
$nomeFantasia = trim($_POST['nome_fantasia'] ?? '');
$razaoSocial  = trim($_POST['razao_social']  ?? '');
$cnpj         = trim($_POST['cnpj']          ?? '');
$email        = trim($_POST['email']         ?? '');
$celular      = trim($_POST['celular']       ?? '');
$cep          = trim($_POST['cep']           ?? '');
$rua          = trim($_POST['rua']           ?? '');
$numero       = trim($_POST['numero']        ?? '');
$complemento  = trim($_POST['complemento']   ?? '');
$bairro       = trim($_POST['bairro']        ?? '');
$cidade       = trim($_POST['cidade']        ?? '');
$estado       = strtoupper(trim($_POST['estado'] ?? ''));
$observacao   = trim($_POST['observacao']    ?? '');
$status       = in_array($_POST['status']??'',['ativo','inativo']) ? $_POST['status'] : 'ativo';
$valorRaw     = trim($_POST['valor_patrocinio'] ?? '');
$valor        = $valorRaw !== '' ? (float)str_replace(['.', ','], ['', '.'], $valorRaw) : null;

if ($nomeFantasia === '') {
    echo json_encode(['success'=>false,'message'=>'Nome fantasia é obrigatório.']);
    exit;
}

require_once dirname(__FILE__,3).'/config/database.php';
$pdo = getDbConnection();

try {
    if ($id > 0) {
        $st = $pdo->prepare("
            UPDATE patrocinadores SET
                nome_fantasia=?,razao_social=?,cnpj=?,email=?,celular=?,
                cep=?,rua=?,numero=?,complemento=?,bairro=?,cidade=?,estado=?,
                valor_patrocinio=?,status=?,observacao=?
            WHERE id=?
        ");
        $st->execute([$nomeFantasia,$razaoSocial?:null,$cnpj?:null,$email?:null,$celular?:null,
                      $cep?:null,$rua?:null,$numero?:null,$complemento?:null,$bairro?:null,
                      $cidade?:null,$estado?:null,$valor,$status,$observacao?:null,$id]);

        // Atualiza todos os lançamentos existentes deste patrocinador (valor e descrição)
        if ($valor > 0) {
            $desc = 'Patrocínio — ' . $nomeFantasia;
            $pdo->prepare("
                UPDATE lancamentos_financeiros
                SET valor = ?, descricao = ?
                WHERE referencia_tipo = 'patrocinador' AND referencia_id = ?
            ")->execute([$valor, $desc, $id]);
        } else {
            // Valor zerado → remove os lançamentos automáticos do patrocinador
            $pdo->prepare("
                DELETE FROM lancamentos_financeiros
                WHERE referencia_tipo = 'patrocinador' AND referencia_id = ?
            ")->execute([$id]);
        }

    } else {
        $st = $pdo->prepare("
            INSERT INTO patrocinadores
                (nome_fantasia,razao_social,cnpj,email,celular,cep,rua,numero,complemento,bairro,cidade,estado,valor_patrocinio,status,observacao)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $st->execute([$nomeFantasia,$razaoSocial?:null,$cnpj?:null,$email?:null,$celular?:null,
                      $cep?:null,$rua?:null,$numero?:null,$complemento?:null,$bairro?:null,
                      $cidade?:null,$estado?:null,$valor,$status,$observacao?:null]);
        $id = (int)$pdo->lastInsertId();

        // Gera lançamento de receita automaticamente ao cadastrar novo patrocinador ativo com valor
        if ($valor > 0 && $status === 'ativo') {
            $competencia = date('Y-m');
            $desc = 'Patrocínio — ' . $nomeFantasia;
            try {
                $pdo->prepare("
                    INSERT IGNORE INTO lancamentos_financeiros
                        (competencia,data,tipo,categoria,descricao,valor,origem,referencia_tipo,referencia_id)
                    VALUES (?,CURDATE(),'receita','patrocinio',?,?,'auto','patrocinador',?)
                ")->execute([$competencia,$desc,$valor,$id]);
            } catch (PDOException) {}
        }
    }
    echo json_encode(['success'=>true,'id'=>$id]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erro ao salvar.']);
}
