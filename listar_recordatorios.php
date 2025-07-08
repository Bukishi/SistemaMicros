<?php
session_start();
include 'config.php';

$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT recordatorio.*, ruta.nombre AS ruta_nombre, paradero.nombre AS paradero_nombre FROM recordatorio
    LEFT JOIN ruta ON recordatorio.ruta_id = ruta.id
    LEFT JOIN paradero ON recordatorio.paradero_id = paradero.id
    WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$recordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($recordatorios);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener recordatorios: ' . $e->getMessage()]);
}

