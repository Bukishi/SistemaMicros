<?php
$config = json_decode(file_get_contents(__DIR__ . '/db_config.json'), true);

$host = $config['host'];
$port = $config['port'];
$db   = $config['dbname'];
$user = $config['user'];
$pass = $config['pass'];

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
