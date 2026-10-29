<?php
require_once __DIR__ . '/app.php';

$privateConfigPath = 'C:\\xampp\\healthsphere-private\\healthsphere-db.php';
$privateConfig = is_file($privateConfigPath) ? require $privateConfigPath : [];
if (!is_array($privateConfig)) {
    error_log('HealthSphere private database configuration must return an array.');
    http_response_code(500);
    exit('The private database configuration is invalid.');
}

$dbHost = getenv('HEALTHSPHERE_DB_HOST') ?: ($privateConfig['host'] ?? '127.0.0.1');
$dbPort = getenv('HEALTHSPHERE_DB_PORT') ?: ($privateConfig['port'] ?? '3306');
$dbName = getenv('HEALTHSPHERE_DB_NAME') ?: ($privateConfig['name'] ?? 'healthsphere_db');
$dbUser = getenv('HEALTHSPHERE_DB_USER') ?: ($privateConfig['user'] ?? 'root');
$dbPassword = getenv('HEALTHSPHERE_DB_PASSWORD') ?: ($privateConfig['password'] ?? '');

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $exception) {
    error_log('HealthSphere database connection failed: ' . $exception->getMessage());

    http_response_code(500);
    exit('Unable to connect to the HealthSphere database. Please check the local database configuration.');
}
