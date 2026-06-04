<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (empty($_SESSION['usuario'])) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$descricao   = trim($_POST['descricao']    ?? '');
$credor      = trim($_POST['credor']       ?? '');
$categoria   = trim($_POST['categoria']    ?? 'outros');
$dataInicio  = trim($_POST['data_inicio']  ?? date('Y-m-d'));
$observacao  = trim($_POST['observacao']   ?? '');
$numParcelas = max(1, (int)($_POST['num_parcelas'] ?? 1));
$valorRaw    = trim($_POST['valor_total']  ?? '');
$valorTotal  = (float) str_replace(['.', ','], ['', '.'], $valorRaw);

if ($descricao === '' || $valorTotal <= 0) {
    echo json_encode(['success'=>false,'message'=>'Descrição e valor são obrigatórios.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) $dataInicio = date('Y-m-d');

// Calcula valor das parcelas (última absorve o centavo)
$centavos      = (int) round($valorTotal * 100);
$parcCentavos  = intdiv($centavos, $numParcelas);
$restoCentavos = $centavos - $parcCentavos * $numParcelas;
$valorParcela  = round($parcCentavos / 100, 2);

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    $stDivida = $pdo->prepare("
        INSERT INTO dividas (descricao, credor, categoria, valor_total, num_parcelas, valor_parcela, data_inicio, observacao)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stDivida->execute([$descricao, $credor ?: null, $categoria, $valorTotal, $numParcelas, $valorParcela, $dataInicio, $observacao ?: null]);
    $dividaId = (int) $pdo->lastInsertId();

    $stParcela = $pdo->prepare("
        INSERT INTO parcelas_dividas (divida_id, numero, valor, data_vencimento)
        VALUES (?, ?, ?, ?)
    ");

    $dtBase = new DateTime($dataInicio);
    for ($i = 0; $i < $numParcelas; $i++) {
        $venc = clone $dtBase;
        if ($i > 0) $venc->modify('+' . $i . ' month');
        // Última parcela recebe o resto dos centavos
        $valorParc = $i === $numParcelas - 1
            ? round(($parcCentavos + $restoCentavos) / 100, 2)
            : $valorParcela;
        $stParcela->execute([$dividaId, $i + 1, $valorParc, $venc->format('Y-m-d')]);
    }

    $pdo->commit();

    // ── Processa anexos enviados junto com a criação ──────────────────────────
    $anexosErros = [];
    $files = $_FILES['anexos'] ?? [];
    if (!empty($files['name'][0])) {
        $allowedMimes = [
            'application/pdf',
            'image/jpeg','image/png','image/webp','image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ];
        $uploadDir = dirname(__FILE__, 3) . '/uploads/dividas/' . $dividaId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);
            if (!in_array($mime, $allowedMimes) || $files['size'][$i] > 10 * 1024 * 1024) {
                $anexosErros[] = $files['name'][$i];
                continue;
            }
            $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = uniqid('anx_') . ($ext ? '.' . strtolower($ext) : '');
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename)) {
                $pdo->prepare("INSERT INTO dividas_anexos (divida_id, nome_original, caminho, tipo_mime, tamanho) VALUES (?,?,?,?,?)")
                    ->execute([$dividaId, $files['name'][$i], 'uploads/dividas/' . $dividaId . '/' . $filename, $mime, $files['size'][$i]]);
            }
        }
    }

    echo json_encode(['success'=>true, 'id'=>$dividaId, 'anexos_erros'=>$anexosErros]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erro ao salvar dívida.']);
}
