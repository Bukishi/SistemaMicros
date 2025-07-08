<?php
include 'config.php';
session_start();
$usuario_id = $_SESSION['usuario_id'] ?? null;

if (!$usuario_id) {
    http_response_code(403);
    echo "Usuario no autenticado.";
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo "Falta ID.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM recordatorio WHERE id = ? AND usuario_id = ?");
$stmt->execute([$id, $usuario_id]);
$recordatorio = $stmt->fetch(PDO::FETCH_ASSOC);

if ($recordatorio) {
    $recordatorio['dias'] = json_decode($recordatorio['dias']);
    echo json_encode($recordatorio);
} else {
    http_response_code(404);
    echo "No encontrado.";
}
?>
