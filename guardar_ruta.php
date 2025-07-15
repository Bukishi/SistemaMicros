<?php
include 'config.php';
session_start();

$data = json_decode(file_get_contents("php://input"), true);

$nombre = $data['nombre'];
$color = $data['color'];
$coordenadas = $data['coordenadas'];
if ($nombre === '' || !preg_match('/^[a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ]+$/', $nombre)) {
    die("Nombre de ruta inválido. Debe contener texto legible.");
}
if (!isset($data['coordenadas'])) {
    http_response_code(400);
    exit("No se recibieron coordenadas.");
}

if (!is_array($data['coordenadas'])) {
    http_response_code(400);
    exit("Las coordenadas no son un arreglo válido.");
}

if (!$nombre || !$color || !is_array($coordenadas)) {
    http_response_code(400);
    exit("Datos inválidos.");
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO ruta (nombre, color) VALUES (:nombre, :color) RETURNING id");
    $stmt->execute(['nombre' => $nombre, 'color' => $color]);
    $ruta_id = $stmt->fetchColumn();

    $stmtCoord = $pdo->prepare("INSERT INTO coordenada (lat, lng, orden, ruta_id) VALUES (:lat, :lng, :orden, :ruta_id)");

    foreach ($coordenadas as $i => $coord) {
        $stmtCoord->execute([
            'lat' => $coord['lat'],
            'lng' => $coord['lng'],
            'orden' => $i,
            'ruta_id' => $ruta_id
        ]);
    }

    $pdo->commit();
    echo "Ruta guardada correctamente.";
file_put_contents('debug_log.txt', print_r($data, true));

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Error al guardar la ruta: " . $e->getMessage();
}
