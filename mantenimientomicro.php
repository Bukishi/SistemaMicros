<?php
include 'config.php';
session_start();

function h($valor) {
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$mensaje = '';
$mensaje_tipo = '';

$patente = '';
$tipo = '';
$descripcion = '';
$kilometraje = '';
$fecha_ingreso = date('Y-m-d'); // Siempre hoy al inicio
$fecha_salida = '';
$confirmado = false;
$ruta_id = '';

if (isset($_POST['add'])) {
    $patente = strtoupper(trim($_POST['patente'] ?? ''));
    $tipo = $_POST['tipo'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $kilometraje = $_POST['kilometraje'] ?? null;
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? date('Y-m-d');
    $fecha_salida = $_POST['fecha_salida'] ?? '';
    $confirmado = isset($_POST['confirmado']) && $_POST['confirmado'] ? true : false;
    $ruta_id = $_POST['ruta_id'] ?? null;

    if (!$patente || !$tipo || !$fecha_ingreso || !$fecha_salida || !$ruta_id) {
        $mensaje = "Todos los campos obligatorios deben ser completados.";
        $mensaje_tipo = "danger";
    } elseif (strtotime($fecha_ingreso) > strtotime(date('Y-m-d'))) {
        $mensaje = "La fecha de ingreso no puede ser mayor al día actual.";
        $mensaje_tipo = "danger";
    } elseif (strtotime($fecha_salida) < strtotime($fecha_ingreso)) {
        $mensaje = "La fecha de salida no puede ser menor que la fecha de ingreso.";
        $mensaje_tipo = "danger";
    } elseif (strtotime($fecha_salida) > strtotime("+7 days", strtotime($fecha_ingreso))) {
        $mensaje = "La fecha de salida no puede superar una semana desde la fecha de ingreso.";
        $mensaje_tipo = "danger";
    } elseif ($tipo === 'cambio_aceite') {
        if (!is_numeric($kilometraje) || $kilometraje < 0 || $kilometraje > 999999) {
            $mensaje = "Kilometraje inválido. Debe estar entre 0 y 999.999.";
            $mensaje_tipo = "danger";
        } else {
            if (!is_numeric($descripcion)) {
                $mensaje = "Para cambio de aceite, la descripción debe ser un número que indique el kilometraje.";
                $mensaje_tipo = "danger";
            } elseif ((float)$descripcion > ((float)$kilometraje + 10000)) {
                $mensaje = "La descripción (kilometraje para próximo cambio) no puede exceder en más de 10.000 km el kilometraje ingresado.";
                $mensaje_tipo = "danger";
            } elseif ((float)$descripcion < (float)$kilometraje) {
                $mensaje = "La descripción (kilometraje para próximo cambio) no puede ser menor al kilometraje ingresado.";
                $mensaje_tipo = "danger";
            }
        }
    }

    if (!$mensaje) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM micro WHERE patente = ?");
            $stmt->execute([$patente]);
            $micro = $stmt->fetch();

            if (!$micro) {
                throw new Exception("La patente ingresada no está registrada como micro. Regístrala primero.");
            }

            $micro_id = $micro['id'];

            $stmt = $pdo->prepare("INSERT INTO mantenimiento (micro_id, fecha, tipo, descripcion, fecha_ingreso, fecha_salida, kilometraje, confirmado, patente, ruta)
                VALUES (?, CURRENT_DATE, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $micro_id,
                $tipo,
                $descripcion,
                $fecha_ingreso,
                $fecha_salida,
                $tipo === 'cambio_aceite' ? $kilometraje : null,
                $confirmado ? 1 : 0,
                $patente,
                $ruta_id
            ]);

            $patente = '';
            $tipo = '';
            $descripcion = '';
            $kilometraje = '';
            $fecha_ingreso = date('Y-m-d');
            $fecha_salida = '';
            $confirmado = false;
            $ruta_id = '';

            $mensaje = "Mantenimiento agregado correctamente.";
            $mensaje_tipo = "success";

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'patente_unica')) {
                $mensaje = "La patente <strong>" . h($patente) . "</strong> ya tiene un mantenimiento registrado. No puede repetirse.";
                $mensaje_tipo = "warning";
            } else {
                $mensaje = "Error al agregar mantenimiento: " . $e->getMessage();
                $mensaje_tipo = "danger";
            }
        } catch (Exception $e) {
            $mensaje = "Error al agregar mantenimiento: " . $e->getMessage();
            $mensaje_tipo = "danger";
        }
    }
}

if (isset($_POST['edit'])) {
    $id = $_POST['id'] ?? null;
    $tipo = $_POST['tipo'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $kilometraje = $_POST['kilometraje'] ?? null;
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
    $fecha_salida = $_POST['fecha_salida'] ?? '';
    $confirmado = isset($_POST['confirmado']) && $_POST['confirmado'] ? true : false;
    $ruta_id = $_POST['ruta_id'] ?? null;

    if (!$id || !$tipo || !$fecha_ingreso || !$fecha_salida || !$ruta_id) {
        $mensaje = "Todos los campos obligatorios deben ser completados para editar.";
        $mensaje_tipo = "danger";
    } elseif (strtotime($fecha_ingreso) > strtotime(date('Y-m-d'))) {
        $mensaje = "La fecha de ingreso no puede ser mayor al día actual.";
        $mensaje_tipo = "danger";
    } elseif (strtotime($fecha_salida) < strtotime($fecha_ingreso)) {
        $mensaje = "La fecha de salida no puede ser menor que la fecha de ingreso.";
        $mensaje_tipo = "danger";
    } elseif (strtotime($fecha_salida) > strtotime("+7 days", strtotime($fecha_ingreso))) {
        $mensaje = "La fecha de salida no puede superar una semana desde la fecha de ingreso.";
        $mensaje_tipo = "danger";
    } elseif ($tipo === 'cambio_aceite') {
        if (!is_numeric($kilometraje) || $kilometraje < 0 || $kilometraje > 999999) {
            $mensaje = "Kilometraje inválido. Debe estar entre 0 y 999.999.";
            $mensaje_tipo = "danger";
        } else {
            if (!is_numeric($descripcion)) {
                $mensaje = "Para cambio de aceite, la descripción debe ser un número que indique el kilometraje.";
                $mensaje_tipo = "danger";
            } elseif ((float)$descripcion > ((float)$kilometraje + 10000)) {
                $mensaje = "La descripción (kilometraje para próximo cambio) no puede exceder en más de 10.000 km el kilometraje ingresado.";
                $mensaje_tipo = "danger";
            } elseif ((float)$descripcion < (float)$kilometraje) {
                $mensaje = "La descripción (kilometraje para próximo cambio) no puede ser menor al kilometraje ingresado.";
                $mensaje_tipo = "danger";
            }
        }
    }

    if (!$mensaje) {
        try {
            $stmt = $pdo->prepare("UPDATE mantenimiento SET tipo = ?, descripcion = ?, fecha_ingreso = ?, fecha_salida = ?, kilometraje = ?, confirmado = ?, ruta = ? WHERE id = ?");
            $stmt->execute([
                $tipo,
                $descripcion,
                $fecha_ingreso,
                $fecha_salida,
                $tipo === 'cambio_aceite' ? $kilometraje : null,
                $confirmado ? 1 : 0,
                $ruta_id,
                $id
            ]);

            $patente = '';
            $tipo = '';
            $descripcion = '';
            $kilometraje = '';
            $fecha_ingreso = date('Y-m-d');
            $fecha_salida = '';
            $confirmado = false;
            $ruta_id = '';

            $mensaje = "Mantenimiento actualizado correctamente.";
            $mensaje_tipo = "success";

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'patente_unica')) {
                $mensaje = "La patente <strong>" . h($patente) . "</strong> ya tiene un mantenimiento registrado. No puede repetirse.";
                $mensaje_tipo = "warning";
            } else {
                $mensaje = "Error al editar mantenimiento: " . $e->getMessage();
                $mensaje_tipo = "danger";
            }
        }
    }
}

if (isset($_POST['delete'])) {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM mantenimiento WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

$mantenimientos = $pdo->query("SELECT m.*, r.nombre as ruta_nombre, mi.ruta_id FROM mantenimiento m LEFT JOIN micro mi ON m.micro_id = mi.id LEFT JOIN ruta r ON mi.ruta_id = r.id ORDER BY m.fecha DESC")->fetchAll();
$rutas = $pdo->query("SELECT id, nombre FROM ruta ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Mantenimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        input[name="patente"] { text-transform: uppercase; }
        input[type="checkbox"] { width: 18px; height: 18px; border: 2px solid #444; }
        tr.no-confirmado { color: red; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">

    <button type="button" class="btn btn-primary mb-3" onclick="history.back()">Volver</button>

    <h2 class="mb-4">Registro de Mantenimientos</h2>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= h($mensaje_tipo) ?>"><?= $mensaje_tipo === 'warning' ? $mensaje : h($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" class="border p-4 bg-white shadow-sm mb-4" id="form-mantenimiento" onsubmit="return validarFormulario();">
        <input type="hidden" name="id" id="id" value="<?= h($id ?? '') ?>" />
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="patente" class="form-label">Patente *</label>
                <input type="text" name="patente" id="patente" class="form-control" maxlength="6" required
                    value="<?= h($patente) ?>"
                    placeholder="Ej: ABCD12"
                    oninput="this.value = this.value.toUpperCase();"
                />
            </div>
            <div class="col-md-4">
                <label for="ruta_id" class="form-label">Ruta *</label>
                <select name="ruta_id" id="ruta_id" class="form-select" required>
                    <option value="">-- Selecciona ruta --</option>
                    <?php foreach ($rutas as $ruta): ?>
                        <option value="<?= $ruta['id'] ?>" <?= $ruta['id'] == $ruta_id ? 'selected' : '' ?>><?= h($ruta['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="tipo" class="form-label">Tipo de mantenimiento *</label>
                <select name="tipo" id="tipo" class="form-select" required onchange="actualizarCampos();">
                    <option value="">-- Selecciona --</option>
                    <option value="ajuste_frenos" <?= $tipo === 'ajuste_frenos' ? 'selected' : '' ?>>Ajuste de frenos</option>
                    <option value="cambio_aceite" <?= $tipo === 'cambio_aceite' ? 'selected' : '' ?>>Cambio de aceite</option>
                    <option value="cambio_neumaticos" <?= $tipo === 'cambio_neumaticos' ? 'selected' : '' ?>>Cambio de neumáticos</option>
                    <option value="verificacion_luces" <?= $tipo === 'verificacion_luces' ? 'selected' : '' ?>>Verificación de luces</option>
                </select>
            </div>
        </div>

        <div class="mb-3" id="kilometraje_group" style="display: <?= $tipo === 'cambio_aceite' ? 'block' : 'none' ?>;">
            <label for="kilometraje" class="form-label">Kilometraje</label>
            <input type="number" name="kilometraje" id="kilometraje" class="form-control" min="0" max="999999" value="<?= h($kilometraje) ?>" oninput="validarDescripcion()" />
        </div>

        <div class="mb-3" id="descripcion_group">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea 
                name="descripcion" 
                id="descripcion" 
                class="form-control" 
                rows="3"
                placeholder="<?= h($tipo === 'cambio_aceite' ? 'Ingrese a qué kilometraje se debe realizar el siguiente cambio.' : 'Escribe algo extra aquí sobre las mantenciones realizadas.') ?>"
                oninput="validarDescripcion()"
            ><?= h($descripcion) ?></textarea>
            <div class="form-text text-danger" id="errorDescripcion" style="display:none;"></div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="fecha_ingreso" class="form-label">Fecha de ingreso *</label>
                <input type="date" name="fecha_ingreso" id="fecha_ingreso" class="form-control" required
                    value="<?= h($fecha_ingreso) ?>"
                    max="<?= date('Y-m-d') ?>"
                />
            </div>
            <div class="col-md-6">
                <label for="fecha_salida" class="form-label">Fecha de salida *</label>
                <input type="date" name="fecha_salida" id="fecha_salida" class="form-control" required
                    value="<?= h($fecha_salida) ?>"
                    min="<?= h($fecha_ingreso) ?>"
                />
            </div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="confirmado" id="confirmado" class="form-check-input" <?= $confirmado ? 'checked' : '' ?> />
            <label for="confirmado" class="form-check-label fw-bold">Confirmo que el mantenimiento fue realizado</label>
        </div>

        <button type="submit" name="add" id="btn-add" class="btn btn-primary">Agregar Mantenimiento</button>
        <button type="submit" name="edit" id="btn-edit" class="btn btn-success" style="display:none;">Guardar Cambios</button>
        <button type="button" id="btn-cancel" class="btn btn-secondary" style="display:none;">Cancelar</button>
    </form>

    <h4>Historial de Mantenimientos</h4>
    <table class="table table-bordered table-striped table-hover mt-3">
        <thead class="table-primary">
            <tr>
                <th>Patente</th>
                <th>Ruta</th>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Fecha ingreso</th>
                <th>Fecha salida</th>
                <th>Kilometraje</th>
                <th>Confirmado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mantenimientos as $m): ?>
                <tr class="<?= $m['confirmado'] ? '' : 'no-confirmado' ?>">
                    <td><?= h($m['patente']) ?></td>
                    <td><?= h($m['ruta_nombre'] ?? '') ?></td>
                    <td><?= h(str_replace('_', ' ', $m['tipo'])) ?></td>
                    <td><?= h($m['descripcion']) ?></td>
                    <td><?= date('d-m-Y', strtotime($m['fecha_ingreso'])) ?></td>
                    <td><?= date('d-m-Y', strtotime($m['fecha_salida'])) ?></td>
                    <td><?= $m['kilometraje'] !== null ? number_format($m['kilometraje']) : '-' ?></td>
                    <td><?= $m['confirmado'] ? '✅' : '❌' ?></td>
                    <td>
                        <?php
                        $data = [
                            'id' => $m['id'],
                            'patente' => $m['patente'],
                            'ruta_id' => $m['ruta_id'],
                            'tipo' => $m['tipo'],
                            'descripcion' => $m['descripcion'],
                            'fecha_ingreso' => $m['fecha_ingreso'],
                            'fecha_salida' => $m['fecha_salida'],
                            'kilometraje' => $m['kilometraje'],
                            'confirmado' => $m['confirmado'] ? '1' : '0'
                        ];
                        $json_data = h(json_encode($data));
                        ?>
                        <button class="btn btn-sm btn-warning btn-edit" data-info="<?= $json_data ?>">Editar</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este mantenimiento?');">
                            <input type="hidden" name="id" value="<?= h($m['id']) ?>" />
                            <button type="submit" name="delete" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

<script>
function actualizarCampos() {
    const tipo = document.getElementById('tipo').value;
    const kmGroup = document.getElementById('kilometraje_group');
    const descripcion = document.getElementById('descripcion');
    const errorDesc = document.getElementById('errorDescripcion');

    if (tipo === 'cambio_aceite') {
        kmGroup.style.display = 'block';
        descripcion.placeholder = 'Ingrese a qué kilometraje se debe realizar el siguiente cambio.';
    } else {
        kmGroup.style.display = 'none';
        descripcion.placeholder = 'Escribe algo extra aquí sobre las mantenciones realizadas.';
    }

    errorDesc.style.display = 'none';
}

function validarDescripcion() {
    const tipo = document.getElementById('tipo').value;
    const descripcion = document.getElementById('descripcion');
    const km = document.getElementById('kilometraje').value;
    const errorDesc = document.getElementById('errorDescripcion');
    errorDesc.style.display = 'none';

    if (tipo === 'cambio_aceite') {
        if (descripcion.value.trim() === '') {
            errorDesc.textContent = 'La descripción no puede estar vacía.';
            errorDesc.style.display = 'block';
            return false;
        }
        if (isNaN(descripcion.value)) {
            errorDesc.textContent = 'La descripción debe ser un número indicando el kilometraje.';
            errorDesc.style.display = 'block';
            return false;
        }
        if (parseFloat(descripcion.value) < parseFloat(km)) {
            errorDesc.textContent = 'El kilometraje para próximo cambio no puede ser menor al actual.';
            errorDesc.style.display = 'block';
            return false;
        }
        if (parseFloat(descripcion.value) > parseFloat(km) + 10000) {
            errorDesc.textContent = 'El kilometraje para próximo cambio no puede exceder en más de 10.000 km el actual.';
            errorDesc.style.display = 'block';
            return false;
        }
    }
    return true;
}

function validarFormulario() {
    const patente = document.getElementById('patente').value.trim();
    const tipo = document.getElementById('tipo').value;
    const fechaIngreso = document.getElementById('fecha_ingreso').value;
    const fechaSalida = document.getElementById('fecha_salida').value;
    const ruta = document.getElementById('ruta_id').value;

    if (!patente || !tipo || !fechaIngreso || !fechaSalida || !ruta) {
        alert('Por favor complete todos los campos obligatorios.');
        return false;
    }

    const hoy = new Date();
    const fechaIng = new Date(fechaIngreso);
    const fechaSal = new Date(fechaSalida);

    if (fechaIng > hoy) {
        alert('La fecha de ingreso no puede ser mayor al día actual.');
        return false;
    }

    if (fechaSal < fechaIng) {
        alert('La fecha de salida no puede ser menor que la fecha de ingreso.');
        return false;
    }

    if ((fechaSal - fechaIng) / (1000 * 60 * 60 * 24) > 7) {
        alert('La fecha de salida no puede superar una semana desde la fecha de ingreso.');
        return false;
    }

    return validarDescripcion();
}

document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', () => {
        const data = JSON.parse(button.getAttribute('data-info'));

        document.getElementById('id').value = data.id;
        document.getElementById('patente').value = data.patente;
        document.getElementById('ruta_id').value = data.ruta_id;
        document.getElementById('tipo').value = data.tipo;
        actualizarCampos();
        document.getElementById('descripcion').value = data.descripcion;
        document.getElementById('fecha_ingreso').value = data.fecha_ingreso;
        document.getElementById('fecha_salida').value = data.fecha_salida;
        document.getElementById('kilometraje').value = data.kilometraje || '';
        document.getElementById('confirmado').checked = data.confirmado === '1';

        document.getElementById('btn-add').style.display = 'none';
        document.getElementById('btn-edit').style.display = 'inline-block';
        document.getElementById('btn-cancel').style.display = 'inline-block';

        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

document.getElementById('btn-cancel').addEventListener('click', () => {
    document.getElementById('form-mantenimiento').reset();
    document.getElementById('id').value = '';
    document.getElementById('btn-add').style.display = 'inline-block';
    document.getElementById('btn-edit').style.display = 'none';
    document.getElementById('btn-cancel').style.display = 'none';
    actualizarCampos();
});
actualizarCampos();
</script>

</body>
</html>
