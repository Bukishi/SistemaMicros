<?php
include 'config.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(["error" => "ID no proporcionado"]);
    exit;
}

try {
    // Ruta
    $stmt = $pdo->prepare("SELECT id, nombre, color FROM ruta WHERE id = ?");
    $stmt->execute([$id]);
    $ruta = $stmt->fetch(PDO::FETCH_ASSOC);

    // Coordenadas
    $stmt2 = $pdo->prepare("SELECT latitud, longitud FROM coordenadas WHERE ruta_id = ? ORDER BY orden ASC");
    $stmt2->execute([$id]);
    $coordenadas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $ruta['coordenadas'] = array_map(function ($c) {
        return [(float)$c['latitud'], (float)$c['longitud']];
    }, $coordenadas);

    echo json_encode($ruta);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>
