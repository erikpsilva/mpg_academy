<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

$id         = (int) ($_POST['id']          ?? 0);
$descricao  = trim($_POST['descricao']     ?? '');
$credor     = trim($_POST['credor']        ?? '') ?: null;
$categoria  = trim($_POST['categoria']     ?? 'outros');
$observacao = trim($_POST['observacao']    ?? '') ?: null;
$valorRaw   = trim($_POST['valor_total']   ?? '');
$numParcStr = trim($_POST['num_parcelas']  ?? '');
$dataInicio = trim($_POST['data_inicio']   ?? '');
$regenerar  = !empty($_POST['regenerar']);

if ($id <= 0 || !$descricao) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória.']);
    exit;
}

$categoriasValidas = ['outros','equipamento','reforma','aluguel','administrativo'];
if (!in_array($categoria, $categoriasValidas)) $categoria = 'outros';

$valorTotal  = $valorRaw !== ''   ? (float) str_replace(['.', ','], ['', '.'], $valorRaw) : null;
$numParcelas = $numParcStr !== '' ? max(1, (int) $numParcStr) : null;
if ($dataInicio && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) $dataInicio = '';

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$check = $pdo->prepare("SELECT id FROM dividas WHERE id = ?");
$check->execute([$id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Dívida não encontrada.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Build UPDATE dynamically
    $set    = ['descricao = ?', 'credor = ?', 'categoria = ?', 'observacao = ?'];
    $params = [$descricao, $credor, $categoria, $observacao];

    if ($valorTotal !== null && $valorTotal > 0) {
        $set[]    = 'valor_total = ?';
        $params[] = $valorTotal;
    }
    if ($numParcelas !== null) {
        $set[]    = 'num_parcelas = ?';
        $params[] = $numParcelas;
    }
    if ($valorTotal !== null && $numParcelas !== null && $valorTotal > 0 && $numParcelas > 0) {
        $set[]    = 'valor_parcela = ?';
        $params[] = round($valorTotal / $numParcelas, 2);
    }
    if ($dataInicio !== '') {
        $set[]    = 'data_inicio = ?';
        $params[] = $dataInicio;
    }

    $params[] = $id;
    $pdo->prepare('UPDATE dividas SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);

    // Regenerate pending installments if requested
    if ($regenerar && $valorTotal > 0 && $numParcelas > 0 && $dataInicio !== '') {
        // Keep paid/advanced installments; delete pending ones
        $stPagas = $pdo->prepare("
            SELECT numero, valor FROM parcelas_dividas
            WHERE divida_id = ? AND status IN ('pago','adiantado')
            ORDER BY numero
        ");
        $stPagas->execute([$id]);
        $pagas       = $stPagas->fetchAll(PDO::FETCH_ASSOC);
        $nPagas      = count($pagas);
        $valorJaPago = (float) array_sum(array_column($pagas, 'valor'));

        $pdo->prepare("DELETE FROM parcelas_dividas WHERE divida_id = ? AND status = 'pendente'")->execute([$id]);

        $nNovos = $numParcelas - $nPagas;
        if ($nNovos > 0) {
            $valorRestante = max(0, $valorTotal - $valorJaPago);
            $centavos      = (int) round($valorRestante * 100);
            $parcCentavos  = intdiv($centavos, $nNovos);
            $restoCentavos = $centavos - $parcCentavos * $nNovos;

            $dtBase = new DateTime($dataInicio);
            $stNew  = $pdo->prepare("
                INSERT INTO parcelas_dividas (divida_id, numero, valor, data_vencimento)
                VALUES (?, ?, ?, ?)
            ");
            for ($i = 0; $i < $nNovos; $i++) {
                $parcNum  = $nPagas + $i + 1;
                $venc     = clone $dtBase;
                if ($nPagas + $i > 0) $venc->modify('+' . ($nPagas + $i) . ' month');
                $valorParc = $i === $nNovos - 1
                    ? round(($parcCentavos + $restoCentavos) / 100, 2)
                    : round($parcCentavos / 100, 2);
                $stNew->execute([$id, $parcNum, $valorParc, $venc->format('Y-m-d')]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar dívida.']);
}
