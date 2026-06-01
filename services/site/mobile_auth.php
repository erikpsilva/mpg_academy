<?php
/**
 * Helper: valida Bearer token e retorna aluno.
 * Inclua nos endpoints mobile protegidos.
 */

function getMobileToken(): ?string {
    // Apache pode passar o Authorization de formas diferentes
    $sources = [
        $_SERVER['HTTP_AUTHORIZATION']          ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        function_exists('apache_request_headers')
            ? (apache_request_headers()['Authorization'] ?? '')
            : '',
    ];

    foreach ($sources as $auth) {
        if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
            return trim($m[1]);
        }
    }
    return null;
}

function getMobileAluno(): ?array {
    $token = getMobileToken();
    if (!$token || strlen($token) !== 64) return null;

    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT a.* FROM alunos a
        JOIN mobile_tokens mt ON mt.aluno_id = a.id
        WHERE mt.token = ? AND mt.expire_at > NOW() AND a.status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mobileCors(): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

function mobileAuth(): array {
    mobileCors();
    require_once dirname(__FILE__, 3) . '/config/database.php';
    $aluno = getMobileAluno();
    if (!$aluno) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
        exit;
    }
    return $aluno;
}
