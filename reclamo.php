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

// Mostrar mensajes después de redirección
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $mensaje_tipo = $_SESSION['mensaje_tipo'];
    unset($_SESSION['mensaje'], $_SESSION['mensaje_tipo']);
}

// -------- ACCIONES --------

if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM reclamo WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['mensaje'] = "✅ Reclamo eliminado correctamente.";
        $_SESSION['mensaje_tipo'] = "success";
        header("Location: reclamo.php#top");
        exit;
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
        $_SESSION['mensaje'] = "✅ Reclamo marcado como completado.";
        $_SESSION['mensaje_tipo'] = "success";
        header("Location: reclamo.php#top");
        exit;
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

if (isset($_POST['editar'])) {
    $editar_id = intval($_POST['editar_id']);
    $estado = $_POST['estado'] ?? 'selecciona_una_opcion';
    $respuesta_admin = trim($_POST['respuesta_admin'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM reclamo WHERE id = ?");
    $stmt->execute([$editar_id]);
    $datos_editar = $stmt->fetch();
    $modo_editar = true;

    if ($estado === 'selecciona_una_opcion') {
        $mensaje = "Debes seleccionar un estado válido.";
        $mensaje_tipo = "danger";
    } elseif ($datos_editar['estado'] === 'completado') {
        $mensaje = "Este reclamo ya fue completado y no puede ser editado.";
        $mensaje_tipo = "warning";
        $modo_editar = false;
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE reclamo SET estado = ?, respuesta_admin = ? WHERE id = ?");
            $stmt->execute([$estado, $respuesta_admin, $editar_id]);
            $_SESSION['mensaje'] = "✅ Reclamo actualizado correctamente.";
            $_SESSION['mensaje_tipo'] = "success";
            header("Location: reclamo.php#top");
            exit;
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar reclamo: " . $e->getMessage();
            $mensaje_tipo = "danger";
        }
    }
}

// -------- DATOS --------

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
        .bloqueado { background-color: #eee !important; pointer-events: none; color: #555; }
    </style>
    <script>
        function confirmarCambios() {
            return confirm('¿Estás seguro de confirmar los cambios?');
        }
    </script>
</head>
<body class="bg-light">
<a name="top"></a>
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

        <form method="POST" onsubmit="return confirmarCambios();">
            <input type="hidden" name="editar_id" value="<?= htmlspecialchars($datos_editar['id']) ?>" />

            <!-- Tipo de problema primero -->
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
                    <?php foreach($rutas as $ruta): ?>
                        <option value="<?= $ruta['id'] ?>" <?= ($datos_editar['ruta_id'] == $ruta['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ruta['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Categorías:</label><br />
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
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea id="descripcion" name="descripcion" class="form-control <?= $bloqueado_class ?>" rows="3" readonly><?= htmlspecialchars($datos_editar['descripcion']) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-select" <?= $es_completado ? 'disabled' : 'required' ?>>
                    <option value="selecciona_una_opcion">Selecciona una opción</option>
                    <?php foreach (['pendiente', 'en_revision', 'completado'] as $estado_op): ?>
                        <option value="<?= $estado_op ?>" <?= ($datos_editar['estado'] === $estado_op) ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $estado_op)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="respuesta_admin" class="form-label">Mensaje del administrador</label>
                <textarea id="respuesta_admin" name="respuesta_admin" class="form-control" rows="3" <?= $es_completado ? 'readonly' : '' ?>><?= htmlspecialchars($datos_editar['respuesta_admin']) ?></textarea>
            </div>

            <?php if (!$es_completado): ?>
                <button type="submit" name="editar" class="btn btn-primary">Guardar Cambios</button>
                <a href="reclamo.php#top" class="btn btn-secondary">Cancelar</a>
            <?php else: ?>
                <a href="reclamo.php#top" class="btn btn-secondary">Volver</a>
            <?php endif; ?>
        </form>

    <?php else: ?>
        <!-- Lista de reclamos -->
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
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Patente</th>
                        <th>Estado</th>
                        <th>Respuesta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reclamos as $reclamo): 
                        $categorias = convertirArrayPostgres($reclamo['categorias']);
                        $tipos = convertirArrayPostgres($reclamo['tipo_problema']);
                        $clase = $reclamo['estado'] === 'completado' ? 'table-success' : ($reclamo['estado'] === 'en_revision' ? 'table-warning' : '');
                    ?>
                    <tr class="<?= $clase ?>">
                        <td><?= $reclamo['id'] ?></td>
                        <td><?= htmlspecialchars($reclamo['usuario_nombre'] . ' ' . $reclamo['usuario_apellido']) ?></td>
                        <td><?= htmlspecialchars($reclamo['ruta_nombre'] ?? '-') ?></td>
                        <td><?= date('d-m-Y', strtotime($reclamo['fecha'])) ?></td>
                        <td><?= htmlspecialchars($reclamo['hora']) ?></td>
                        <td><?= implode(', ', $categorias) ?></td>
                        <td><?= implode(', ', $tipos) ?></td>
                        <td><?= nl2br(htmlspecialchars($reclamo['descripcion'])) ?></td>
                        <td><?= htmlspecialchars($reclamo['patente'] ?? '-') ?></td>
                        <td><?= $reclamo['estado'] ?></td>
                        <td><?= nl2br(htmlspecialchars($reclamo['respuesta_admin'] ?? '-')) ?></td>
                        <td class="text-center">
                            <?php if ($reclamo['estado'] !== 'completado'): ?>
                                <a href="?editar=<?= $reclamo['id'] ?>#top" class="btn btn-warning btn-sm">Editar</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Marcar como completado?');">
                                    <input type="hidden" name="marcar_completado_id" value="<?= $reclamo['id'] ?>" />
                                    <button type="submit" class="btn btn-success btn-sm">Completar</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar reclamo?');">
                                <input type="hidden" name="delete_id" value="<?= $reclamo['id'] ?>" />
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
