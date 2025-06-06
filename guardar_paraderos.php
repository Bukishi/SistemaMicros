<?php
include 'config.php';
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo "Datos inválidos.";
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO paradero (nombre, lat, lng) VALUES (:nombre, :lat, :lng)");

    foreach ($data as $paradero) {
        $stmt->execute([
            'nombre' => $paradero['nombre'],
            'lat' => $paradero['lat'],
            'lng' => $paradero['lng']
        ]);
    }

    echo "Paraderos guardados correctamente.";
} catch (Exception $e) {
    http_response_code(500);
    echo "Error al guardar paraderos: " . $e->getMessage();
}
?>
