<?php 
include 'config.php';
session_start();
/*if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}*/
$isAdmin = ($_SESSION['usuario_rol'] === 'admin'); // Verificación del rol de administrador
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Mapa Transporte Público - Osorno</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <style>
    body, html {
      margin: 0;
      padding: 0;
      height: 100%;
    }

    .navbar {
      background-color: #343a40;
      color: white;
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: sans-serif;
    }

    .navbar .buttons a {
      color: white;
      text-decoration: none;
      margin-left: 15px;
      padding: 6px 12px;
      border: 1px solid white;
      border-radius: 4px;
      transition: background 0.3s;
    }

    .navbar .buttons a:hover {
      background-color: white;
      color: #343a40;
    }

    #map {
      height: calc(100% - 50px); /* Resta la altura del navbar */
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="title">🚍 Mapa Transporte Público - Osorno</div>
    <div class="buttons">
      <a href="">Inicio</a>
      <?php if ($isAdmin): ?>
        <a href="conductores.php">Conductores</a>
       <?php endif ?>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>
  <div id="map"></div>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    const map = L.map('map').setView([-40.573, -73.134], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    </script>
  </body>
</html>
