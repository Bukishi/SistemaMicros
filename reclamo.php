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

$mensaje = '';
$mensaje_tipo = '';
$modo_editar = false;
$editar_id = null;
$datos_editar = null;

$usuario_id = $_SESSION['usuario_id'];

// Acciones

if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM reclamo WHERE id = ?");
        $stmt->execute([$delete_id]);
        $mensaje = "Reclamo eliminado correctamente.";
        $mensaje_tipo = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al eliminar reclamo: " . $e->getMessage();
        $mensaje_tipo = "danger";
    }
}

if (isset($_POST['marcar_completado_id'])) {
    $id = intval($_POST['marcar_completado_id']);
    try {
        $stmt = $pdo->prepare("UPDATE reclamo SET estado = 'completado' WHERE id = ?");
        $stmt->execute([$id]);
        $mensaje = "Reclamo marcado como completado.";
        $mensaje_tipo = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al actualizar estado: " . $e->getMessage();
        $mensaje_tipo = "danger";
    }
}

if (isset($_GET['editar'])) {
    $editar_id = intval($_GET['editar']);
    $stmt = $pdo->prepare("SELECT * FROM reclamo WHERE id = ?");
    $stmt->execute([$editar_id]);
    $datos_editar = $stmt->fetch();
    if (!$datos_editar) {
        $mensaje = "Reclamo no encontrado para editar.";
        $mensaje_tipo = "danger";
    } else {
        $modo_editar = true;
    }
}

if (isset($_POST['add'])) {
    $ruta_id = !empty($_POST['ruta_id']) ? $_POST['ruta_id'] : null;
    $fecha      = $_POST['fecha'] ?? date('Y-m-d');
    $hora       = $_POST['hora'] ?? date('H:i:s');
    $categorias = $_POST['categorias'] ?? [];
    $tipo_problema = $_POST['tipo_problema'] ?? [];
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $patente       = trim($_POST['patente'] ?? null);

    if (empty($descripcion)) {
        $mensaje = "La descripción no puede estar vacía.";
        $mensaje_tipo = "danger";
    } elseif (empty($categorias)) {
        $mensaje = "Debes seleccionar al menos una categoría.";
        $mensaje_tipo = "danger";
    } elseif (empty($tipo_problema)) {
        $mensaje = "Debes seleccionar al menos un tipo de problema.";
        $mensaje_tipo = "danger";
    } else {
        $categorias_pg = '{' . implode(',', array_map(function($c){ return pg_escape_string($c); }, $categorias)) . '}';
        $tipo_problema_pg = '{' . implode(',', array_map(function($t){ return pg_escape_string($t); }, $tipo_problema)) . '}';

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
            $mensaje = "Reclamo agregado correctamente.";
            $mensaje_tipo = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al agregar reclamo: " . $e->getMessage();
            $mensaje_tipo = "danger";
        }
    }
}

if (isset($_POST['editar'])) {
    $editar_id = intval($_POST['editar_id']);
    $estado = $_POST['estado'] ?? 'selecciona_una_opcion';
    $respuesta_admin = trim($_POST['respuesta_admin'] ?? '');

    if ($estado === 'selecciona_una_opcion') {
        $mensaje = "Debes seleccionar un estado válido.";
        $mensaje_tipo = "danger";
        $modo_editar = true;
        $stmt = $pdo->prepare("SELECT * FROM reclamo WHERE id = ?");
        $stmt->execute([$editar_id]);
        $datos_editar = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT estado FROM reclamo WHERE id = ?");
        $stmt->execute([$editar_id]);
        $reclamo_actual = $stmt->fetch();

        if ($reclamo_actual && $reclamo_actual['estado'] !== 'completado') {
            try {
                $stmt = $pdo->prepare("UPDATE reclamo SET estado = ?, respuesta_admin = ? WHERE id = ?");
                $stmt->execute([$estado, $respuesta_admin, $editar_id]);
                $mensaje = "Reclamo actualizado correctamente.";
                $mensaje_tipo = "success";
                $modo_editar = false;
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar reclamo: " . $e->getMessage();
                $mensaje_tipo = "danger";
                $modo_editar = true;
            }
        } else {
            $mensaje = "Este reclamo ya fue completado y no puede ser editado.";
            $mensaje_tipo = "warning";
            $modo_editar = false;
        }
    }
}

$rutas = $pdo->query("SELECT id, nombre FROM ruta ORDER BY nombre")->fetchAll();
$reclamos = $pdo->query("
    SELECT r.*, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido,
           ruta.nombre AS ruta_nombre
    FROM reclamo r
    JOIN usuario u ON r.usuario_id = u.id
    LEFT JOIN ruta ON r.ruta_id = ruta.id
    ORDER BY r.fecha DESC, r.hora DESC
")->fetchAll();

function checkedInArray($value, $array) {
    return in_array($value, (array)$array) ? 'checked' : '';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Gestión de Reclamos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .bloqueado {
            background-color: #eee !important;
            pointer-events: none;
            color: #555;
        }
    </style>
</head>
<body class="bg-light">
<button type="button" onclick="location.href='home.php'" class="btn btn-primary m-3">Volver</button>

<div class="container py-4">
    <h2 class="mb-4 text-center">Gestión de Reclamos</h2>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje_tipo ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($modo_editar && $datos_editar): 
        $categorias_sel = convertirArrayPostgres($datos_editar['categorias'] ?? []);
        $tipo_problema_sel = convertirArrayPostgres($datos_editar['tipo_problema'] ?? []);
        $es_completado = ($datos_editar['estado'] === 'completado');
        $bloqueado_class = $es_completado ? 'bloqueado' : '';
    ?>
        <h3 class="mb-3">Editar Reclamo #<?= htmlspecialchars($datos_editar['id']) ?></h3>

        <?php if($es_completado): ?>
            <div class="alert alert-info">Este reclamo ya fue completado y no puede modificarse.</div>
        <?php endif; ?>

        <form method="POST" class="mb-5">
            <input type="hidden" name="editar_id" value="<?= htmlspecialchars($datos_editar['id']) ?>" />

            <div class="mb-3">
                <label for="fecha" class="form-label">Fecha</label>
                <input type="date" id="fecha" name="fecha" class="form-control <?= $bloqueado_class ?>" value="<?= htmlspecialchars($datos_editar['fecha']) ?>" readonly />
            </div>

            <div class="mb-3">
                <label for="hora" class="form-label">Hora</label>
                <input type="time" id="hora" name="hora" class="form-control <?= $bloqueado_class ?>" value="<?= htmlspecialchars(substr($datos_editar['hora'], 0, 5)) ?>" readonly />
            </div>

            <div class="mb-3">
                <label for="ruta_id" class="form-label">Ruta</label>
                <select id="ruta_id" name="ruta_id" class="form-select <?= $bloqueado_class ?>" disabled>
                    <option value="">-- Seleccione una ruta --</option>
                    <option value="" <?= empty($datos_editar['ruta_id']) ? 'selected' : '' ?>>Ruta desconocida</option>
                    <?php foreach($rutas as $ruta): ?>
                        <option value="<?= $ruta['id'] ?>" <?= ($datos_editar['ruta_id'] == $ruta['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ruta['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="patente" class="form-label">Patente</label>
                <input type="text" id="patente" name="patente" class="form-control <?= $bloqueado_class ?>" maxlength="10" value="<?= htmlspecialchars($datos_editar['patente'] ?? '') ?>" readonly />
            </div>

            <div class="mb-3">
                <label class="form-label">Categorías (Puedes seleccionar una o más):</label><br />
                <?php 
                    $opc_categorias = ['maltrato' => 'Maltrato', 'retraso' => 'Retraso', 'mala_conduccion' => 'Mala conducción', 'otros' => 'Otros'];
                    foreach ($opc_categorias as $valor => $texto): 
                ?>
                    <label>
                        <input type="checkbox" name="categorias[]" value="<?= $valor ?>" <?= checkedInArray($valor, $categorias_sel) ?> disabled />
                        <?= $texto ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Tipo de problema (Seleccione uno o más):</label><br />
                <?php 
                    $opc_tipo_problema = ['micro' => 'Micro', 'plataforma' => 'Plataforma'];
                    foreach ($opc_tipo_problema as $valor => $texto): 
                ?>
                    <label>
                        <input type="checkbox" name="tipo_problema[]" value="<?= $valor ?>" <?= checkedInArray($valor, $tipo_problema_sel) ?> disabled />
                        <?= $texto ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción (obligatoria)</label>
                <textarea id="descripcion" name="descripcion" class="form-control <?= $bloqueado_class ?>" rows="4" readonly><?= htmlspecialchars($datos_editar['descripcion']) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-select" <?= $es_completado ? 'disabled' : 'required' ?>>
                    <option value="selecciona_una_opcion" <?= ($datos_editar['estado'] === 'selecciona_una_opcion') ? 'selected' : '' ?>>Selecciona una opción</option>
                    <?php
                    $estados = ['pendiente', 'en_revision', 'completado'];
                    foreach ($estados as $estado_option):
                    ?>
                    <option value="<?= $estado_option ?>" <?= ($datos_editar['estado'] === $estado_option) ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_', ' ', $estado_option)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="respuesta_admin" class="form-label">Mensaje del administrador</label>
                <textarea id="respuesta_admin" name="respuesta_admin" class="form-control" rows="3" <?= $es_completado ? 'readonly' : '' ?>><?= htmlspecialchars($datos_editar['respuesta_admin'] ?? '') ?></textarea>
            </div>

            <?php if (!$es_completado): ?>
                <button type="submit" name="editar" class="btn btn-primary">Guardar Cambios</button>
                <a href="reclamo.php" class="btn btn-secondary">Cancelar</a>
            <?php else: ?>
                <a href="reclamo.php" class="btn btn-secondary">Volver</a>
            <?php endif; ?>
        </form>

    <?php else: ?>

        <!-- SOLO la tabla, sin formulario de agregar -->

        <h3 class="mb-3">Lista de Reclamos</h3>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover shadow-sm">
                <thead class="table-primary text-center">
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Ruta</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Categorías</th>
                        <th>Tipo de problema</th>
                        <th>Descripción</th>
                        <th>Patente</th>
                        <th>Estado</th>
                        <th>Mensaje Admin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reclamos as $reclamo): 
                        $clase_fila = '';
                        if ($reclamo['estado'] === 'completado') {
                            $clase_fila = 'table-success'; // verde
                        } elseif ($reclamo['estado'] === 'en_revision') {
                            $clase_fila = 'table-warning'; // amarillo
                        }
                        $categorias_reclamo = convertirArrayPostgres($reclamo['categorias']);
                        $tipos_reclamo = convertirArrayPostgres($reclamo['tipo_problema']);
                    ?>
                    <tr class="<?= $clase_fila ?>">
                        <td class="text-center"><?= htmlspecialchars($reclamo['id']) ?></td>
                        <td><?= htmlspecialchars($reclamo['usuario_nombre'] . ' ' . $reclamo['usuario_apellido']) ?></td>
                        <td><?= htmlspecialchars($reclamo['ruta_nombre'] ?? '-') ?></td>
                        <td class="text-center"><?= htmlspecialchars(date('d-m-Y', strtotime($reclamo['fecha']))) ?></td>
                        <td class="text-center"><?= htmlspecialchars($reclamo['hora']) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $categorias_reclamo)) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $tipos_reclamo)) ?></td>
                        <td><?= nl2br(htmlspecialchars($reclamo['descripcion'])) ?></td>
                        <td><?= htmlspecialchars($reclamo['patente'] ?? '-') ?></td>
                        <td class="text-center"><?= htmlspecialchars($reclamo['estado']) ?></td>
                        <td><?= nl2br(htmlspecialchars($reclamo['respuesta_admin'] ?? '-')) ?></td>
                        <td class="text-center" style="white-space: nowrap;">
                            <?php if ($reclamo['estado'] !== 'completado'): ?>
                                <a href="?editar=<?= $reclamo['id'] ?>" class="btn btn-warning btn-sm mb-1">Editar</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Marcar como completado?');">
                                    <input type="hidden" name="marcar_completado_id" value="<?= $reclamo['id'] ?>" />
                                    <button type="submit" class="btn btn-success btn-sm mb-1">Completar</button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-success">Completado</span>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro que quieres eliminar este reclamo?');">
                                <input type="hidden" name="delete_id" value="<?= $reclamo['id'] ?>" />
                                <button type="submit" class="btn btn-danger btn-sm mb-1">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
