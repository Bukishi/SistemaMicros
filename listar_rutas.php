<?php
// listar_rutas.php
include 'config.php';
session_start();

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM ruta ORDER BY id");
    $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rutas);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error al listar rutas: " . $e->getMessage()]);
}
?>

