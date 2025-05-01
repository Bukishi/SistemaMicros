<?php
// login.php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];

    try {
        // Ajustamos el nombre de la tabla y campos a tu estructura real
        $sql = "SELECT * FROM usuario WHERE correo = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($contrasena, $usuario['contraseña'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
            $_SESSION['usuario_rol'] = $usuario['rol'];
            header('Location: home.php'); // Redirigir a la página principal
            exit();
        } else {
            $error = "Credenciales incorrectas";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>

<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Log In</title>

    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link
      href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="assets/css/bootstrap.css" />

    <link rel="stylesheet" href="assets/vendors/iconly/bold.css" />

    <link
      rel="stylesheet"
      href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css"
    />
    <link
      rel="stylesheet"
      href="assets/vendors/bootstrap-icons/bootstrap-icons.css"
    />
    <link rel="stylesheet" href="assets/css/app.css" />
    <link
      rel="shortcut icon"
      href="assets/images/favicon.svg"
      type="image/x-icon"
    />
    <link rel="preconnect" href="https://fonts.gstatic.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/bootstrap.css" />
  <link rel="stylesheet" href="../assets/vendors/iconly/bold.css" />
  <link
    rel="stylesheet"
    href="../assets/vendors/perfect-scrollbar/perfect-scrollbar.css" />
  <link
    rel="stylesheet"
    href="../assets/vendors/bootstrap-icons/bootstrap-icons.css" />
  <link rel="stylesheet" href="../assets/css/app.css" />
  <link
    rel="shortcut icon"
    href="../assets/images/favicon.svg"
    type="image/x-icon" />
    <style>
      body {
        font-family: Arial, sans-serif;
        margin: 0;
        background-color: #f4f4f9;
        display: flex;
        flex-direction: column; /* Diseño vertical */
        align-items: center;
        gap: 20px; /* Espacio entre contenedores */
        padding: 20px;
            }

        .container {
            text-align: center;
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            max-width: 800px; /* Limita el ancho máximo */
            width: 100%;
            margin-bottom: 20px; /* Espacio entre tablas */
        }
      h1 {
          margin-bottom: 1.5rem;
          color: #333;
      }
      .button {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 100%;
          margin: 0.5rem 0;
          padding: 0.75rem;
          font-size: 1rem;
          color: #fff;
          background: #007bff;
          border: none;
          border-radius: 5px;
          text-decoration: none;
          cursor: pointer;
      }
      .button:hover {
          background: #0056b3;
      }
      .button span {
          margin-left: 10px;
      }
      .button-user {
          background: #28a745;
      }
      .button-user:hover {
          background: #218838;
      }
      .description {
          margin-top: 1rem;
          color: #666;
          font-size: 0.9rem;
      }
  </style>
</head>
<body>
    <div class="container">
    <h1>Iniciar Sesion</h1>
<!-- Formulario de inicio de sesión -->
<form method="POST">
    <label>Correo:</label>
    <input type="email" name="correo" required><br><br>
    <label>Contraseña:</label>
    <input type="password" name="contrasena" required><br><br>
    <button type="submit">Iniciar sesión</button>
    
    <?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
    <p><a href="register.php">¿No tienes cuenta? Regístrate aquí</a></p>
    <p><a href="change-pass.php">¿Olvidaste tu contraseña?</a></p>
</form>
</div>
</body>
</html>