<?php 
include 'config.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// EDITAR CHOFER - mostrar formulario con datos
if (isset($_GET['editar_id'])) {
    $editar_id = $_GET['editar_id'];
    $stmt = $pdo->prepare("SELECT c.id, c.nombre, c.apellido, c.licencia, c.examen_corma, u.correo, c.usuario_id 
                           FROM chofer c
                           JOIN usuario u ON c.usuario_id = u.id
                           WHERE c.id = ?");
    $stmt->execute([$editar_id]);
    $choferEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$choferEditar) {
        echo "<div class='alert alert-danger'>Chofer no encontrado.</div>";
        unset($choferEditar);
    }
}

// ACTUALIZAR CHOFER
if (isset($_POST['update'])) {
    $id           = $_POST['id'];
    $nombre       = $_POST['nombre'];
    $apellido     = $_POST['apellido'];
    $correo       = $_POST['correo'];
    $licencia     = isset($_POST['licencia']) ? 'A4' : '';
    $examen_corma = isset($_POST['examen_corma']) ? 1 : 0;

    $stmt = $pdo->prepare("SELECT usuario_id FROM chofer WHERE id = ?");
    $stmt->execute([$id]);
    $usuarioIdRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuarioIdRow) {
        $usuario_id = $usuarioIdRow['usuario_id'];

        $stmtUpdUser = $pdo->prepare("UPDATE usuario SET nombre = ?, apellido = ?, correo = ? WHERE id = ?");
        try {
            $stmtUpdUser->execute([$nombre, $apellido, $correo, $usuario_id]);

            $stmtUpdChofer = $pdo->prepare("UPDATE chofer SET nombre = ?, apellido = ?, licencia = ?, examen_corma = ? WHERE id = ?");
            $stmtUpdChofer->execute([$nombre, $apellido, $licencia, $examen_corma, $id]);

            header('Location: ' . basename($_SERVER['PHP_SELF']));
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                echo "<div class='alert alert-danger'>El correo '$correo' ya está registrado. Por favor usa otro.</div>";
            } else {
                echo "<div class='alert alert-danger'>Error actualizando usuario: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// AÑADIR CHOFER
if (isset($_POST['add'])) {
    $nombre      = $_POST['nombre'];
    $apellido    = $_POST['apellido'];
    $correo      = $_POST['correo'];
    $contraseña  = password_hash($_POST['contraseña'], PASSWORD_DEFAULT);
    $licencia    = isset($_POST['licencia']) ? 'A4' : '';
    $examen_corma= isset($_POST['examen_corma']) ? true : false;
    $rol         = 'chofer';

    if ($licencia !== 'A4') {
        echo "<div class='alert alert-danger'>❌ No se puede registrar el chofer porque no cuenta con licencia A4.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO usuario (nombre, apellido, correo, contraseña, rol) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellido, $correo, $contraseña, $rol]);
            $usuario_id = $pdo->lastInsertId();

            $stmt2 = $pdo->prepare("INSERT INTO chofer (nombre, apellido, usuario_id, licencia, examen_corma) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$nombre, $apellido, $usuario_id, $licencia, $examen_corma]);
            echo "<div class='alert alert-success'>Chofer registrado correctamente.</div>";
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                echo "<div class='alert alert-danger'>El correo '$correo' ya está registrado. Por favor usa otro.</div>";
            } else {
                echo "<div class='alert alert-danger'>Ocurrió un error: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// ELIMINAR CHOFER
if (isset($_POST['delete'])) {
    $chofer_id = $_POST['chofer_id'];
    $stmt = $pdo->prepare("SELECT usuario_id FROM chofer WHERE id = ?");
    $stmt->execute([$chofer_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $usuario_id = $usuario['usuario_id'];
        $pdo->prepare("DELETE FROM asignacion_micro WHERE chofer_id = ?")->execute([$chofer_id]);
        $pdo->prepare("DELETE FROM horario WHERE chofer_id = ?")->execute([$chofer_id]);
        $pdo->prepare("DELETE FROM chofer WHERE id = ?")->execute([$chofer_id]);
        $pdo->prepare("DELETE FROM usuario WHERE id = ?")->execute([$usuario_id]);
    }
}

// CONSULTAS GENERALES
$micros   = $pdo->query("SELECT id, patente, ruta_id FROM micro")->fetchAll();
$rutas    = $pdo->query("SELECT id, nombre FROM ruta")->fetchAll();
$choferes = $pdo->query("SELECT c.id, c.nombre, c.apellido, c.licencia, c.examen_corma, u.correo, c.usuario_id 
                          FROM chofer c
                          JOIN usuario u ON c.usuario_id = u.id")->fetchAll();
$horarios = $pdo->query("SELECT h.id, c.nombre AS chofer_nombre, c.apellido AS chofer_apellido, h.hora_inicio, h.hora_fin, h.fecha, m.patente AS micro_patente, r.nombre AS ruta_nombre
                         FROM horario h
                         JOIN chofer c ON h.chofer_id = c.id
                         JOIN ruta r ON h.ruta_id = r.id
                         LEFT JOIN asignacion_micro am ON am.chofer_id = h.chofer_id AND DATE(am.fecha) = DATE(h.fecha)
                         LEFT JOIN micro m ON am.micro_id = m.id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Gestión de Choferes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        .no-circular {
            background-color: #f8d7da !important; /* rojo claro */
        }
        /* Checkbox un poquito más grandes y borde un poco más grueso */
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-width: 1.5px;
        }
        button {
            background-color: rgb(37, 95, 241);
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: rgb(42, 158, 235);
        }
    </style>

    <script>
        // Confirmación al editar si licencia o examen CORMA están desmarcados
        function confirmarEdicion(event) {
            const licencia = document.getElementById('licenciaCheckEdit').checked;
            const corma = document.getElementById('cormaCheckEdit').checked;

            if (!licencia || !corma) {
                let mensaje = 'Este chofer ';
                if (!licencia && !corma) {
                    mensaje += 'no tiene licencia A4 y no aprobó examen CORMA.';
                } else if (!licencia) {
                    mensaje += 'no tiene licencia A4.';
                } else if (!corma) {
                    mensaje += 'no aprobó examen CORMA.';
                }
                mensaje += ' ¿Seguro que deseas guardar?';

                if (!confirm(mensaje)) {
                    event.preventDefault();
                    return false;
                }
            }
            return true;
        }
    </script>
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="text-center mb-4">Gestión de Choferes</h2>

    <!-- Tabla de Choferes -->
    <table class="table table-bordered table-hover">
        <thead class="table-primary text-center">
            <tr>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Licencia</th>
                <th>Examen CORMA</th>
                <th>Correo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($choferes as $chofer): ?>
            <tr class="<?= ($chofer['licencia'] !== 'A4' || !$chofer['examen_corma']) ? 'no-circular' : '' ?>">
                <td><?= htmlspecialchars($chofer['nombre']) ?></td>
                <td><?= htmlspecialchars($chofer['apellido']) ?></td>
                <td>
                    <?= $chofer['licencia'] === 'A4' ? 'A4' : '<span class="text-danger fw-bold">Sin licencia A4</span>' ?>
                </td>
                <td><?= $chofer['examen_corma'] ? 'Sí' : '<span class="text-danger fw-bold">No aprobado</span>' ?></td>
                <td><?= htmlspecialchars($chofer['correo']) ?></td>
                <td class="text-center">
                    <a href="?editar_id=<?= $chofer['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="chofer_id" value="<?= $chofer['id'] ?>" />
                        <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar chofer?')">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Formulario Agregar o Editar -->
    <?php if (isset($choferEditar)): ?>
        <h3 class="text-center mt-5">Editar Chofer</h3>
        <form method="POST" class="mx-auto" style="max-width: 400px;" onsubmit="return confirmarEdicion(event)">
            <input type="hidden" name="id" value="<?= $choferEditar['id'] ?>" />
            <input type="text" name="nombre" class="form-control mb-2" required value="<?= htmlspecialchars($choferEditar['nombre']) ?>" />
            <input type="text" name="apellido" class="form-control mb-2" required value="<?= htmlspecialchars($choferEditar['apellido']) ?>" />
            <input type="email" name="correo" class="form-control mb-2" required value="<?= htmlspecialchars($choferEditar['correo']) ?>" />

            <div class="form-check mb-2">
                <input type="checkbox" name="licencia" id="licenciaCheckEdit" class="form-check-input" <?= $choferEditar['licencia'] === 'A4' ? 'checked' : '' ?> />
                <label for="licenciaCheckEdit" class="form-check-label">Licencia A4</label>
            </div>

            <?php if ($choferEditar['licencia'] !== 'A4'): ?>
                <div class="alert alert-warning">⚠ Este chofer no puede circular porque no tiene licencia A4.</div>
            <?php endif; ?>

            <div class="form-check mb-2">
                <input type="checkbox" name="examen_corma" id="cormaCheckEdit" class="form-check-input" <?= $choferEditar['examen_corma'] ? 'checked' : '' ?> />
                <label for="cormaCheckEdit" class="form-check-label">Aprobó examen CORMA</label>
            </div>

            <?php if (!$choferEditar['examen_corma']): ?>
                <div class="alert alert-warning">⚠ Este chofer no puede circular porque no aprobó el examen CORMA.</div>
            <?php endif; ?>

            <button type="submit" name="update" class="btn btn-primary">Actualizar Chofer</button>
            <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    <?php else: ?>
        <h3 class="text-center mt-5">Agregar Nuevo Chofer</h3>
        <form method="POST" class="mx-auto" style="max-width: 400px;">
            <input type="text" name="nombre" class="form-control mb-2" required placeholder="Nombre" />
            <input type="text" name="apellido" class="form-control mb-2" required placeholder="Apellido" />
            <input type="email" name="correo" class="form-control mb-2" required placeholder="Correo" />
            <input type="password" name="contraseña" class="form-control mb-2" required placeholder="Contraseña" />

            <div class="form-check mb-2">
                <input type="checkbox" name="licencia" id="licenciaCheckAdd" class="form-check-input" />
                <label for="licenciaCheckAdd" class="form-check-label">Licencia A4</label>
            </div>

            <div class="form-check mb-2">
                <input type="checkbox" name="examen_corma" id="cormaCheckAdd" class="form-check-input" />
                <label for="cormaCheckAdd" class="form-check-label">Aprobó examen CORMA</label>
            </div>

            <button type="submit" name="add" class="btn btn-success">Agregar Chofer</button>
        </form>
    <?php endif; ?>

    <hr class="my-5" />

    <h3 class="text-center mb-4">Horarios de Choferes</h3>
    <table class="table table-bordered table-hover">
        <thead class="table-primary text-center">
            <tr>
                <th>Chofer</th>
                <th>Patente Micro</th>
                <th>Ruta</th>
                <th>Fecha</th>
                <th>Hora Inicio</th>
                <th>Hora Fin</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($horarios as $horario): ?>
            <tr>
                <td><?= htmlspecialchars($horario['chofer_nombre'] . ' ' . $horario['chofer_apellido']) ?></td>
                <td><?= htmlspecialchars($horario['micro_patente'] ?? '-') ?></td>
                <td><?= htmlspecialchars($horario['ruta_nombre']) ?></td>
                <td><?= date('d-m-Y', strtotime($horario['fecha'])) ?></td>
                <td><?= htmlspecialchars($horario['hora_inicio']) ?></td>
                <td><?= htmlspecialchars($horario['hora_fin']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
