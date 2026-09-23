<?php

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/segredos.php';

// Nenhuma credencial de produção mora aqui: este arquivo é servido pela web e vive num
// repositório público. Os valores reais ficam em mpg_secrets.php, FORA do /www — ver
// config/segredos.php. Os padrões abaixo são só o XAMPP da máquina de desenvolvimento.
$dbConfig = APP_IS_LOCAL
    ? [
        'host' => segredo('DB_HOST', 'localhost'),
        'name' => segredo('DB_NAME', 'mpgacademy_mpg_db'),
        'user' => segredo('DB_USER', 'root'),
        'pass' => segredo('DB_PASS', ''),
    ]
    : [
        // KingHost nao aceita 'localhost': o MySQL roda em outro servidor.
        // Host alternativo, caso o principal falhe: mysql65-farm2.uni5.net
        'host' => segredo('DB_HOST', 'mysql.mpgacademy.com.br'),
        'name' => segredo('DB_NAME', 'mpgacademy'),
        'user' => segredo('DB_USER', 'mpgacademy'),
        'pass' => mpgSegredoObrigatorio('DB_PASS'),
    ];

define('DB_HOST', $dbConfig['host']);
define('DB_NAME', $dbConfig['name']);
define('DB_USER', $dbConfig['user']);
define('DB_PASS', $dbConfig['pass']);

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
