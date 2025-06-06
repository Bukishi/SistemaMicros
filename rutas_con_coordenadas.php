<?php
include 'config.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT r.id as ruta_id, r.nombre, r.color, c.lat, c.lng, c.orden
            FROM ruta r
            JOIN coordenada c ON r.id = c.ruta_id
            ORDER BY r.id, c.orden";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rutas = [];

    foreach ($rows as $row) {
        $id = $row['ruta_id'];
        if (!isset($rutas[$id])) {
            $rutas[$id] = [
                    'id' => $id,
                    'nombre' => $row['nombre'],
                    'color' => $row['color'],
                    'coordenadas' => []
                ];
        }
        $rutas[$id]['coordenadas'][] = [$row['lat'], $row['lng']];
    }

    echo json_encode(array_values($rutas));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
