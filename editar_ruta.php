<?php
// editar_ruta.php
include 'config.php';
session_start();

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$nombre = $data['nombre'] ?? null;
$color = $data['color'] ?? null;
$coordenadas = $data['coordenadas'] ?? [];

if (!$id || !$nombre || !$color || !is_array($coordenadas)) {
    http_response_code(400);
    exit("Datos inválidos.");
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE ruta SET nombre = :nombre, color = :color WHERE id = :id");
    $stmt->execute(['nombre' => $nombre, 'color' => $color, 'id' => $id]);

    $pdo->prepare("DELETE FROM coordenada WHERE ruta_id = :id")->execute(['id' => $id]);

    $stmtCoord = $pdo->prepare("INSERT INTO coordenada (lat, lng, orden, ruta_id) VALUES (:lat, :lng, :orden, :ruta_id)");
    foreach ($coordenadas as $i => $coord) {
        $stmtCoord->execute([
            'lat' => $coord['lat'],
            'lng' => $coord['lng'],
            'orden' => $i,
            'ruta_id' => $id
        ]);
    }

    $pdo->commit();
    echo "Ruta actualizada correctamente.";
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Error al actualizar la ruta: " . $e->getMessage();
}
?>