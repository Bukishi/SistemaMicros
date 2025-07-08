<?php
include 'config.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

function convertirArrayPostgres($cadena) {
    if (is_array($cadena)) return $cadena;
    if (empty($cadena)) return [];
    $limpia = trim($cadena, '{}');
    return array_map('trim', explode(',', $limpia));
}

// PROCESAR FORMULARIO
if (isset($_POST['add'])) {
    $usuario_id     = $_SESSION['usuario_id'];
    $ruta_id        = !empty($_POST['ruta_id']) ? $_POST['ruta_id'] : null;
    $patente        = trim($_POST['patente'] ?? null);
    $fecha          = $_POST['fecha'] ?? date('Y-m-d');
    $hora           = $_POST['hora'] ?? date('H:i:s');
    $categorias     = $_POST['categorias'] ?? [];
    $tipo_problema  = $_POST['tipo_problema'] ?? [];
    $descripcion    = trim($_POST['descripcion'] ?? '');

    // Validaciones
    if (empty($descripcion)) {
        echo "<div class='alert alert-danger'>⚠️ La descripción no puede estar vacía.</div>";
    } elseif (empty($categorias)) {
        echo "<div class='alert alert-danger'>⚠️ Selecciona al menos una categoría.</div>";
    } elseif (empty($tipo_problema)) {
        echo "<div class='alert alert-danger'>⚠️ Selecciona al menos un tipo de problema.</div>";
    } elseif (!empty($patente) && !preg_match('/^[A-Za-z]{4}[0-9]{2}$/', $patente)) {
        echo "<div class='alert alert-danger'>⚠️ La patente debe tener exactamente 4 letras y 2 números (Ej: ABCD12).</div>";
    } else {
        // Formatear arrays
        $categorias_pg     = '{' . implode(',', array_map('pg_escape_string', $categorias)) . '}';
        $tipo_problema_pg  = '{' . implode(',', array_map('pg_escape_string', $tipo_problema)) . '}';

        try {
            $stmt = $pdo->prepare("INSERT INTO reclamo 
                (usuario_id, ruta_id, fecha, hora, categorias, tipo_problema, descripcion, estado, patente)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)");
            $stmt->execute([
                $usuario_id,
                $ruta_id,
                $fecha,
                $hora,
                $categorias_pg,
                $tipo_problema_pg,
                $descripcion,
                $patente
            ]);
            echo "<div class='alert alert-success'>✅ Reclamo registrado correctamente.</div>";
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}

$rutas = $pdo->query("SELECT id, nombre FROM ruta ORDER BY nombre")->fetchAll();

// CONSULTAR MIS RECLAMOS
$usuario_id = $_SESSION['usuario_id'];
$mis_reclamos = $pdo->prepare("
    SELECT r.*, ru.nombre AS ruta_nombre
    FROM reclamo r
    LEFT JOIN ruta ru ON r.ruta_id = ru.id
    WHERE r.usuario_id = ?
    ORDER BY r.fecha DESC, r.hora DESC
");
$mis_reclamos->execute([$usuario_id]);
$reclamos = $mis_reclamos->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reclamos - Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        input[type="checkbox"], input[type="radio"] {
            border: 1px solid black;
            width: 18px;
            height: 18px;
            margin-right: 5px;
            cursor: pointer;
        }
        label {
            user-select: none;
            margin-right: 10px;
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <h2 class="text-center mb-4">Formulario de Reclamos</h2>

    <form method="POST">
        <div class="mb-3">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="date" name="fecha" id="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="mb-3">
            <label for="hora" class="form-label">Hora</label>
            <input type="time" name="hora" id="hora" class="form-control" value="<?= date('H:i') ?>" required>
        </div>

        <div class="mb-3">
            <label for="ruta_id" class="form-label">Ruta</label>
            <select name="ruta_id" id="ruta_id" class="form-select">
                <option value="">-- Seleccionar ruta --</option>
                <?php foreach ($rutas as $ru): ?>
                    <option value="<?= $ru['id'] ?>"><?= htmlspecialchars($ru['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="patente" class="form-label">Patente</label>
            <input type="text" name="patente" id="patente" class="form-control" maxlength="6" placeholder="Ej: ABCD12">
        </div>

        <div class="mb-3">
            <label class="form-label">Categorías:</label><br>
            <label><input type="checkbox" name="categorias[]" value="maltrato"> Maltrato</label>
            <label><input type="checkbox" name="categorias[]" value="retraso"> Retraso</label>
            <label><input type="checkbox" name="categorias[]" value="mala_conduccion"> Mala conducción</label>
            <label><input type="checkbox" name="categorias[]" value="otros"> Otros</label>
        </div>

        <div class="mb-3">
            <label class="form-label">Tipo de problema:</label><br>
            <label><input type="checkbox" name="tipo_problema[]" value="micro"> Micro</label>
            <label><input type="checkbox" name="tipo_problema[]" value="plataforma"> Plataforma</label>
        </div>

        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="4" required></textarea>
        </div>

        <button type="submit" name="add" class="btn btn-success">Enviar Reclamo</button>
        <a href="home.php" class="btn btn-secondary">Volver</a>
    </form>

    <h3 class="mt-5 mb-3 text-center">Mis Reclamos</h3>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-primary text-center">
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Ruta</th>
                    <th>Patente</th>
                    <th>Categorías</th>
                    <th>Tipo Problema</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Respuesta Admin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reclamos as $rec): 
                    $categorias = convertirArrayPostgres($rec['categorias']);
                    $tipos = convertirArrayPostgres($rec['tipo_problema']);
                ?>
                <tr class="<?php
                    switch ($rec['estado']) {
                        case 'pendiente': echo 'table-secondary'; break;
                        case 'en_revision': echo 'table-warning'; break;
                        case 'completado': echo 'table-success'; break;
                    }
                ?>">
                    <td class="text-center"><?= htmlspecialchars($rec['id']) ?></td>
                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($rec['fecha']))) ?></td>
                    <td><?= htmlspecialchars($rec['hora']) ?></td>
                    <td><?= $rec['ruta_nombre'] ? htmlspecialchars($rec['ruta_nombre']) : '-' ?></td>
                    <td><?= htmlspecialchars($rec['patente'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(implode(', ', $categorias)) ?></td>
                    <td><?= htmlspecialchars(implode(', ', $tipos)) ?></td>
                    <td><?= nl2br(htmlspecialchars($rec['descripcion'])) ?></td>
                    <td class="text-center"><?= ucfirst(str_replace('_', ' ', $rec['estado'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($rec['respuesta_admin'] ?? '-')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
// Bloqueo automático de ruta y patente si seleccionan "plataforma"
document.addEventListener('DOMContentLoaded', () => {
    const tipoProblemaChecks = document.querySelectorAll('input[name="tipo_problema[]"]');
    const rutaSelect = document.getElementById('ruta_id');
    const patenteInput = document.getElementById('patente');

    function actualizarCampos() {
        let plataformaSeleccionada = false;
        tipoProblemaChecks.forEach(cb => {
            if (cb.checked && cb.value === 'plataforma') {
                plataformaSeleccionada = true;
            }
        });

        rutaSelect.disabled = plataformaSeleccionada;
        patenteInput.disabled = plataformaSeleccionada;

        if (plataformaSeleccionada) {
            rutaSelect.value = "";
            patenteInput.value = "";
        }
    }

    tipoProblemaChecks.forEach(cb => {
        cb.addEventListener('change', actualizarCampos);
    });

    actualizarCampos();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
