<?php
// micros.php
require 'config.php'; // conexión a la DB

// Función para validar y preparar datos, recibe alertas y errores por referencia
function validarMicro($data, &$errores, &$alertas) {
    $patente = strtoupper(trim($data['patente']));
    $permiso_desde = $data['permiso_desde'];
    $permiso_hasta = $data['permiso_hasta'];
    $revision = $data['revision'];
    $ruta_id = $data['ruta_id'];
    $padron = $data['padron'];
    $herramientas = isset($data['herramientas']) ? true : false;
    $folio_seguro = trim($data['folio_seguro']);
    $seguro_desde = $data['seguro_desde'];
    $seguro_hasta = $data['seguro_hasta'];
    $mantenciones_al_dia = isset($data['mantenciones_al_dia']) ? true : false;

    $hoy = date('Y-m-d');

    // Validar patente: 6 caracteres alfanuméricos
    if (!preg_match('/^[A-Z0-9]{6}$/', $patente)) {
        $errores[] = "La patente debe tener exactamente 6 caracteres alfanuméricos (sin espacios ni símbolos).";
    }

    // Alertas específicas (no bloquean inserción)
    if ($permiso_hasta < $hoy) {
        $alertas[] = "El permiso de circulación está vencido.";
    }
    if ($revision < $hoy) {
        $alertas[] = "La revisión técnica está vencida.";
    }
    if ($seguro_hasta < $hoy) {
        $alertas[] = "El seguro está vencido.";
    }
    if (empty($ruta_id) || !is_numeric($ruta_id) || intval($ruta_id) <= 0) {
        $alertas[] = "El micro no tiene una ruta asignada.";
    }

    // Validar permiso fechas: permiso_desde <= permiso_hasta, permiso_hasta <= 31 marzo del año actual
    if (strtotime($permiso_desde) > strtotime($permiso_hasta)) {
        $errores[] = "La fecha de inicio del permiso no puede ser posterior a la fecha de vencimiento.";
    }

    // Validar revision: no puede tener más de 1 año de antigüedad
    $fecha_revision = strtotime($revision);
    if ($fecha_revision < strtotime('-1 year')) {
        $errores[] = "La revisión técnica no puede tener más de un año de antigüedad.";
    }

    // Validar padron: obligatorio, solo números, mínimo 8 dígitos
    if (!preg_match('/^[0-9]{8,}$/', $padron)) {
        $errores[] = "El padrón es obligatorio y debe tener al menos 8 dígitos numéricos.";
    }

    // Validar folio seguro: obligatorio, 8 números
    if (!preg_match('/^[0-9]{8}$/', $folio_seguro)) {
        $errores[] = "El folio del seguro es obligatorio y debe tener exactamente 8 números.";
    }

    // Validar seguro fechas: seguro_desde <= seguro_hasta
    if (strtotime($seguro_desde) > strtotime($seguro_hasta)) {
        $errores[] = "La fecha de inicio del seguro no puede ser posterior a la fecha de vencimiento.";
    }

    // Validar ruta_id numérico y >0
    if (!is_numeric($ruta_id) || intval($ruta_id) <= 0) {
        $errores[] = "Debe seleccionar una ruta válida.";
    }

    return [
        'patente' => $patente,
        'permiso_desde' => $permiso_desde,
        'permiso_hasta' => $permiso_hasta,
        'revision' => $revision,
        'ruta_id' => intval($ruta_id),
        'padron' => $padron,
        'herramientas' => $herramientas,
        'folio_seguro' => $folio_seguro,
        'seguro_desde' => $seguro_desde,
        'seguro_hasta' => $seguro_hasta,
        'mantenciones_al_dia' => $mantenciones_al_dia,
    ];
}

// Función para obtener rutas (para el select)
function obtenerRutas($pdo) {
    $stmt = $pdo->query("SELECT id, nombre FROM ruta ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// CRUD


$alertas = [];
$errores = [];

if (isset($_POST['crear'])) {
    $datos = validarMicro($_POST, $errores, $alertas);
    if (empty($errores)) {
        try {
            $sql = "INSERT INTO micro 
            (patente, permiso_desde, permiso_hasta, revision, ruta_id, padron, herramientas, folio_seguro, seguro_desde, seguro_hasta, mantenciones_al_dia) 
            VALUES (:patente, :permiso_desde, :permiso_hasta, :revision, :ruta_id, :padron, :herramientas, :folio_seguro, :seguro_desde, :seguro_hasta, :mantenciones_al_dia)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($datos);
            header("Location: micros.php");
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error al insertar: " . $e->getMessage();
        }
    }
}

if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM micro WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: micros.php");
    exit;
}

$micro_editar = null;
if (isset($_GET['editar'])) {
    $id = (int) $_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM micro WHERE id = ?");
    $stmt->execute([$id]);
    $micro_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (isset($_POST['actualizar'])) {
    $id = (int) $_POST['id'];
    $datos = validarMicro($_POST, $errores, $alertas);
    if (empty($errores)) {
        try {
            $sql = "UPDATE micro SET 
                patente = :patente, 
                permiso_desde = :permiso_desde,
                permiso_hasta = :permiso_hasta,
                revision = :revision,
                ruta_id = :ruta_id,
                padron = :padron,
                herramientas = :herramientas,
                folio_seguro = :folio_seguro,
                seguro_desde = :seguro_desde,
                seguro_hasta = :seguro_hasta,
                mantenciones_al_dia = :mantenciones_al_dia
                WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $datos['id'] = $id;
            $stmt->execute($datos);
            header("Location: micros.php");
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Obtener micros
$stmt = $pdo->query("SELECT m.*, r.nombre as ruta_nombre FROM micro m LEFT JOIN ruta r ON m.ruta_id = r.id ORDER BY m.id DESC");
$micros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener rutas para el select
$rutas = obtenerRutas($pdo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Gestión de Micros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="container mt-4">
    <h1>Gestión de Micros</h1>

    <?php if (!empty($alertas)): ?>
        <div class="alert alert-warning">
            <ul>
                <?php foreach ($alertas as $a): ?>
                    <li><?= htmlspecialchars($a) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errores as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Formulario crear/editar -->
    <form method="POST" class="mb-4">
        <input type="hidden" name="id" value="<?= $micro_editar['id'] ?? '' ?>">
        <div class="row mb-3">
            <div class="col-md-3">
                <label>Patente</label>
                <input type="text" name="patente" maxlength="6" class="form-control" required
                    value="<?= htmlspecialchars($micro_editar['patente'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Permiso Desde</label>
                <input type="date" name="permiso_desde" class="form-control" required
                    value="<?= htmlspecialchars($micro_editar['permiso_desde'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Permiso Hasta</label>
                <input type="date" name="permiso_hasta" class="form-control" required
                    value="<?= htmlspecialchars($micro_editar['permiso_hasta'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Revisión Técnica</label>
                <input type="date" name="revision" class="form-control" required
                    value="<?= htmlspecialchars($micro_editar['revision'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <label>Ruta</label>
                <select name="ruta_id" class="form-select" required>
                    <option value="">Seleccione ruta</option>
                    <?php foreach ($rutas as $ruta): ?>
                        <option value="<?= $ruta['id'] ?>" <?= (isset($micro_editar['ruta_id']) && $micro_editar['ruta_id'] == $ruta['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ruta['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label>Padrón</label>
                <input type="text" name="padron" class="form-control" required pattern="\d{6,}"
                    title="Solo números, mínimo 6 dígitos"
                    value="<?= htmlspecialchars($micro_editar['padron'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Herramientas</label><br>
                <input type="checkbox" name="herramientas" <?= (!empty($micro_editar['herramientas'])) ? 'checked' : '' ?>>
            </div>
            <div class="col-md-3">
                <label>Folio Seguro</label>
                <input type="text" name="folio_seguro" maxlength="8" class="form-control" required pattern="\d{8}"
                    title="Exactamente 8 números"
                    value="<?= htmlspecialchars($micro_editar['folio_seguro'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <label>Seguro Desde</label>
                <input type="date" name="seguro_desde" class="form-control" required
                    value="<?= htmlspecialchars($micro_editar['seguro_desde'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Seguro Hasta</label>
                <input type="date" name="seguro_hasta" class="form-control" required
                    value="<?= htmlspecialchars($micro_editar['seguro_hasta'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Mantenciones al día</label><br>
                <input type="checkbox" name="mantenciones_al_dia" <?= (!empty($micro_editar['mantenciones_al_dia'])) ? 'checked' : '' ?>>
            </div>
        </div>

        <button type="submit" name="<?= $micro_editar ? 'actualizar' : 'crear' ?>" class="btn btn-primary">
            <?= $micro_editar ? 'Actualizar' : 'Crear' ?>
        </button>
        <?php if ($micro_editar): ?>
            <a href="micros.php" class="btn btn-secondary">Cancelar</a>
        <?php endif; ?>
    </form>

    <!-- Tabla de micros -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Patente</th>
                <th>Permiso Desde</th>
                <th>Permiso Hasta</th>
                <th>Revisión Técnica</th>
                <th>Ruta</th>
                <th>Padrón</th>
                <th>Herramientas</th>
                <th>Folio Seguro</th>
                <th>Seguro Desde</th>
                <th>Seguro Hasta</th>
                <th>Mantenciones al día</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($micros as $m): ?>
                <tr>
                    <td><?= $m['id'] ?></td>
                    <td><?= htmlspecialchars($m['patente']) ?></td>
                    <td><?= htmlspecialchars($m['permiso_desde']) ?></td>
                    <td><?= htmlspecialchars($m['permiso_hasta']) ?></td>
                    <td><?= htmlspecialchars($m['revision']) ?></td>
                    <td><?= htmlspecialchars($m['ruta_nombre']) ?></td>
                    <td><?= htmlspecialchars($m['padron']) ?></td>
                    <td><?= $m['herramientas'] ? 'Sí' : 'No' ?></td>
                    <td><?= htmlspecialchars($m['folio_seguro']) ?></td>
                    <td><?= htmlspecialchars($m['seguro_desde']) ?></td>
                    <td><?= htmlspecialchars($m['seguro_hasta']) ?></td>
                    <td><?= $m['mantenciones_al_dia'] ? 'Sí' : 'No' ?></td>
                    <td>
                        <a href="?editar=<?= $m['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="?eliminar=<?= $m['id'] ?>" onclick="return confirm('¿Eliminar este micro?');" class="btn btn-sm btn-danger">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
