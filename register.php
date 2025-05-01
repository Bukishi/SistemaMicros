<?php
// register.php
include 'config.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombres = $_POST['nombres'];
    $apellidos = $_POST['apellidos'];
    $correo = $_POST['correo'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_BCRYPT);

    try {
        $sql = "INSERT INTO usuario (nombre, apellido, correo, contraseña) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombres, $apellidos, $correo, $contrasena]);
        $mensaje = "✅ Usuario registrado exitosamente. <a href='login.php'>Iniciar sesión</a>";
    } catch (PDOException $e) {
        if ($e->getCode() == '23505') {
            $mensaje = "⚠️ El correo ya está registrado.";
        } else {
            $mensaje = "❌ Error al registrar: " . $e->getMessage();
        }
    }
}
?>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registrarse</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/bootstrap.css" />
    <link rel="stylesheet" href="assets/vendors/iconly/bold.css" />
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
        color: #333;
      }
    </style>
</head>
<body>
    <div class="container">
    <h1>Registro de Usuario</h1>

    <?php if ($mensaje): ?>
        <div class="message"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Nombres:</label>
        <input type="text" name="nombres" required><br><br>
        <label>Apellidos:</label>
        <input type="text" name="apellidos" required><br><br>
        <label>Correo:</label>
        <input type="email" name="correo" required><br><br>
        <label>Contraseña:</label>
        <input type="password" name="contrasena" required><br><br>
        <button type="submit" class="button">Registrarse</button>
    </form>
    <p><a href="login.php">¿Ya tienes cuenta? Inicia sesión aquí</a></p>
    </div>
</body>
</html>
