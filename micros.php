<?php
require 'config.php';

function validarMicro($data, &$errores, &$alertas) {
    $patente = strtoupper(trim($data['patente'] ?? ''));

    $permiso_desde = $data['permiso_desde'] ?? '';
    $permiso_hasta = $data['permiso_hasta'] ?? '';
    $revision_desde = $data['revision_desde'] ?? '';
    $revision_hasta = $data['revision_hasta'] ?? '';
    $ruta_id = $data['ruta_id'] ?? '';
    $padron = trim($data['padron'] ?? '');
    // Booleanos convertidos a int para evitar errores
    $herramientas = (isset($data['herramientas']) && $data['herramientas'] == '1') ? 1 : 0;
    $folio_seguro = trim($data['folio_seguro'] ?? '');
    $seguro_desde = $data['seguro_desde'] ?? '';
    $seguro_hasta = $data['seguro_hasta'] ?? '';
    $mantenciones_al_dia = (isset($data['mantenciones_al_dia']) && $data['mantenciones_al_dia'] == '1') ? 1 : 0;

    // Fechas límites
    $permiso_min = strtotime(date('Y') . '-03-01');
    $permiso_max = strtotime('2026-03-31');
    $revision_min = strtotime('2025-07-15');
    $revision_max = strtotime('2026-12-12');

    // Validar patente
    if (!preg_match('/^[A-Z]{4}[0-9]{2}$/', $patente)) {
        $errores[] = "La patente debe tener 4 letras seguidas de 2 números (ej: ABCD12).";
    }

    // Validar permiso
    if (!$permiso_desde || !$permiso_hasta) {
        $errores[] = "Debe ingresar fecha inicio y término del permiso de circulación.";
    } else {
        $pd = strtotime($permiso_desde);
        $ph = strtotime($permiso_hasta);
        if ($pd < $permiso_min) {
            $errores[] = "La fecha de inicio del permiso no puede ser anterior al 01-03-" . date('Y') . ".";
        }
        if ($ph > $permiso_max) {
            $errores[] = "La fecha de término del permiso no puede ser posterior al 31-03-2026.";
        }
        if ($ph < $pd) {
            $errores[] = "La fecha de término del permiso no puede ser anterior a la fecha de inicio.";
        }
        $duracion_permiso = ($ph - $pd) / (60*60*24);
        if ($duracion_permiso != 365 && $duracion_permiso != 366) { // Exactamente 1 año (considera bisiesto)
            $errores[] = "El permiso de circulación debe durar exactamente 1 año.";
        }
    }

    // Validar revisión
    if (!$revision_desde || !$revision_hasta) {
        $errores[] = "Debe ingresar fecha inicio y término de la revisión técnica.";
    } else {
        $rd = strtotime($revision_desde);
        $rh = strtotime($revision_hasta);
        if ($rd < $revision_min) {
            $errores[] = "La fecha de inicio de la revisión no puede ser anterior al 15-07-2025.";
        }
        if ($rh > $revision_max) {
            $errores[] = "La fecha de término de la revisión no puede ser posterior al 12-12-2026.";
        }
        if ($rh < $rd) {
            $errores[] = "La fecha de término de la revisión no puede ser anterior a la fecha de inicio.";
        }
        $duracion_revision = ($rh - $rd) / (60*60*24);
        if ($duracion_revision != 365 && $duracion_revision != 366) {
            $errores[] = "La revisión técnica debe durar exactamente 1 año.";
        }
    }

    // Validar seguro (mismas reglas que permiso)
    if (!$seguro_desde || !$seguro_hasta) {
        $errores[] = "Debe ingresar fecha inicio y término del seguro.";
    } else {
        $sd = strtotime($seguro_desde);
        $sh = strtotime($seguro_hasta);
        if ($sd < $permiso_min) {
            $errores[] = "La fecha de inicio del seguro no puede ser anterior al 01-03-" . date('Y') . ".";
        }
        if ($sh > $permiso_max) {
            $errores[] = "La fecha de término del seguro no puede ser posterior al 31-03-2026.";
        }
        if ($sh < $sd) {
            $errores[] = "La fecha de término del seguro no puede ser anterior a la fecha de inicio.";
        }
        $duracion_seguro = ($sh - $sd) / (60*60*24);
        if ($duracion_seguro != 365 && $duracion_seguro != 366) {
            $errores[] = "El seguro debe durar exactamente 1 año.";
        }
    }

    // Validar padrón
    if (!preg_match('/^\d{6,}$/', $padron)) {
        if ($padron === '') {
            $alertas[] = "El padrón es obligatorio y debe tener al menos 6 números.";
        } else {
            $errores[] = "El padrón debe tener al menos 6 dígitos numéricos.";
        }
    }

    // Validar folio seguro
    if (!preg_match('/^\d{8}$/', $folio_seguro)) {
        if ($folio_seguro === '') {
            $alertas[] = "El folio del seguro es obligatorio y debe tener exactamente 8 números.";
        } else {
            $errores[] = "El folio del seguro debe tener exactamente 8 dígitos numéricos.";
        }
    }

    // Validar ruta
    if (!is_numeric($ruta_id) || intval($ruta_id) <= 0) {
        $errores[] = "Debe seleccionar una ruta válida.";
    }

    return [
        'patente' => $patente,
        'permiso_desde' => $permiso_desde,
        'permiso_hasta' => $permiso_hasta,
        'revision_desde' => $revision_desde,
        'revision_hasta' => $revision_hasta,
        'ruta_id' => intval($ruta_id),
        'padron' => $padron,
        'herramientas' => $herramientas,
        'folio_seguro' => $folio_seguro,
        'seguro_desde' => $seguro_desde,
        'seguro_hasta' => $seguro_hasta,
        'mantenciones_al_dia' => $mantenciones_al_dia,
    ];
}

function obtenerRutas($pdo) {
    $stmt = $pdo->query("SELECT id, nombre FROM ruta ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$alertas = [];
$errores = [];

if (isset($_POST['crear'])) {
    $datos = validarMicro($_POST, $errores, $alertas);
    if (empty($errores)) {
        try {
            $sql = "INSERT INTO micro 
                (patente, permiso_desde, permiso_hasta, revision_desde, revision_hasta, ruta_id, padron, herramientas, folio_seguro, seguro_desde, seguro_hasta, mantenciones_al_dia) 
                VALUES (:patente, :permiso_desde, :permiso_hasta, :revision_desde, :revision_hasta, :ruta_id, :padron, :herramientas, :folio_seguro, :seguro_desde, :seguro_hasta, :mantenciones_al_dia)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($datos);
            header("Location: micros.php");
            exit;
        } catch (PDOException $e) {
            $errores[] = "Error al insertar: " . $e->getMessage();
        }
    }
}

$micro_editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM micro WHERE id = ?");
    $stmt->execute([$id]);
    $micro_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (isset($_POST['actualizar'])) {
    $id = (int)$_POST['id'];
    $datos = validarMicro($_POST, $errores, $alertas);
    if (empty($errores)) {
        try {
            $sql = "UPDATE micro SET 
                patente = :patente, 
                permiso_desde = :permiso_desde,
                permiso_hasta = :permiso_hasta,
                revision_desde = :revision_desde,
                revision_hasta = :revision_hasta,
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

if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM micro WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: micros.php");
    exit;
}

$stmt = $pdo->query("SELECT m.*, r.nombre as ruta_nombre FROM micro m LEFT JOIN ruta r ON m.ruta_id = r.id ORDER BY m.id DESC");
$micros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rutas = obtenerRutas($pdo);

function formatoFecha($fecha) {
    if (!$fecha) return '';
    $timestamp = strtotime($fecha);
    return date('d-m-Y', $timestamp);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Gestión de Micros</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
    input[type="checkbox"] {
        width: 18px;
        height: 18px;
    }
    /* Para mostrar en rojo fila si herramientas o mantenciones no están marcadas */
    .no-marcado {
        background-color: #f8d7da !important;
        color: #842029 !important;
    }
</style>
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

    <form method="POST" id="form-micro" class="mb-4">
        <input type="hidden" name="id" value="<?= htmlspecialchars($micro_editar['id'] ?? '') ?>">

        <div class="row mb-3">
            <div class="col-md-3">
                <label>Patente</label>
                <input type="text" name="patente" maxlength="6" class="form-control" required
                    value="<?= htmlspecialchars($_POST['patente'] ?? $micro_editar['patente'] ?? '') ?>"
                    pattern="[A-Za-z]{4}[0-9]{2}"
                    title="4 letras seguidas de 2 números. Ejemplo: ABCD12">
            </div>
            <div class="col-md-3">
                <label>Permiso Desde</label>
                <input type="date" name="permiso_desde" class="form-control" required
                    value="<?= htmlspecialchars($_POST['permiso_desde'] ?? $micro_editar['permiso_desde'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Permiso Hasta</label>
                <input type="date" name="permiso_hasta" class="form-control" required
                    value="<?= htmlspecialchars($_POST['permiso_hasta'] ?? $micro_editar['permiso_hasta'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Revisión Desde</label>
                <input type="date" name="revision_desde" class="form-control" required
                    value="<?= htmlspecialchars($_POST['revision_desde'] ?? $micro_editar['revision_desde'] ?? '') ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <label>Revisión Hasta</label>
                <input type="date" name="revision_hasta" class="form-control" required
                    value="<?= htmlspecialchars($_POST['revision_hasta'] ?? $micro_editar['revision_hasta'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Ruta</label>
                <select name="ruta_id" class="form-select" required>
                    <option value="">Seleccione ruta</option>
                    <?php foreach ($rutas as $ruta): ?>
                        <option value="<?= $ruta['id'] ?>" <?= ((($_POST['ruta_id'] ?? $micro_editar['ruta_id'] ?? '') == $ruta['id']) ? 'selected' : '') ?>>
                            <?= htmlspecialchars($ruta['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label>Padrón</label>
                <input type="text" name="padron" class="form-control" required pattern="\d{6,}"
                    title="Solo números, mínimo 6 dígitos"
                    placeholder="Mínimo 6 números"
                    value="<?= htmlspecialchars($_POST['padron'] ?? $micro_editar['padron'] ?? '') ?>">
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <!-- hidden para enviar siempre valor -->
                <input type="hidden" name="herramientas" value="0">
                <input type="checkbox" name="herramientas" id="herramientas" value="1" class="form-check-input"
                    <?= (($_POST['herramientas'] ?? $micro_editar['herramientas'] ?? false) ? 'checked' : '') ?>>
                <label for="herramientas" class="ms-2 mb-0">Herramientas</label>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <label>Folio Seguro</label>
                <input type="text" name="folio_seguro" maxlength="8" class="form-control" required pattern="\d{8}"
                    title="Exactamente 8 números"
                    placeholder="Exactamente 8 números"
                    value="<?= htmlspecialchars($_POST['folio_seguro'] ?? $micro_editar['folio_seguro'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Seguro Desde</label>
                <input type="date" name="seguro_desde" class="form-control" required
                    value="<?= htmlspecialchars($_POST['seguro_desde'] ?? $micro_editar['seguro_desde'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label>Seguro Hasta</label>
                <input type="date" name="seguro_hasta" class="form-control" required
                    value="<?= htmlspecialchars($_POST['seguro_hasta'] ?? $micro_editar['seguro_hasta'] ?? '') ?>">
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <!-- hidden para enviar siempre valor -->
                <input type="hidden" name="mantenciones_al_dia" value="0">
                <input type="checkbox" name="mantenciones_al_dia" id="mantenciones_al_dia" value="1" class="form-check-input"
                    <?= (($_POST['mantenciones_al_dia'] ?? $micro_editar['mantenciones_al_dia'] ?? false) ? 'checked' : '') ?>>
                <label for="mantenciones_al_dia" class="ms-2 mb-0">Mantenciones al día</label>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="<?= $micro_editar ? 'actualizar' : 'crear' ?>" class="btn btn-primary">
                <?= $micro_editar ? 'Actualizar' : 'Crear' ?>
            </button>
            <a href="home.php" class="btn btn-secondary">Volver</a>
            <?php if ($micro_editar): ?>
                <a href="micros.php" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>

    <table class="table table-bordered table-hover">
        <thead class="table-primary text-center">
            <tr>
                <th>ID</th>
                <th>Patente</th>
                <th>Permiso Desde</th>
                <th>Permiso Hasta</th>
                <th>Revisión Desde</th>
                <th>Revisión Hasta</th>
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
            <tr <?= (!$m['herramientas'] || !$m['mantenciones_al_dia'] ? 'class="no-marcado"' : '') ?>>
                <td class="text-center"><?= $m['id'] ?></td>
                <td><?= htmlspecialchars($m['patente']) ?></td>
                <td class="text-center"><?= formatoFecha($m['permiso_desde']) ?></td>
                <td class="text-center"><?= formatoFecha($m['permiso_hasta']) ?></td>
                <td class="text-center"><?= formatoFecha($m['revision_desde']) ?></td>
                <td class="text-center"><?= formatoFecha($m['revision_hasta']) ?></td>
                <td><?= htmlspecialchars($m['ruta_nombre']) ?></td>
                <td><?= htmlspecialchars($m['padron']) ?></td>
                <td class="text-center">
                    <?= $m['herramientas'] ? 'Sí' : '<span class="text-danger fw-bold">No</span>' ?>
                </td>
                <td><?= htmlspecialchars($m['folio_seguro']) ?></td>
                <td class="text-center"><?= formatoFecha($m['seguro_desde']) ?></td>
                <td class="text-center"><?= formatoFecha($m['seguro_hasta']) ?></td>
                <td class="text-center">
                    <?= $m['mantenciones_al_dia'] ? 'Sí' : '<span class="text-danger fw-bold">No</span>' ?>
                </td>
                <td class="text-center">
                    <a href="?editar=<?= $m['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                    <a href="?eliminar=<?= $m['id'] ?>" onclick="return confirm('¿Seguro que desea eliminar este micro?')" class="btn btn-sm btn-danger">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById('form-micro').addEventListener('submit', function(event) {
    let herramientas = document.getElementById('herramientas').checked;
    let mantenciones = document.getElementById('mantenciones_al_dia').checked;

    if (!herramientas || !mantenciones) {
        let mensaje = "Las siguientes opciones no están marcadas:\n";
        if (!herramientas) mensaje += "- Herramientas\n";
        if (!mantenciones) mensaje += "- Mantenciones al día\n";
        mensaje += "\n¿Seguro que desea continuar sin marcarlas?";
        if (!confirm(mensaje)) {
            event.preventDefault();
        }
    }
});
</script>

</body>
</html>
