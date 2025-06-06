<?php 
include 'config.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// EDITAR CHOFER - mostrar formulario con datos
if (isset($_GET['editar_id'])) {
    $editar_id = $_GET['editar_id'];
    $stmt = $pdo->prepare("SELECT c.id, c.nombre, c.apellido, c.licencia, c.examen_corma, u.correo, c.usuario_id 
                           FROM chofer c
                           JOIN usuario u ON c.usuario_id = u.id
                           WHERE c.id = ?");
    $stmt->execute([$editar_id]);
    $choferEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$choferEditar) {
        echo "<div class='alert alert-danger'>Chofer no encontrado.</div>";
        unset($choferEditar);
    }
}

// ACTUALIZAR CHOFER
if (isset($_POST['update'])) {
    $id         = $_POST['id'];
    $nombre     = $_POST['nombre'];
    $apellido   = $_POST['apellido'];
    $correo     = $_POST['correo'];
    $licencia   = $_POST['licencia'];
    $examen_corma = isset($_POST['examen_corma']) ? true : false;

    // Obtener usuario_id para actualizar usuario
    $stmt = $pdo->prepare("SELECT usuario_id FROM chofer WHERE id = ?");
    $stmt->execute([$id]);
    $usuarioIdRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuarioIdRow) {
        $usuario_id = $usuarioIdRow['usuario_id'];
        
        // Actualizar usuario
        $stmtUpdUser = $pdo->prepare("UPDATE usuario SET nombre = ?, apellido = ?, correo = ? WHERE id = ?");
        try {
            $stmtUpdUser->execute([$nombre, $apellido, $correo, $usuario_id]);
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                echo "<div class='alert alert-danger'>El correo '$correo' ya está registrado. Por favor usa otro.</div>";
            } else {
                echo "<div class='alert alert-danger'>Error actualizando usuario: " . $e->getMessage() . "</div>";
            }
            goto skip_update; // para no actualizar chofer si hubo error
        }
        
        // Actualizar chofer
        $stmtUpdChofer = $pdo->prepare("UPDATE chofer SET nombre = ?, apellido = ?, licencia = ?, examen_corma = ? WHERE id = ?");
        $stmtUpdChofer->execute([$nombre, $apellido, $licencia, $examen_corma, $id]);
        echo "<div class='alert alert-success'>Chofer actualizado correctamente.</div>";
    } else {
        echo "<div class='alert alert-danger'>Chofer no encontrado para actualizar.</div>";
    }
    skip_update:
}

// Añadir chofer
if (isset($_POST['add'])) {
    $nombre      = $_POST['nombre'];
    $apellido    = $_POST['apellido'];
    $correo      = $_POST['correo'];
    $contraseña  = password_hash($_POST['contraseña'], PASSWORD_DEFAULT);
    $licencia    = $_POST['licencia'];
    $examen_corma= isset($_POST['examen_corma']) ? true : false;
    $rol         = 'chofer';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO usuario (nombre, apellido, correo, contraseña, rol) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $correo, $contraseña, $rol]);
        $usuario_id = $pdo->lastInsertId();

        $stmt2 = $pdo->prepare("INSERT INTO chofer (nombre, apellido, usuario_id, licencia, examen_corma) VALUES (?, ?, ?, ?, ?)");
        $stmt2->execute([$nombre, $apellido, $usuario_id, $licencia, $examen_corma]);
    } catch (PDOException $e) {
        if ($e->getCode() == '23505') {
            echo "<div class='alert alert-danger'>El correo '$correo' ya está registrado. Por favor usa otro.</div>";
        } else {
            echo "<div class='alert alert-danger'>Ocurrió un error: " . $e->getMessage() . "</div>";
        }
    }
}

// Eliminar chofer
if (isset($_POST['delete'])) {
    $chofer_id = $_POST['chofer_id'];

    $stmt = $pdo->prepare("SELECT usuario_id FROM chofer WHERE id = ?");
    $stmt->execute([$chofer_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $usuario_id = $usuario['usuario_id'];

        $pdo->prepare("DELETE FROM asignacion_micro WHERE chofer_id = ?")->execute([$chofer_id]);
        $pdo->prepare("DELETE FROM horario WHERE chofer_id = ?")->execute([$chofer_id]);
        $pdo->prepare("DELETE FROM chofer WHERE id = ?")->execute([$chofer_id]);

        if ($usuario_id) {
            $pdo->prepare("DELETE FROM usuario WHERE id = ?")->execute([$usuario_id]);
        }
    }
}

// Asignar micro a chofer
if (isset($_POST['assign_micro'])) {
    $chofer_id = $_POST['chofer_id'];
    $micro_id  = $_POST['micro_id'];
    $fecha     = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin    = $_POST['hora_fin'];

    $stmtRuta = $pdo->prepare("SELECT ruta_id FROM micro WHERE id = ?");
    $stmtRuta->execute([$micro_id]);
    $ruta = $stmtRuta->fetch(PDO::FETCH_ASSOC);

    if ($ruta) {
        $ruta_id = $ruta['ruta_id'];

        $stmtAsignacion = $pdo->prepare("INSERT INTO asignacion_micro (chofer_id, micro_id, fecha, hora_inicio, hora_fin)
                                         VALUES (?, ?, ?, ?, ?)");
        $stmtAsignacion->execute([$chofer_id, $micro_id, $fecha, $hora_inicio, $hora_fin]);

        $stmtHorario = $pdo->prepare("INSERT INTO horario (chofer_id, ruta_id, fecha, hora_inicio, hora_fin)
                                      VALUES (?, ?, ?, ?, ?)");
        $stmtHorario->execute([$chofer_id, $ruta_id, $fecha, $hora_inicio, $hora_fin]);
    } else {
        echo "Error: No se encontró la ruta asociada a la micro seleccionada.";
    }
}

// Actualizar horario
if (isset($_POST['update_schedule'])) {
    $horario_id = $_POST['horario_id'];
    $ruta_id    = $_POST['ruta_id'];
    $micro_id   = $_POST['micro_id'];
    $fecha      = $_POST['fecha'];
    $inicio     = $_POST['inicio'];
    $fin        = $_POST['fin'];

    $stmt = $pdo->prepare("SELECT chofer_id FROM horario WHERE id = ?");
    $stmt->execute([$horario_id]);
    $row = $stmt->fetch();

    if ($row) {
        $chofer_id = $row['chofer_id'];

        $checkStmt = $pdo->prepare("SELECT id FROM asignacion_micro WHERE chofer_id = ? AND fecha = ?");
        $checkStmt->execute([$chofer_id, $fecha]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $updateStmt = $pdo->prepare("UPDATE asignacion_micro 
                                         SET micro_id = ?, hora_inicio = ?, hora_fin = ?
                                         WHERE id = ?");
            $updateStmt->execute([$micro_id, $inicio, $fin, $existing['id']]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO asignacion_micro 
                                         (chofer_id, micro_id, fecha, hora_inicio, hora_fin)
                                         VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute([$chofer_id, $micro_id, $fecha, $inicio, $fin]);
        }
        
        if ($horario_id) {
            $updateHorario = $pdo->prepare("UPDATE horario 
                                            SET chofer_id = ?, ruta_id = ?, hora_inicio = ?, hora_fin = ?
                                            WHERE id = ?");
            $updateHorario->execute([$chofer_id, $ruta_id, $inicio, $fin, $horario_id]);
        } else {
            $insertHorario = $pdo->prepare("INSERT INTO horario 
                                            (chofer_id, ruta_id, hora_inicio, hora_fin)
                                            VALUES (?, ?, ?, ?)");
            $insertHorario->execute([$chofer_id, $ruta_id, $inicio, $fin]);
        }
        echo "<p style='color:green;'>✅ Horario actualizado correctamente.</p>";
    } else {
        echo "<p style='color:red;'>❌ No se encontró un horario con ese ID.</p>";
    }
}

$micros   = $pdo->query("SELECT id, patente, ruta_id FROM micro")->fetchAll();
$rutas    = $pdo->query("SELECT id, nombre FROM ruta")->fetchAll();
$choferes = $pdo->query("SELECT c.id, c.nombre, c.apellido, c.licencia, c.examen_corma, u.correo, c.usuario_id 
                          FROM chofer c
                          JOIN usuario u ON c.usuario_id = u.id")->fetchAll();

$horarios = $pdo->query("SELECT h.id, c.nombre AS chofer_nombre, 
                                        c.apellido AS chofer_apellido, 
                                        h.hora_inicio, 
                                        h.hora_fin, 
                                        h.fecha, 
                                        m.patente AS micro_patente, 
                                        r.nombre AS ruta_nombre
                                        FROM horario h
                                        JOIN chofer c ON h.chofer_id = c.id
                                        JOIN ruta r ON h.ruta_id = r.id
                                        LEFT JOIN asignacion_micro am 
                                        ON am.chofer_id = h.chofer_id AND DATE(am.fecha) = DATE(h.fecha)
                                        LEFT JOIN micro m ON am.micro_id = m.id;
                                        ")->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Choferes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        button {
          background-color:rgb(37, 95, 241);
          color: #fff;
          padding: 10px 20px;
          border: none;
          border-radius: 5px;
          cursor: pointer;
          transition: background-color 0.3s ease;
        }

        button:hover {
          background-color:rgb(42, 158, 235);
        }
    </style>
</head>
<body class="bg-light">

<button type="button" onclick="location.href='home.php'">Volver</button>

<div class="container py-5">
    <h2 class="mb-4 text-center">Gestión de Choferes</h2>
    <h3 class="my-4 text-center">Conductores y sus Horarios</h3>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover shadow-sm">
            <thead class="table-primary text-center">
                <tr>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Licencia</th>
                    <th>Examen CORMA</th>
                    <th>Correo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($choferes as $chofer): ?>
                    <tr>
                        <td><?= htmlspecialchars($chofer['nombre']) ?></td>
                        <td><?= htmlspecialchars($chofer['apellido']) ?></td>
                        <td><?= htmlspecialchars($chofer['licencia']) ?></td>
                        <td><?= $chofer['examen_corma'] ? 'Sí' : 'No' ?></td>
                        <td><?= htmlspecialchars($chofer['correo']) ?></td>
                        <td class="text-center">
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="chofer_id" value="<?= $chofer['id'] ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar chofer?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <a href="?editar_id=<?= $chofer['id'] ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- FORMULARIO EDITAR -->
    <?php if (isset($choferEditar)): ?>
        <h3 class="my-4 text-center">Editar Chofer</h3>
        <form method="POST" class="mx-auto" style="max-width: 400px;">
            <input type="hidden" name="id" value="<?= $choferEditar['id'] ?>">
            <input type="text" name="nombre" placeholder="Nombre" class="form-control mb-2" value="<?= htmlspecialchars($choferEditar['nombre']) ?>" required>
            <input type="text" name="apellido" placeholder="Apellido" class="form-control mb-2" value="<?= htmlspecialchars($choferEditar['apellido']) ?>" required>
            <input type="email" name="correo" placeholder="Correo" class="form-control mb-2" value="<?= htmlspecialchars($choferEditar['correo']) ?>" required>
            <!-- Licencia A4 únicamente -->
            <select name="licencia" class="form-select mb-2" required>
                <option value="A4" <?= $choferEditar['licencia'] === 'A4' ? 'selected' : '' ?>>A4</option>
            </select>
            <div class="form-check mb-2">
                <input type="checkbox" name="examen_corma" class="form-check-input" id="cormaCheckEdit" <?= $choferEditar['examen_corma'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="cormaCheckEdit">Aprobó examen CORMA</label>
            </div>
            <button type="submit" name="update">Actualizar Chofer</button>
            <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    <?php else: ?>
    <!-- FORMULARIO AGREGAR -->
    <h3 class="my-4 text-center">Agregar Nuevo Chofer</h3>
    <form method="POST" class="mx-auto" style="max-width: 400px;">
        <input type="text" name="nombre" placeholder="Nombre" class="form-control mb-2" required>
        <input type="text" name="apellido" placeholder="Apellido" class="form-control mb-2" required>
        <input type="email" name="correo" placeholder="Correo" class="form-control mb-2" required>
        <input type="password" name="contraseña" placeholder="Contraseña" class="form-control mb-2" required>
        <!-- Licencia A4 únicamente -->
        <select name="licencia" class="form-select mb-2" required>
            <option value="A4" selected>A4</option>
        </select>
        <div class="form-check mb-2">
            <input type="checkbox" name="examen_corma" class="form-check-input" id="cormaCheckAdd">
            <label class="form-check-label" for="cormaCheckAdd">Aprobó examen CORMA</label>
        </div>
        <button type="submit" name="add">Agregar Chofer</button>
    </form>
    <?php endif; ?>

    <hr class="my-5">

    <h3 class="mb-4 text-center">Horarios de Choferes</h3>
    <div class="table-responsive">
        <table class="table table-bordered table-hover table-striped shadow-sm">
            <thead class="table-primary text-center">
                <tr>
                    <th>Chofer</th>
                    <th>Patente Micro</th>
                    <th>Ruta</th>
                    <th>Fecha</th>
                    <th>Hora Inicio</th>
                    <th>Hora Fin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horarios as $horario): ?>
                    <tr>
                        <td><?= htmlspecialchars($horario['chofer_nombre'] . ' ' . $horario['chofer_apellido']) ?></td>
                        <td><?= htmlspecialchars($horario['micro_patente'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($horario['ruta_nombre']) ?></td>
                        <td><?= htmlspecialchars($horario['fecha']) ?></td>
                        <td><?= htmlspecialchars($horario['hora_inicio']) ?></td>
                        <td><?= htmlspecialchars($horario['hora_fin']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
