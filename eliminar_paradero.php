<?php
include 'config.php';
$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'];

$stmt = $pdo->prepare("DELETE FROM paradero WHERE id = :id");
$stmt->execute(['id' => $id]);

echo "Paradero eliminado.";
?>