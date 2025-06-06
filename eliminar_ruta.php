<?php
// eliminar_ruta.php
include 'config.php';
session_start();

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;

if (!$id) {
    http_response_code(400);
    exit("ID de ruta requerido.");
}

try {
    $stmt = $pdo->prepare("DELETE FROM ruta WHERE id = :id");
    $stmt->execute(['id' => $id]);
    echo "Ruta eliminada correctamente.";
} catch (Exception $e) {
    http_response_code(500);
    echo "Error al eliminar la ruta: " . $e->getMessage();
}
?>
