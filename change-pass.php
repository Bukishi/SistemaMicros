<?php
session_start();
include 'config.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = $_POST['correo'];
    $nueva_contrasena = $_POST['nueva_contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];

    if ($nueva_contrasena !== $confirmar_contrasena) {
        $mensaje = "<p style='color:red;'>❌ Las contraseñas no coinciden.</p>";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuario WHERE correo = ?");
            $stmt->execute([$correo]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $hash = password_hash($nueva_contrasena, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE usuario SET contraseña = ? WHERE correo = ?");
                $update->execute([$hash, $correo]);
                $mensaje = "<p style='color:green;'>✅ Contraseña actualizada exitosamente. <a href='login.php'>Iniciar sesión</a></p>";
            } else {
                $mensaje = "<p style='color:red;'>❌ El correo no está registrado.</p>";
            }
        } catch (PDOException $e) {
            $mensaje = "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
}
?>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cambio de Contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/bootstrap.css" />
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css" />
    <link rel="stylesheet" href="assets/css/app.css" />
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <style>
      body {
        font-family: Arial, sans-serif;
        margin: 0;
        background-color: #f4f4f9;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        padding: 20px;
      }

      .container {
        text-align: center;
        background: #fff;
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        max-width: 800px;
        width: 100%;
        margin-bottom: 20px;
      }

      h1 {
        margin-bottom: 1.5rem;
        color: #333;
      }

      .button {
        width: 100%;
        padding: 0.75rem;
        font-size: 1rem;
        color: #fff;
        background: #007bff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
      }

      .button:hover {
        background: #0056b3;
      }

      .message {
        margin-top: 15px;
        font-size: 1rem;
      }
    </style>
</head>
<body>
    <form method="POST">
        <div class="container">
            <h1>Cambio de Contraseña</h1>

            <label>Correo:</label><br>
            <input type="email" name="correo" required><br><br>

            <label>Contraseña Nueva:</label><br>
            <input type="password" name="nueva_contrasena" required><br><br>

            <label>Confirmar Contraseña:</label><br>
            <input type="password" name="confirmar_contrasena" required><br><br>

            <button type="submit" class="button">Cambiar Contraseña</button>

            <div class="message">
                <?= $mensaje ?>
            </div>
        </div>
    </form>
</body>
</html>
