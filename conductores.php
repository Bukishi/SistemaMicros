<?php 
include 'config.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Añadir chofer
if (isset($_POST['add'])) {
    $nombre   = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $correo   = $_POST['correo'];
    $contraseña = password_hash($_POST['contraseña'], PASSWORD_DEFAULT);
    $rol      = 'chofer';  // Asignamos el rol como chofer
    
    // Insertamos el nuevo usuario
    try {
        $stmt = $pdo->prepare("INSERT INTO usuario (nombre, apellido, correo, contraseña, rol) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $correo, $contraseña, $rol]);
        // Insertamos el chofer
        $stmt2 = $pdo->prepare("INSERT INTO chofer (nombre, apellido, usuario_id) 
            VALUES (?, ?, ?)");
        $stmt2->execute([$nombre, $apellido, $usuario_id]);
    } catch (PDOException $e) {
        if ($e->getCode() == '23505') {
            // Error de clave duplicada (por ejemplo, correo ya registrado)
            echo "<div class='alert alert-danger'>El correo '$correo' ya está registrado. Por favor usa otro.</div>";
        } else {
            // Otro error
            echo "<div class='alert alert-danger'>Ocurrió un error: " . $e->getMessage() . "</div>";
        }
    }
    

    
}

// Eliminar chofer
if (isset($_POST['delete'])) {
    $chofer_id = $_POST['chofer_id'];

    // Obtener usuario_id antes de borrar el chofer
    $stmt = $pdo->prepare("SELECT usuario_id FROM chofer WHERE id = ?");
    $stmt->execute([$chofer_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $usuario_id = $usuario['usuario_id'];

        // Eliminar asignación del chofer al micro
        $pdo->prepare("DELETE FROM asignacion_micro WHERE chofer_id = ?")->execute([$chofer_id]);

        // Eliminar horarios
        $pdo->prepare("DELETE FROM horario WHERE chofer_id = ?")->execute([$chofer_id]);

        // Eliminar chofer
        $pdo->prepare("DELETE FROM chofer WHERE id = ?")->execute([$chofer_id]);

        // Eliminar usuario asociado si existe
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

    // Obtener la ruta_id de la micro
    $stmtRuta = $pdo->prepare("SELECT ruta_id FROM micro WHERE id = ?");
    $stmtRuta->execute([$micro_id]);
    $ruta = $stmtRuta->fetch(PDO::FETCH_ASSOC);

    if ($ruta) {
        $ruta_id = $ruta['ruta_id'];

        // Insertar en asignacion_micro
        $stmtAsignacion = $pdo->prepare("INSERT INTO asignacion_micro (chofer_id, micro_id, fecha, hora_inicio, hora_fin)
                                         VALUES (?, ?, ?, ?, ?)");
        $stmtAsignacion->execute([$chofer_id, $micro_id, $fecha, $hora_inicio, $hora_fin]);

        // Insertar en horario
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

    // Obtener chofer_id a partir del horario_id
    $stmt = $pdo->prepare("SELECT chofer_id FROM horario WHERE id = ?");
    $stmt->execute([$horario_id]);
    $row = $stmt->fetch();

    if ($row) {
        $chofer_id = $row['chofer_id'];

        // Verificar si ya existe una asignación para ese chofer en esa fecha
        $checkStmt = $pdo->prepare("SELECT id FROM asignacion_micro WHERE chofer_id = ? AND fecha = ?");
        $checkStmt->execute([$chofer_id, $fecha]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Si existe, actualizamos
            $updateStmt = $pdo->prepare("UPDATE asignacion_micro 
                                         SET micro_id = ?, hora_inicio = ?, hora_fin = ?
                                         WHERE id = ?");
            $updateStmt->execute([$micro_id, $inicio, $fin, $existing['id']]);
        } else {
            // Si no existe, insertamos
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

// Obtener datos para los formularios
$micros   = $pdo->query("SELECT id, patente, ruta_id FROM micro")->fetchAll();
$rutas    = $pdo->query("SELECT id, nombre FROM ruta")->fetchAll();
$choferes = $pdo->query("SELECT c.id, c.nombre, c.apellido, u.correo, c.usuario_id 
                          FROM chofer c
                          JOIN usuario u ON c.usuario_id = u.id")->fetchAll();

// Obtener todos los horarios y la asignación de micro
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        button {
  background-color:rgb(37, 95, 241); /* Color de fondo */
  color: #fff; /* Color del texto */
  padding: 10px 20px; /* Espaciado interno */
  border: none; /* Elimina el borde por defecto */
  border-radius: 5px; /* Relleno de esquinas */
  cursor: pointer; /* Cambia el cursor en puntero */
  transition: background-color 0.3s ease; /* Efecto de transición */
}

button:hover {
  background-color:rgb(42, 158, 235); /* Cambia el color de fondo al pasar el mouse */
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
                    <th>Inicio Horario</th>
                    <th>Fin Horario</th>
                    <th>Fecha</th>
                    <th>Patente Micro</th>
                    <th>Ruta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horarios as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['chofer_nombre']) ?></td>
                        <td><?= htmlspecialchars($h['chofer_apellido']) ?></td>
                        <td><?= $h['hora_inicio'] ?></td>
                        <td><?= $h['hora_fin'] ?></td>
                        <td><?= $h['fecha'] ?></td>
                        <td><?= htmlspecialchars($h['micro_patente']) ?></td>
                        <td><?= htmlspecialchars($h['ruta_nombre']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="accordion" id="choferAccordion">

        <!-- Añadir Chofer -->
        <div class="accordion-item border border-success rounded mb-3 shadow">
            <h2 class="accordion-header" id="headingAdd">
                <button class="accordion-button bg-success text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdd">
                    <i class="bi bi-person-plus-fill me-2"></i> Añadir Chofer
                </button>
            </h2>
            <div id="collapseAdd" class="accordion-collapse collapse show" data-bs-parent="#choferAccordion">
                <div class="accordion-body">
                    <form method="POST">
                        <input type="text" name="nombre" class="form-control mb-2" placeholder="Nombre" required>
                        <input type="text" name="apellido" class="form-control mb-2" placeholder="Apellido" required>
                        <input type="email" name="correo" class="form-control mb-2" placeholder="Correo" required>
                        <input type="password" name="contraseña" class="form-control mb-2" placeholder="Contraseña" required>
                        <button type="submit" name="add" class="btn btn-outline-success w-100">Añadir Chofer</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Eliminar Chofer -->
        <div class="accordion-item border border-danger rounded mb-3 shadow">
            <h2 class="accordion-header" id="headingDelete">
                <button class="accordion-button bg-danger text-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDelete">
                    <i class="bi bi-person-dash-fill me-2"></i> Eliminar Chofer
                </button>
            </h2>
            <div id="collapseDelete" class="accordion-collapse collapse" data-bs-parent="#choferAccordion">
                <div class="accordion-body">
                    <form method="POST">
                        <select name="chofer_id" class="form-select mb-2" required>
                            <!-- Opciones generadas por PHP -->
                            <?php foreach ($choferes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= "{$c['nombre']} {$c['apellido']}" ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="delete" class="btn btn-outline-danger w-100">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Asignar Micro -->
        <div class="accordion-item border border-primary rounded mb-3 shadow">
            <h2 class="accordion-header" id="headingAssign">
                <button class="accordion-button bg-primary text-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAssign">
                    <i class="bi bi-truck-front-fill me-2"></i> Asignar Micro a Chofer
                </button>
            </h2>
            <div id="collapseAssign" class="accordion-collapse collapse" data-bs-parent="#choferAccordion">
                <div class="accordion-body">
                    <form method="POST">
                        <select name="chofer_id" class="form-select mb-2" required>
                            <?php foreach ($choferes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= "{$c['nombre']} {$c['apellido']}" ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="micro_id" class="form-select mb-2" required>
                            <?php foreach ($micros as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= $m['patente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="fecha" class="form-control mb-2" required>
                        <input type="time" name="hora_inicio" class="form-control mb-2" required>
                        <input type="time" name="hora_fin" class="form-control mb-2" required>
                        <button type="submit" name="assign_micro" class="btn btn-outline-primary w-100">Asignar Micro</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Actualizar Horario -->
        <div class="accordion-item border border-warning rounded mb-3 shadow">
            <h2 class="accordion-header" id="headingUpdate">
                <button class="accordion-button bg-warning text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUpdate">
                    <i class="bi bi-clock-history me-2"></i> Actualizar Horario
                </button>
            </h2>
            <div id="collapseUpdate" class="accordion-collapse collapse" data-bs-parent="#choferAccordion">
                <div class="accordion-body">
                    <form method="POST">
                        <select name="horario_id" class="form-select mb-2" required>
                            <?php foreach ($horarios as $h): ?>
                                <option value="<?= $h['id'] ?>">
                                    <?= "{$h['chofer_nombre']} {$h['chofer_apellido']} - {$h['ruta_nombre']} - {$h['fecha']} {$h['hora_inicio']} - {$h['hora_fin']}" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="fecha" class="form-control mb-2" required>
                        <input type="time" name="inicio" class="form-control mb-2" required>
                        <input type="time" name="fin" class="form-control mb-2" required>
                        <!-- Selector de ruta -->
                        <select id="rutaSelect" name="ruta_id" class="form-select mb-2" required>
                            <?php foreach ($rutas as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= $r['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Selector de micro (patente) -->
                        <select id="microSelect" name="micro_id" class="form-select mb-2" required>
                            <?php foreach ($micros as $m): ?>
                                <option value="<?= $m['id'] ?>" data-ruta="<?= $m['ruta_id'] ?>"><?= $m['patente'] ?></option>
                            <?php endforeach; ?>
                        </select>


                        <input type="hidden" name="chofer_id" value="">
                        <button type="submit" name="update_schedule" class="btn btn-outline-warning w-100">Actualizar Horario</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const rutaSelect = document.querySelector('select[name="ruta_id"]');
        const microSelect = document.getElementById('microSelect');

        rutaSelect.addEventListener('change', function () {
            const selectedRuta = this.value;

            // Limpiar todas las opciones
            microSelect.innerHTML = '';

            // Agregar solo las micros que coinciden con la ruta
            <?php foreach ($micros as $m): ?>
                if ('<?= $m['ruta_id'] ?>' === selectedRuta) {
                    const option = document.createElement('option');
                    option.value = '<?= $m['id'] ?>';
                    option.textContent = '<?= $m['patente'] ?>';
                    microSelect.appendChild(option);
                }
            <?php endforeach; ?>

            // Si no hay micros, agregar una opción vacía o de aviso
            if (microSelect.options.length === 0) {
                const emptyOption = document.createElement('option');
                emptyOption.textContent = 'No hay micros para esta ruta';
                emptyOption.disabled = true;
                emptyOption.selected = true;
                microSelect.appendChild(emptyOption);
            }
        });
    });
</script>

</body>
</html>

