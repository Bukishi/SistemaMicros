<?php
include 'config.php';
$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'];
$lat = $data['lat'];
$lng = $data['lng'];

$stmt = $pdo->prepare("UPDATE paradero SET lat = :lat, lng = :lng WHERE id = :id");
$stmt->execute(['lat' => $lat, 'lng' => $lng, 'id' => $id]);

echo "Posición actualizada.";
?>