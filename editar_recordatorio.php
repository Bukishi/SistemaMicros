<?php
include 'config.php';
session_start();

header('Content-Type: application/json');

$usuario_id = $_SESSION['usuario_id'] ?? null;


if (!$usuario_id) {
  http_response_code(403);
  exit("Usuario no autenticado.");
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo "No se recibieron datos.";
    exit;
}

$paradero_id = $data['paradero_id'] ?? null;
$ruta_id = $data['ruta_id'] ?? null;
$hora = $data['hora'] ?? null;
$tipo = $data['tipo'] ?? null;
$dias = $data['dias'] ?? [];
$fecha = $data['fecha'] ?? null;



if (!$paradero_id || !$ruta_id || !$hora || !$tipo) {
    http_response_code(400);
    echo "Faltan campos obligatorios.";
    exit;
}

if ($tipo === 'unico' && !$fecha) {
    http_response_code(400);
    echo "Debe ingresar una fecha para un recordatorio único.";
    exit;
}

if ($tipo === 'semanal' && empty($dias)) {
    http_response_code(400);
    echo "Debe seleccionar al menos un día para recordatorios semanales.";
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE recordatorio 
  SET paradero_id = :paradero,
      ruta_id = :ruta,
      hora = :hora,
      tipo = :tipo,
      fecha = :fecha,
      dias = :dias
  WHERE id = :id AND usuario_id = :usuario_id");

    $stmt->execute([
  'paradero' => $paradero_id,
  'ruta' => $ruta_id,
  'hora' => $hora,
  'tipo' => $tipo,
  'fecha' => $fecha,
  'dias' => json_encode($dias),
  'usuario_id' => $usuario_id,
  'id' => $data['id'] // Este debe ser pasado desde JS
]);


    echo "Recordatorio guardado correctamente.";
} catch (Exception $e) {
    http_response_code(500);
    echo "Error al guardar: " . $e->getMessage();
}
