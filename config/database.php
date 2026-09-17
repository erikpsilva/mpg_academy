<?php

require_once __DIR__ . '/app.php';

$dbConfig = APP_IS_LOCAL
    ? [
        'host' => 'localhost',
        'name' => 'mpgacademy_mpg_db',
        'user' => 'root',
        'pass' => '',
    ]
    : [
        // KingHost nao aceita 'localhost': o MySQL roda em outro servidor.
        // Host alternativo, caso o principal falhe: mysql65-farm2.uni5.net
        'host' => 'mysql.mpgacademy.com.br',
        'name' => 'mpgacademy',
        'user' => 'mpgacademy',
        'pass' => 'Theking389518',
    ];

define('DB_HOST', getenv('MPG_DB_HOST') ?: $dbConfig['host']);
define('DB_NAME', getenv('MPG_DB_NAME') ?: $dbConfig['name']);
define('DB_USER', getenv('MPG_DB_USER') ?: $dbConfig['user']);
define('DB_PASS', getenv('MPG_DB_PASS') ?: $dbConfig['pass']);

function getDbConnection() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log(sprintf(
            '[MPG DB] env=%s host=%s db=%s user=%s error=%s',
            APP_ENV,
            DB_HOST,
            DB_NAME,
            DB_USER,
            $e->getMessage()
        ));

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']);
        exit;
    }
}
