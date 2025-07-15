<?php 
include 'config.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Función para validar nombre/apellido: no vacíos, sin números
function validarNombreApellido($texto) {
    return !empty($texto) && !preg_match('/[0-9]/', $texto);
}

// Variables para rellenar formulario en caso de error o edición
$valores = [
    'nombre' => '',
    'apellido' => '',
    'correo' => '',
    'licencia' => false,
    'examen_corma' => false,
];

$errores = [];

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
    } else {
        // Rellenar valores para edición
        $valores['nombre'] = $choferEditar['nombre'];
        $valores['apellido'] = $choferEditar['apellido'];
        $valores['correo'] = $choferEditar['correo'];
        $valores['licencia'] = ($choferEditar['licencia'] === 'A4');
        $valores['examen_corma'] = (bool)$choferEditar['examen_corma'];
    }
}

// ACTUALIZAR CHOFER
if (isset($_POST['update'])) {
    $id           = $_POST['id'];
    $nombre       = trim($_POST['nombre']);
    $apellido     = trim($_POST['apellido']);
    $correo       = trim($_POST['correo']);
    $licencia     = isset($_POST['licencia']) ? true : false;
    $examen_corma = isset($_POST['examen_corma']) ? true : false;

    // Rellenar para mantener datos si hay error
    $valores = [
        'nombre' => $nombre,
        'apellido' => $apellido,
        'correo' => $correo,
        'licencia' => $licencia,
        'examen_corma' => $examen_corma,
    ];

    // Validaciones
    if (!validarNombreApellido($nombre)) {
        $errores[] = "El nombre no puede estar vacío ni contener números.";
    }
    if (!validarNombreApellido($apellido)) {
        $errores[] = "El apellido no puede estar vacío ni contener números.";
    }
    if (!$licencia) {
        $errores[] = "❌ El chofer debe tener licencia A4 para poder guardar.";
    }

    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT usuario_id FROM chofer WHERE id = ?");
        $stmt->execute([$id]);
        $usuarioIdRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuarioIdRow) {
            $usuario_id = $usuarioIdRow['usuario_id'];

            $stmtUpdUser = $pdo->prepare("UPDATE usuario SET nombre = ?, apellido = ?, correo = ? WHERE id = ?");
            $stmtUpdChofer = $pdo->prepare("UPDATE chofer SET nombre = ?, apellido = ?, licencia = ?, examen_corma = ? WHERE id = ?");
            try {
                $stmtUpdUser->execute([$nombre, $apellido, $correo, $usuario_id]);
                $licencia_val = $licencia ? 'A4' : '';
                $examen_corma_val = $examen_corma ? 1 : 0;
                $stmtUpdChofer->execute([$nombre, $apellido, $licencia_val, $examen_corma_val, $id]);

                // Vaciar formulario si exitoso
                $valores = [
                    'nombre' => '',
                    'apellido' => '',
                    'correo' => '',
                    'licencia' => false,
                    'examen_corma' => false,
                ];

                echo "<div class='alert alert-success'>Chofer actualizado correctamente.</div>";
                // Para evitar que el formulario con datos editados siga mostrando, redirigir
                header('Location: ' . basename($_SERVER['PHP_SELF']));
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == '23505') {
                    $errores[] = "El correo '$correo' ya está registrado. Por favor usa otro.";
                } else {
                    $errores[] = "Ocurrió un error: " . $e->getMessage();
                }
            }
        }
    }
}

// AÑADIR CHOFER
if (isset($_POST['add'])) {
    $nombre      = trim($_POST['nombre']);
    $apellido    = trim($_POST['apellido']);
    $correo      = trim($_POST['correo']);
    $contraseña  = $_POST['contraseña'];
    $licencia    = isset($_POST['licencia']) ? true : false;
    $examen_corma= isset($_POST['examen_corma']) ? true : false;
    $rol         = 'chofer';

    // Guardar valores para no perderlos si hay error
    $valores = [
        'nombre' => $nombre,
        'apellido' => $apellido,
        'correo' => $correo,
        'licencia' => $licencia,
        'examen_corma' => $examen_corma,
    ];

    // Validaciones
    if (!validarNombreApellido($nombre)) {
        $errores[] = "El nombre no puede estar vacío ni contener números.";
    }
    if (!validarNombreApellido($apellido)) {
        $errores[] = "El apellido no puede estar vacío ni contener números.";
    }
    if (!$licencia) {
        $errores[] = "❌ No se puede registrar el chofer porque no cuenta con licencia A4.";
    }

    if (empty($errores)) {
        try {
            $hash_contraseña = password_hash($contraseña, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuario (nombre, apellido, correo, contraseña, rol) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellido, $correo, $hash_contraseña, $rol]);
            $usuario_id = $pdo->lastInsertId();

            $licencia_val = $licencia ? 'A4' : '';
            $examen_corma_val = $examen_corma ? 1 : 0;

            $stmt2 = $pdo->prepare("INSERT INTO chofer (nombre, apellido, usuario_id, licencia, examen_corma) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$nombre, $apellido, $usuario_id, $licencia_val, $examen_corma_val]);

            // Vaciar formulario si éxito
            $valores = [
                'nombre' => '',
                'apellido' => '',
                'correo' => '',
                'licencia' => false,
                'examen_corma' => false,
            ];

            echo "<div class='alert alert-success'>Chofer registrado correctamente.</div>";
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                $errores[] = "El correo '$correo' ya está registrado. Por favor usa otro.";
            } else {
                $errores[] = "Ocurrió un error: " . $e->getMessage();
            }
            // NO vaciar datos aquí para que se mantengan
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
        echo "<div class='alert alert-success'>Chofer eliminado correctamente.</div>";
    }
}

// CONSULTAS GENERALES
$micros   = $pdo->query("SELECT id, patente, ruta_id FROM micro")->fetchAll();
$rutas    = $pdo->query("SELECT id, nombre FROM ruta")->fetchAll();
$choferes = $pdo->query("SELECT c.id, c.nombre, c.apellido, c.licencia, c.examen_corma, u.correo, c.usuario_id 
                          FROM chofer c
                          JOIN usuario u ON c.usuario_id = u.id")->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Gestión de Choferes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .no-circular {
            background-color: #f8d7da !important; /* rojo claro */
        }
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
        .btn-volver {
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: rgb(37, 95, 241);
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 6px 12px;
            font-weight: 600;
            cursor: pointer;
            z-index: 9999;
            text-decoration: none;
        }
        .btn-volver:hover {
            background-color: rgb(42, 158, 235);
            color: #fff;
            text-decoration: none;
        }
    </style>

    <script>
        function confirmarEdicion(event) {
            const licencia = document.getElementById('licenciaCheckEdit')?.checked;
            const corma = document.getElementById('cormaCheckEdit')?.checked;

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

<a href="javascript:history.back()" class="btn-volver">Volver</a>

<div class="container py-5">
    <h2 class="text-center mb-4">Gestión de Choferes</h2>

    <!-- Mostrar errores -->
    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach($errores as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

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
            <input type="text" name="nombre" class="form-control mb-2" required
                   value="<?= htmlspecialchars($valores['nombre']) ?>" />
            <input type="text" name="apellido" class="form-control mb-2" required
                   value="<?= htmlspecialchars($valores['apellido']) ?>" />
            <input type="email" name="correo" class="form-control mb-2" required
                   value="<?= htmlspecialchars($valores['correo']) ?>" />

            <div class="form-check mb-2">
                <input type="checkbox" name="licencia" id="licenciaCheckEdit" class="form-check-input"
                    <?= $valores['licencia'] ? 'checked' : '' ?> />
                <label for="licenciaCheckEdit" class="form-check-label">Licencia A4</label>
            </div>

            <?php if (!$valores['licencia']): ?>
                <div class="alert alert-warning">⚠ Este chofer no puede circular porque no tiene licencia A4.</div>
            <?php endif; ?>

            <div class="form-check mb-2">
                <input type="checkbox" name="examen_corma" id="cormaCheckEdit" class="form-check-input"
                    <?= $valores['examen_corma'] ? 'checked' : '' ?> />
                <label for="cormaCheckEdit" class="form-check-label">Aprobó examen CORMA</label>
            </div>

            <?php if (!$valores['examen_corma']): ?>
                <div class="alert alert-warning">⚠ Este chofer no puede circular porque no aprobó el examen CORMA.</div>
            <?php endif; ?>

            <button type="submit" name="update" class="btn btn-primary">Actualizar Chofer</button>
            <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    <?php else: ?>
        <h3 class="text-center mt-5">Agregar Nuevo Chofer</h3>
        <form method="POST" class="mx-auto" style="max-width: 400px;">
            <input type="text" name="nombre" class="form-control mb-2" required placeholder="Nombre"
                value="<?= htmlspecialchars($valores['nombre']) ?>" />
            <input type="text" name="apellido" class="form-control mb-2" required placeholder="Apellido"
                value="<?= htmlspecialchars($valores['apellido']) ?>" />
            <input type="email" name="correo" class="form-control mb-2" required placeholder="Correo"
                value="<?= htmlspecialchars($valores['correo']) ?>" />
            <input type="password" name="contraseña" class="form-control mb-2" required placeholder="Contraseña" />

            <div class="form-check mb-2">
                <input type="checkbox" name="licencia" id="licenciaCheckAdd" class="form-check-input"
                    <?= $valores['licencia'] ? 'checked' : '' ?> />
                <label for="licenciaCheckAdd" class="form-check-label">Licencia A4</label>
            </div>

            <div class="form-check mb-2">
                <input type="checkbox" name="examen_corma" id="cormaCheckAdd" class="form-check-input"
                    <?= $valores['examen_corma'] ? 'checked' : '' ?> />
                <label for="cormaCheckAdd" class="form-check-label">Aprobó examen CORMA</label>
            </div>

            <button type="submit" name="add" class="btn btn-success">Agregar Chofer</button>
        </form>
    <?php endif; ?>

</div>
</body>
</html>
