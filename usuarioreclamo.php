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

$rutas = $pdo->query("SELECT id, nombre FROM ruta ORDER BY nombre")->fetchAll();

// Variables para mantener valores si hay error
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$hora = $_POST['hora'] ?? date('H:i');
$ruta_id = $_POST['ruta_id'] ?? '';
$patente = $_POST['patente'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';
$categorias = $_POST['categorias'] ?? [];
$tipo_problema = $_POST['tipo_problema'] ?? [];

$mensaje = '';
$mensaje_tipo = '';

if (isset($_POST['add'])) {
    $usuario_id = $_SESSION['usuario_id'];

    // Validaciones
    if (empty($tipo_problema)) {
        $mensaje = "⚠️ Selecciona al menos un tipo de problema.";
        $mensaje_tipo = "danger";
    } elseif (in_array('micro', $tipo_problema) && empty($ruta_id)) {
        $mensaje = "⚠️ Debes seleccionar una ruta si el problema es 'micro'.";
        $mensaje_tipo = "danger";
    } elseif (empty($descripcion)) {
        $mensaje = "⚠️ La descripción no puede estar vacía.";
        $mensaje_tipo = "danger";
    } elseif (empty($categorias)) {
        $mensaje = "⚠️ Selecciona al menos una categoría.";
        $mensaje_tipo = "danger";
    } elseif (!empty($patente) && !preg_match('/^[A-Za-z]{4}[0-9]{2}$/', $patente)) {
        $mensaje = "⚠️ La patente debe tener 4 letras seguidas de 2 números (Ej: ABCD12).";
        $mensaje_tipo = "danger";
    } elseif ($fecha !== date('Y-m-d')) {
        $mensaje = "⚠️ La fecha debe ser exactamente la de hoy.";
        $mensaje_tipo = "danger";
    } else {
        // Formatear arrays
        $categorias_pg = '{' . implode(',', array_map('pg_escape_string', $categorias)) . '}';
        $tipo_problema_pg = '{' . implode(',', array_map('pg_escape_string', $tipo_problema)) . '}';

        try {
            $stmt = $pdo->prepare("INSERT INTO reclamo 
                (usuario_id, ruta_id, fecha, hora, categorias, tipo_problema, descripcion, estado, patente)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)");
            $stmt->execute([
                $usuario_id,
                $ruta_id ?: null,
                $fecha,
                $hora,
                $categorias_pg,
                $tipo_problema_pg,
                $descripcion,
                $patente ?: null
            ]);
            $mensaje = "✅ Reclamo registrado correctamente.";
            $mensaje_tipo = "success";

            // Vaciar formulario tras éxito
            $fecha = date('Y-m-d');
            $hora = date('H:i');
            $ruta_id = '';
            $patente = '';
            $descripcion = '';
            $categorias = [];
            $tipo_problema = [];
        } catch (PDOException $e) {
            $mensaje = "❌ Error: " . $e->getMessage();
            $mensaje_tipo = "danger";
        }
    }
}

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

<!-- Aquí el botón Volver fijo a home.php -->
<button onclick="location.href='home.php'" class="btn btn-primary m-3">Volver</button>

<div class="container py-4">
    <h2 class="text-center mb-4">Formulario de Reclamos</h2>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje_tipo ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" onsubmit="return confirmarEnvio()">

        <div class="mb-3">
            <label class="form-label">Tipo de problema:</label><br>
            <label><input type="checkbox" name="tipo_problema[]" value="micro" <?= in_array('micro', $tipo_problema) ? 'checked' : '' ?>> Micro</label>
            <label><input type="checkbox" name="tipo_problema[]" value="plataforma" <?= in_array('plataforma', $tipo_problema) ? 'checked' : '' ?>> Plataforma</label>
        </div>

        <div class="mb-3">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="date" name="fecha" id="fecha" class="form-control" value="<?= htmlspecialchars($fecha) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="mb-3">
            <label for="hora" class="form-label">Hora</label>
            <input type="time" name="hora" id="hora" class="form-control" value="<?= htmlspecialchars($hora) ?>" required>
        </div>

        <div class="mb-3">
            <label for="ruta_id" class="form-label">Ruta</label>
            <select name="ruta_id" id="ruta_id" class="form-select">
                <option value="">-- Seleccionar ruta --</option>
                <?php foreach ($rutas as $ru): ?>
                    <option value="<?= $ru['id'] ?>" <?= ($ru['id'] == $ruta_id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ru['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="patente" class="form-label">Patente</label>
            <input type="text" name="patente" id="patente" class="form-control" maxlength="6" placeholder="Ej: ABCD12" value="<?= htmlspecialchars($patente) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Categorías:</label><br>
            <label><input type="checkbox" name="categorias[]" value="maltrato" <?= in_array('maltrato', $categorias) ? 'checked' : '' ?>> Maltrato</label>
            <label><input type="checkbox" name="categorias[]" value="retraso" <?= in_array('retraso', $categorias) ? 'checked' : '' ?>> Retraso</label>
            <label><input type="checkbox" name="categorias[]" value="mala_conduccion" <?= in_array('mala_conduccion', $categorias) ? 'checked' : '' ?>> Mala conducción</label>
            <label><input type="checkbox" name="categorias[]" value="otros" <?= in_array('otros', $categorias) ? 'checked' : '' ?>> Otros</label>
        </div>

        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="4" placeholder="Cuéntanos con más detalle tu problema." required><?= htmlspecialchars($descripcion) ?></textarea>
        </div>

        <button type="submit" name="add" class="btn btn-success">Enviar Reclamo</button>
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
function confirmarEnvio() {
    return confirm("¿Estás seguro de que deseas enviar este reclamo?");
}

// Desactiva ruta y patente si se selecciona "plataforma"
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
