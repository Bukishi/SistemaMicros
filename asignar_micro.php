<?php 
include 'config.php';
/*session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
*/
$error_message = '';
$hora_prohibida_inicio = strtotime('00:00');
$hora_prohibida_fin    = strtotime('05:30');

// Asignar micro a chofer
if (isset($_POST['assign_micro'])) {
    $chofer_id = $_POST['chofer_id'];
    $micro_id  = $_POST['micro_id'];
    $fecha     = $_POST['fecha'];
    $inicio    = $_POST['hora_inicio'];
    $fin       = $_POST['hora_fin'];

    $error_message = "";

    // Crear timestamps completos con fecha
    $inicio_ts = strtotime("$fecha $inicio");
    $fin_ts    = strtotime("$fecha $fin");

    // Si el horario termina antes del inicio, asumimos que pasa a día siguiente
    if ($fin_ts <= $inicio_ts) {
        $fin_ts = strtotime("+1 day", $fin_ts);
    }

    // Verificar duración máxima de jornada
    $duracionHoras = ($fin_ts - $inicio_ts) / 3600;
    if ($duracionHoras > 10) {
        $error_message = "❌ El horario excede las 10 horas máximas permitidas por día.";
    } else {
        // Definir las horas prohibidas (de 00:00 a 05:30)
        $prohibido_inicio = strtotime(date('Y-m-d', $inicio_ts) . " 00:00");
        $prohibido_fin    = strtotime(date('Y-m-d', $inicio_ts) . " 05:30");

        $cruzaMedianoche = date('Y-m-d', $inicio_ts) !== date('Y-m-d', $fin_ts);
        if ($cruzaMedianoche) {
            $prohibido_siguiente_inicio = strtotime(date('Y-m-d', $fin_ts) . " 00:00");
            $prohibido_siguiente_fin    = strtotime(date('Y-m-d', $fin_ts) . " 05:30");

            if (
                ($inicio_ts < $prohibido_siguiente_fin && $fin_ts > $prohibido_siguiente_inicio)
            ) {
                $error_message = "❌ El horario no puede incluir horas entre las 00:00 y 05:30.";
            }
        }

        // Validación del mismo día
        if (
            ($inicio_ts < $prohibido_fin && $fin_ts > $prohibido_inicio)
        ) {
            $error_message = "❌ El horario no puede incluir horas entre las 00:00 y 05:30.";
        }
    }
    $inicio_str = $inicio; // ej: '07:00:00'
$fin_str = $fin;       // ej: '15:00:00'

// Consulta para buscar si hay horarios que se solapen para el mismo chofer y fecha
$sql = "SELECT * FROM horario 
        WHERE chofer_id = :chofer_id 
          AND fecha = :fecha 
          AND id != :horario_id -- si es nuevo insert, pasar 0 o NULL para no excluir
          AND (
              (hora_inicio < :fin AND hora_fin > :inicio)
          )";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':chofer_id' => $chofer_id,
    ':fecha'     => $fecha,
    ':inicio'    => $inicio_str,
    ':fin'       => $fin_str,
    ':horario_id'=> $horario_id ?? 0,
]);

$conflicto = $stmt->fetch();

if ($conflicto) {
    // Hay conflicto de horario para el chofer
    $error_message ="<p style='color:red;'>❌ El chofer ya tiene un horario que se cruza con este.</p>";
    // NO insertar ni actualizar
} 

    if (!empty($error_message)) {
        echo "<p style='color:red;'>$error_message</p>";
    } else {
        // Obtener la ruta asociada a la micro
        $stmtRuta = $pdo->prepare("SELECT ruta_id FROM micro WHERE id = ?");
        $stmtRuta->execute([$micro_id]);
        $ruta = $stmtRuta->fetch(PDO::FETCH_ASSOC);

        if ($ruta) {
            $ruta_id = $ruta['ruta_id'];

            // Insertar asignación
            $stmtAsignacion = $pdo->prepare("INSERT INTO asignacion_micro 
                                             (chofer_id, micro_id, fecha, hora_inicio, hora_fin)
                                             VALUES (?, ?, ?, ?, ?)");
            $stmtAsignacion->execute([$chofer_id, $micro_id, $fecha, $inicio, $fin]);

            // Insertar horario
            $stmtHorario = $pdo->prepare("INSERT INTO horario 
                                          (chofer_id, ruta_id, fecha, hora_inicio, hora_fin)
                                          VALUES (?, ?, ?, ?, ?)");
            $stmtHorario->execute([$chofer_id, $ruta_id, $fecha, $inicio, $fin]);
        } else {
            echo "<p style='color:red;'>❌ No se encontró la ruta asociada a la micro seleccionada.</p>";
        }
    }
}



// Actualizar horario
$editData = null;
$editId = null;

if (isset($_POST['actualizar_horario'])) {
    $editId = $_POST['actualizar_horario'];
    $stmt = $pdo->prepare("SELECT * FROM horario WHERE id = ?");
    $stmt->execute([$editId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Procesar actualización
if (isset($_POST['confirmar_actualizacion'])) {
    $horario_id = $_POST['horario_id'];
    $micro_id   = $_POST['micro_id'];
    $fecha      = $_POST['fecha'];
    $inicio     = $_POST['inicio'];
    $fin        = $_POST['fin'];

    $error_message = "";

    // Convertir horas a timestamps
    $inicio_ts = strtotime("$fecha $inicio");
    $fin_ts    = strtotime("$fecha $fin");

    // Si el fin es menor o igual al inicio, asumimos que termina al día siguiente
    if ($fin_ts <= $inicio_ts) {
        $fin_ts = strtotime("+1 day", $fin_ts);
    }

    // Duración en horas
    $duracionHoras = ($fin_ts - $inicio_ts) / 3600;

    // Validación de duración
    if ($duracionHoras > 10) {
        $error_message = "❌ El horario excede las 10 horas máximas permitidas por día.";
    }

    // Validación de horario prohibido (00:00 - 05:30), considerar el mismo día y día siguiente
    $prohibido_inicio_dia1 = strtotime(date('Y-m-d', $inicio_ts) . " 00:00");
    $prohibido_fin_dia1    = strtotime(date('Y-m-d', $inicio_ts) . " 05:30");
    $prohibido_inicio_dia2 = strtotime(date('Y-m-d', $fin_ts) . " 00:00");
    $prohibido_fin_dia2    = strtotime(date('Y-m-d', $fin_ts) . " 05:30");

    if (
        ($inicio_ts < $prohibido_fin_dia1 && $fin_ts > $prohibido_inicio_dia1) || // Cruza la franja prohibida del mismo día
        ($inicio_ts < $prohibido_fin_dia2 && $fin_ts > $prohibido_inicio_dia2)    // Cruza la franja del día siguiente
    ) {
        $error_message = "❌ El horario no puede incluir horas entre las 00:00 y 05:30.";
    }
    $inicio_str = $inicio; // ej: '07:00:00'
    $fin_str = $fin;       // ej: '15:00:00'

    // Consulta para buscar si hay horarios que se solapen para el mismo chofer y fecha
    $sql = "SELECT * FROM horario 
            WHERE chofer_id = :chofer_id 
            AND fecha = :fecha 
            AND id != :horario_id -- si es nuevo insert, pasar 0 o NULL para no excluir
            AND (
                (hora_inicio < :fin AND hora_fin > :inicio)
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':chofer_id' => $chofer_id,
        ':fecha'     => $fecha,
        ':inicio'    => $inicio_str,
        ':fin'       => $fin_str,
        ':horario_id'=> $horario_id ?? 0,
    ]);

    $conflicto = $stmt->fetch();

    if ($conflicto) {
        // Hay conflicto de horario para el chofer
        $error_message ="<p style='color:red;'>❌ El chofer ya tiene un horario que se cruza con este.</p>";
        // NO insertar ni actualizar
    } 


    // Si hay error, mostrar y salir
    if (!empty($error_message)) {
        echo "<p style='color:red;'>$error_message</p>";
        return;
    }

    // Obtener la ruta asociada a la micro
    $stmtRuta = $pdo->prepare("SELECT ruta_id FROM micro WHERE id = ?");
    $stmtRuta->execute([$micro_id]);
    $ruta = $stmtRuta->fetch(PDO::FETCH_ASSOC);

    if ($ruta) {
        $ruta_id = $ruta['ruta_id'];

        // Obtener chofer_id desde horario
        $stmt = $pdo->prepare("SELECT chofer_id FROM horario WHERE id = ?");
        $stmt->execute([$horario_id]);
        $row = $stmt->fetch();

        if ($row) {
            $chofer_id = $row['chofer_id'];

            // Verificar si ya hay una asignación
            $stmtAsignacion = $pdo->prepare("SELECT id FROM asignacion_micro WHERE chofer_id = ? AND fecha = ?");
            $stmtAsignacion->execute([$chofer_id, $fecha]);
            $asignacion = $stmtAsignacion->fetch();

            if ($asignacion) {
                // Actualizar asignación
                $stmt = $pdo->prepare("UPDATE asignacion_micro 
                                       SET micro_id = ?, hora_inicio = ?, hora_fin = ? 
                                       WHERE id = ?");
                $stmt->execute([$micro_id, $inicio, $fin, $asignacion['id']]);
            } else {
                // Insertar asignación nueva
                $stmt = $pdo->prepare("INSERT INTO asignacion_micro 
                                       (chofer_id, micro_id, fecha, hora_inicio, hora_fin)
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$chofer_id, $micro_id, $fecha, $inicio, $fin]);
            }

            // Actualizar horario
            $stmt = $pdo->prepare("UPDATE horario 
                                   SET ruta_id = ?, fecha = ?, hora_inicio = ?, hora_fin = ? 
                                   WHERE id = ?");
            $stmt->execute([$ruta_id, $fecha, $inicio, $fin, $horario_id]);

            echo "<p style='color:green;'>✅ Horario actualizado correctamente.</p>";
        } else {
            echo "<p style='color:red;'>❌ No se encontró el horario a actualizar.</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ No se encontró la ruta asociada a la micro.</p>";
    }
}

// Eliminar horarios
if (isset($_POST['delete_horario_id'])) {
    $id = $_POST['delete_horario_id'];

    $stmt = $pdo->prepare("DELETE FROM asignacion_micro WHERE chofer_id = (SELECT chofer_id FROM horario WHERE id = ?) AND fecha = (SELECT fecha FROM horario WHERE id = ?)");
    $stmt->execute([$id, $id]);

    $stmt = $pdo->prepare("DELETE FROM horario WHERE id = ?");
    $stmt->execute([$id]);

    echo "<p style='color:green;'>✅ Horario eliminado correctamente.</p>";
}

// Obtener todos los horarios y la asignación de micro
$horarios = $pdo->query("SELECT h.id, c.nombre AS chofer_nombre, 
                                c.apellido AS chofer_apellido, 
                                h.hora_inicio, h.hora_fin, h.fecha, 
                                m.patente AS micro_patente, r.nombre AS ruta_nombre
                         FROM horario h
                         JOIN chofer c ON h.chofer_id = c.id
                         JOIN ruta r ON h.ruta_id = r.id
                         LEFT JOIN asignacion_micro am ON am.chofer_id = h.chofer_id AND DATE(am.fecha) = DATE(h.fecha)
                         LEFT JOIN micro m ON am.micro_id = m.id")->fetchAll();



// Obtener datos para los formularios
$micros   = $pdo->query("SELECT id, patente, ruta_id FROM micro")->fetchAll();
$rutas    = $pdo->query("SELECT id, nombre FROM ruta")->fetchAll();
$choferes = $pdo->query("SELECT c.id, c.nombre, c.apellido, c.licencia, c.examen_corma, u.correo, c.usuario_id 
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
    <h2 class="mb-4 text-center">Gestión de Horarios</h2>
    <h3 class="my-4 text-center">Conductores y sus Horarios</h3>
        <h3 class="my-4 text-center">Horarios</h3>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover shadow-sm">
            <thead class="table-success text-center">
                <tr>
                    <th>Chofer</th>
                    <th>Hora Inicio</th>
                    <th>Hora Fin</th>
                    <th>Fecha</th>
                    <th>Micro</th>
                    <th>Ruta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horarios as $horario): ?>
                    <tr>
                        <td><?= htmlspecialchars($horario['chofer_nombre'] . ' ' . $horario['chofer_apellido']) ?></td>
                        <td><?= htmlspecialchars($horario['hora_inicio']) ?></td>
                        <td><?= htmlspecialchars($horario['hora_fin']) ?></td>
                        <td><?= htmlspecialchars((new DateTime($horario['fecha']))->format('d/m/Y')) ?></td>
                        <td><?= htmlspecialchars($horario['micro_patente'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($horario['ruta_nombre'] ?? '-') ?></td>
                        <td class="text-center">
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="actualizar_horario" value="<?= $horario['id'] ?>">
                                <button type="submit" class="btn btn-warning btn-sm">Editar</button>
                            </form>
                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar este horario?');">
                                <input type="hidden" name="delete_horario_id" value="<?= $horario['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

    <!-- Asignar Micro -->
<div class="row">
    <div class="col-6">
    <h3 class="my-4 text-center">Asignar Micro a Chofer</h3>
    <form method="POST" class="mx-auto" style="max-width: 500px;">
        <select name="chofer_id" class="form-select mb-2" required>
            <option value="" disabled selected>Selecciona chofer</option>
            <?php foreach ($choferes as $chofer): ?>
                <option value="<?= $chofer['id'] ?>">
                    <?= htmlspecialchars($chofer['nombre'] . ' ' . $chofer['apellido']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="micro_id" class="form-select mb-2" required>
            <option value="" disabled selected>Selecciona micro</option>
            <?php foreach ($micros as $micro): ?>
                <option value="<?= $micro['id'] ?>">
                    <?= htmlspecialchars($micro['patente']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="date" name="fecha" class="form-control mb-2" required>
        <input type="time" name="hora_inicio" class="form-control mb-2" required>
        <input type="time" name="hora_fin" class="form-control mb-2" required>

        <button type="submit" name="assign_micro">Asignar Micro</button>
    </form>
    </div>

    <!-- Horarios -->
    

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php if ($editData): ?>
<div class="col-6">
<h3 class="my-4 text-center">Editar Horario</h3>
<form method="POST" class="mx-auto" style="max-width: 500px;">
    <input type="hidden" name="confirmar_actualizacion" value="1">
    <input type="hidden" name="horario_id" value="<?= $editId ?>">

    <select name="micro_id" class="form-select mb-2" required>
        <option value="" disabled>Selecciona micro</option>
        <?php foreach ($micros as $micro): ?>
            <option value="<?= $micro['id'] ?>" <?= ($micro['id'] == ($editData['micro_id'] ?? '')) ? 'selected' : '' ?>>
                <?= htmlspecialchars($micro['patente']) ?>
            </option>
        <?php endforeach; ?>
    </select>


    <input type="date" name="fecha" class="form-control mb-2" value="<?= $editData['fecha'] ?>" required>
    <input type="time" name="inicio" class="form-control mb-2" value="<?= $editData['hora_inicio'] ?>" required>
    <input type="time" name="fin" class="form-control mb-2" value="<?= $editData['hora_fin'] ?>" required>

    <button type="submit" class="btn btn-primary">Actualizar</button>
</form>
</div>
</div>
<?php endif; ?>
</html>

