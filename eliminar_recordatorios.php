<?php
include 'config.php';
session_start();
header('Content-Type: application/json');

$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    http_response_code(403);
    echo json_encode(["error" => "Usuario no autenticado."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "ID de recordatorio no proporcionado."]);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM recordatorio WHERE id = :id AND usuario_id = :usuario_id");
    $stmt->execute([
        ':id' => $id,
        ':usuario_id' => $usuario_id
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["message" => "Recordatorio eliminado correctamente."]);
    } else {
        echo json_encode(["error" => "No se encontró el recordatorio o no tienes permiso."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error al eliminar: " . $e->getMessage()]);
}
?>
