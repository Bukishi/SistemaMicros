<?php
include 'config.php';
$stmt = $pdo->query("SELECT * FROM chofer");
$choferes = $stmt->fetchAll();
?>

<h2>Lista de Choferes</h2>
<a href="crear_chofer.php">Agregar Chofer</a>
<table border="1">
  <tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>Licencia</th><th>Examen CORMA</th><th>Acciones</th></tr>
  <?php foreach ($choferes as $c): ?>
  <tr>
    <td><?= $c['id'] ?></td>
    <td><?= $c['nombre'] ?></td>
    <td><?= $c['apellido'] ?></td>
    <td><?= $c['licencia'] ?></td>
    <td><?= $c['examen_corma'] ? 'Sí' : 'No' ?></td>
    <td>
      <a href="editar_chofer.php?id=<?= $c['id'] ?>">Editar</a> |
      <a href="eliminar_chofer.php?id=<?= $c['id'] ?>">Eliminar</a>
    </td>
  </tr>
  <?php endforeach ?>
</table>
