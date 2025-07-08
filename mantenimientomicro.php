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

if (isset($_POST['add'])) {
    $patente = strtoupper(trim($_POST['patente'] ?? ''));
    $tipo = $_POST['tipo'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $kilometraje = $_POST['kilometraje'] ?? null;
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? null;
    $fecha_salida = $_POST['fecha_salida'] ?? null;
    $confirmado = isset($_POST['confirmado']);
    $ruta_id = $_POST['ruta_id'] ?? null;

    if (!$patente || !$tipo || !$fecha_ingreso || !$fecha_salida) {
        $mensaje = "Todos los campos obligatorios deben ser completados.";
        $mensaje_tipo = "danger";
    } elseif (strtotime($fecha_salida) < strtotime($fecha_ingreso)) {
        $mensaje = "La fecha de salida no puede ser menor que la de ingreso.";
        $mensaje_tipo = "danger";
    } elseif ($tipo === 'cambio_aceite' && (!is_numeric($kilometraje) || $kilometraje < 0 || $kilometraje > 999999)) {
        $mensaje = "Kilometraje inválido. Debe estar entre 0 y 999.999.";
        $mensaje_tipo = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM micro WHERE patente = ?");
            $stmt->execute([$patente]);
            $micro = $stmt->fetch();

            if (!$micro) {
                throw new Exception("La patente ingresada no está registrada como micro. Regístrala primero.");
            }

            $micro_id = $micro['id'];

            $stmt = $pdo->prepare("INSERT INTO mantenimiento (micro_id, fecha, tipo, descripcion, fecha_ingreso, fecha_salida, kilometraje, confirmado, patente, ruta_manual)
                VALUES (?, CURRENT_DATE, ?, ?, ?, ?, ?, ?, ?, NULL)");
            $stmt->execute([
                $micro_id,
                $tipo,
                $descripcion,
                $fecha_ingreso,
                $fecha_salida,
                $tipo === 'cambio_aceite' ? $kilometraje : null,
                $confirmado,
                $patente
            ]);

            $mensaje = "Mantenimiento agregado correctamente.";
            $mensaje_tipo = "success";
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
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? null;
    $fecha_salida = $_POST['fecha_salida'] ?? null;
    $confirmado = isset($_POST['confirmado']);
    $ruta_id = $_POST['ruta_id'] ?? null;

    if (!$id || !$tipo || !$fecha_ingreso || !$fecha_salida) {
        $mensaje = "Todos los campos obligatorios deben ser completados para editar.";
        $mensaje_tipo = "danger";
    } elseif (strtotime($fecha_salida) < strtotime($fecha_ingreso)) {
        $mensaje = "La fecha de salida no puede ser menor que la de ingreso.";
        $mensaje_tipo = "danger";
    } elseif ($tipo === 'cambio_aceite' && (!is_numeric($kilometraje) || $kilometraje < 0 || $kilometraje > 999999)) {
        $mensaje = "Kilometraje inválido. Debe estar entre 0 y 999.999.";
        $mensaje_tipo = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE mantenimiento SET tipo = ?, descripcion = ?, fecha_ingreso = ?, fecha_salida = ?, kilometraje = ?, confirmado = ? WHERE id = ?");
            $stmt->execute([
                $tipo,
                $descripcion,
                $fecha_ingreso,
                $fecha_salida,
                $tipo === 'cambio_aceite' ? $kilometraje : null,
                $confirmado,
                $id
            ]);

            $mensaje = "Mantenimiento actualizado correctamente.";
            $mensaje_tipo = "success";
        } catch (Exception $e) {
            $mensaje = "Error al editar mantenimiento: " . $e->getMessage();
            $mensaje_tipo = "danger";
        }
    }
}

if (isset($_POST['delete'])) {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM mantenimiento WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = "Mantenimiento eliminado correctamente.";
        $mensaje_tipo = "success";
    }
}

$mantenimientos = $pdo->query("SELECT m.*, r.nombre as ruta_nombre, mi.ruta_id FROM mantenimiento m LEFT JOIN micro mi ON m.micro_id = mi.id LEFT JOIN ruta r ON mi.ruta_id = r.id ORDER BY m.fecha DESC")->fetchAll();
$rutas = $pdo->query("SELECT id, nombre FROM ruta ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        input[name="patente"] { text-transform: uppercase; }
        input[type="checkbox"] { width: 18px; height: 18px; border: 2px solid #444; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Registro de Mantenimientos</h2>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= h($mensaje_tipo) ?>"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" class="border p-4 bg-white shadow-sm mb-4" id="form-mantenimiento">
        <input type="hidden" name="id" id="id" value="">
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="patente" class="form-label">Patente *</label>
                <input type="text" name="patente" id="patente" class="form-control" maxlength="6" required>
            </div>
            <div class="col-md-4">
                <label for="ruta_id" class="form-label">Ruta *</label>
                <select name="ruta_id" id="ruta_id" class="form-select" required>
                    <option value="">-- Selecciona ruta --</option>
                    <?php foreach ($rutas as $ruta): ?>
                        <option value="<?= $ruta['id'] ?>"><?= h($ruta['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="tipo" class="form-label">Tipo de mantenimiento *</label>
                <select name="tipo" id="tipo" class="form-select" required>
                    <option value="">-- Selecciona --</option>
                    <option value="ajuste_frenos">Ajuste de frenos</option>
                    <option value="cambio_aceite">Cambio de aceite</option>
                    <option value="cambio_neumaticos">Cambio de neumáticos</option>
                    <option value="verificacion_luces">Verificación de luces</option>
                </select>
            </div>
        </div>

        <div class="mb-3" id="kilometraje_group" style="display:none;">
            <label for="kilometraje" class="form-label">Kilometraje</label>
            <input type="number" name="kilometraje" id="kilometraje" class="form-control" min="0" max="999999">
        </div>

        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control" rows="3"></textarea>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="fecha_ingreso" class="form-label">Fecha de ingreso *</label>
                <input type="date" name="fecha_ingreso" id="fecha_ingreso" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label for="fecha_salida" class="form-label">Fecha de salida *</label>
                <input type="date" name="fecha_salida" id="fecha_salida" class="form-control" required>
            </div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="confirmado" id="confirmado" class="form-check-input">
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
                <tr style="color: <?= $m['confirmado'] ? 'green' : 'red' ?>">
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
                            <input type="hidden" name="id" value="<?= h($m['id']) ?>">
                            <button type="submit" name="delete" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById('tipo').addEventListener('change', function () {
    document.getElementById('kilometraje_group').style.display = this.value === 'cambio_aceite' ? 'block' : 'none';
});

document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', () => {
        const data = JSON.parse(button.dataset.info);

        document.getElementById('btn-add').style.display = 'none';
        document.getElementById('btn-edit').style.display = 'inline-block';
        document.getElementById('btn-cancel').style.display = 'inline-block';

        document.getElementById('id').value = data.id;
        document.getElementById('patente').value = data.patente;
        document.getElementById('patente').setAttribute('readonly', true);
        document.getElementById('ruta_id').value = data.ruta_id;
        document.getElementById('tipo').value = data.tipo;
        document.getElementById('descripcion').value = data.descripcion;
        document.getElementById('fecha_ingreso').value = data.fecha_ingreso;
        document.getElementById('fecha_salida').value = data.fecha_salida;
        document.getElementById('kilometraje').value = data.kilometraje || '';
        document.getElementById('confirmado').checked = data.confirmado === '1';

        document.getElementById('kilometraje_group').style.display = data.tipo === 'cambio_aceite' ? 'block' : 'none';
    });
});

document.getElementById('btn-cancel').addEventListener('click', () => {
    document.getElementById('form-mantenimiento').reset();
    document.getElementById('id').value = '';
    document.getElementById('patente').removeAttribute('readonly');
    document.getElementById('btn-add').style.display = 'inline-block';
    document.getElementById('btn-edit').style.display = 'none';
    document.getElementById('btn-cancel').style.display = 'none';
    document.getElementById('kilometraje_group').style.display = 'none';
});
</script>
</body>
</html>
