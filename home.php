<?php 
include 'config.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$isAdmin = ($_SESSION['usuario_rol'] === 'admin'); // Verificación del rol de administrador

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Mapa Transporte Público - Osorno</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <style>
    body, html {
      margin: 0;
      padding: 0;
      height: 100%;
    }

    .navbar {
      background-color: #343a40;
      color: white;
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: sans-serif;
    }

    .navbar .buttons a {
      color: white;
      text-decoration: none;
      margin-left: 15px;
      padding: 6px 12px;
      border: 1px solid white;
      border-radius: 4px;
      transition: background 0.3s;
    }

    .navbar .buttons a:hover {
      background-color: white;
      color: #343a40;
    }

    #map {
      height: calc(95% - 50px); 
    }

     .buttons {
    display: flex;
    align-items: center;
    gap: 15px;
    } 

    .buttons a {
    color: white;
    text-decoration: none;
    padding: 8px;
  }

  .dropdown {
    position: relative;
    display: inline-block;
    color: white;
    text-decoration: none;
    margin-left: 15px;
    border: 1px solid white;
    border-radius: 4px;
    transition: background 0.3s;
  }

  .dropdown-btn {
    background: #343a40;
    border: none;
    color: white;
    cursor: pointer;
    padding: 8px;
    font-size: 16px;
  }

  .dropdown-content {
    display: none;
    position: absolute;
    background-color:  #343a40;
    min-width: 160px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    z-index: 1;
    border-radius: 1px;
    overflow: hidden;
  }

  .dropdown-content a {
    color: black;
    padding: 1px;
    display: block;
    text-decoration: none;
  }

  .dropdown-content a:hover {
    background-color: #f1f1f1;
  }

  .dropdown:hover .dropdown-content {
    display: block;
  }

  .leaflet-container {
  z-index: 0 !important;
}

/* El navbar siempre encima */
.navbar {
  z-index: 1001;
}

/* El menú dropdown encima del mapa */
.dropdown-content {
  z-index: 1002;
}
#recordatorioModal {
  position: fixed;
  top: 20%;
  left: 50%;
  transform: translateX(-50%);
  background: white;
  border: 2px solid #444;
  padding: 20px;
  z-index: 1101; /* MÁS ALTO */
  display: none;
}

#modalOverlay {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.4);
  display: none;
  z-index: 1100; /* MÁS ALTO */
}

.panel-lateral {
  position: fixed;
  top: 0;
  right: -100%;
  width: 300px;
  max-width: 80%;
  height: 100%;
  background-color: #f4f4f4;
  box-shadow: -2px 0 5px rgba(0, 0, 0, 0.2);
  overflow-y: auto;
  padding: 20px;
  transition: right 0.3s ease;
  z-index: 1001; /* MENOR */
}
.panel-lateral.abierto {
  right: 0;
}

/* Botón para cerrar */
.cerrar-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  border: none;
  background: none;
  font-size: 18px;
  cursor: pointer;
}

/* Botón flotante para abrir panel */
#abrirPanel {
  position: fixed;
  top: 90%;
  right: 0;
  z-index: 1000; /* MENOR */
  background-color: #343a40;
  color: white;
  border: none;
  padding: 10px 16px;
  font-size: 14px;
  border-radius: 4px;
  cursor: pointer;
}

/* ===== Responsive design ===== */

/* Tablets horizontales o pantallas medianas */
@media (max-width: 1024px) {
  .panel-lateral {
    width: 60%;
  }
}

/* Celulares grandes y tablets verticales */
@media (max-width: 768px) {
  .panel-lateral {
    width: 80%;
  }

  #abrirPanel {
    top: 10px;
    right: 10px;
    padding: 8px 12px;
    font-size: 13px;
  }
}

/* Celulares pequeños */
@media (max-width: 480px) {
  .panel-lateral {
    width: 100%;
    padding: 15px;
  }

  #abrirPanel {
    font-size: 12px;
    padding: 6px 10px;
  }
}


  </style>
</head>
<body>
  <div class="navbar">
  <div class="title">🚍 Mapa Transporte Público - Osorno</div>
  <div class="buttons">
    <a href="">Inicio</a>
    <a href="usuarioreclamo.php">Reclamos</a>
    
    <?php if ($isAdmin): ?>
    <div class="dropdown">
      <button class="dropdown-btn">Opciones ▾</button>
      <div class="dropdown-content">
        <a href="conductores.php">Ver conductores</a>
        <a href="micros.php">Ver micros</a>
        <a href="ruta.html">Ver rutas</a>
        <a href="paraderos.html">Ver paraderos</a>
        <a href="reclamo.php">Ver reclamos</a>
        <a href="asignar_micro.php">Asignar micros</a>
        <a href="matenimientomicro.php">Mantenimiento de micros</a>
      </div>
    </div>
    <?php endif; ?>

    <a href="logout.php">Cerrar sesión</a>
  </div>
</div>
<div id="modalOverlay">
<div id="recordatorioModal">
  <h3>Nuevo Recordatorio</h3>

  <label>Hora: <input type="time" id="horaRecordatorio"></label>

  <label>Ruta:
    <select id="rutaSelect">
      <option value="">-- Selecciona una ruta --</option>
    </select>
  </label>

  <div>
    <label>
    <input type="radio" name="tipo" value="unico" onclick="mostrarFecha()"> Un día específico
  </label>
  <label>
    <input type="radio" name="tipo" value="semanal" onclick="mostrarDiasSemana()"> Días de la semana
  </label>
  </div>

  <div id="fechaInput" style="display:none">
    <label>Fecha: <input type="date" id="diaFecha"></label>
  </div>

  <div id="diasInput" style="display:none">
    <label><input type="checkbox" value="lunes"> Lunes</label>
    <label><input type="checkbox" value="martes"> Martes</label>
    <label><input type="checkbox" value="miercoles"> Miércoles</label>
    <label><input type="checkbox" value="jueves"> Jueves</label>
    <label><input type="checkbox" value="viernes"> Viernes</label>
    <label><input type="checkbox" value="sabado"> Sábado</label>
    <label><input type="checkbox" value="domingo"> Domingo</label>
  </div>

  <button onclick="guardarRecordatorio()">Guardar</button>
  <button onclick="cerrarModal()">Cancelar</button>
</div>
</div>

<button id="abrirPanel">📋 Ver Recordatorios</button>

<div id="panelRecordatorios" class="panel-lateral">
  <button class="cerrar-btn" onclick="cerrarPanel()">✖</button>
  <h3>🕑 Tus Recordatorios</h3>
  <div id="listaRecordatorios"></div>
</div>

  <div id="map"></div>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    const map = L.map('map').setView([-40.573, -73.134], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    fetch('rutas_con_coordenadas.php')
  .then(res => res.json())
  .then(rutas => {
    rutas.forEach(ruta => {
      const polyline = L.polyline(ruta.coordenadas, { color: ruta.color }).addTo(map);
      polyline.bindPopup(`<strong>${ruta.nombre}</strong>`);
    });
  })
  .catch(err => console.error('Error cargando rutas:', err));

  fetch('listar_paraderos.php')
  .then(res => res.json())
  .then(paraderos => {
    paraderos.forEach(p => {
      const marker = L.marker([p.lat, p.lng]).addTo(map);


marker.bindPopup(`
  <strong>${p.nombre}</strong><br>
  <button onclick="crearRecordatorio(${p.id})">
    Crear recordatorio
  </button>
`);

    });
  });

let recordatorioEditandoId = null;


function crearRecordatorio(paraderoId) {
  
  const diaFechaInput = document.getElementById('diaFecha');
  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);
  diaFechaInput.min = hoy.toISOString().split('T')[0];
  diaFechaInput.value = ''; 

  console.log("Crear recordatorio para paradero:", paraderoId);
  recordatorioParaderoId = paraderoId;
  fetch('listar_rutas.php')
  .then(res => res.json())
  .then(rutas => {
    const selector = document.getElementById('rutaSelect');
    selector.innerHTML = '<option value="">-- Selecciona una ruta --</option>';
    rutas.forEach(ruta => {
      const option = document.createElement('option');
      option.value = ruta.id;
      option.textContent = ruta.nombre;
      selector.appendChild(option);
    });
  });
  document.getElementById("recordatorioModal").style.display = 'block';
  document.getElementById("modalOverlay").style.display = 'block';
  document.getElementById("fechaInput").style.display = 'none';
  document.getElementById("diasInput").style.display = 'none';
}

function cerrarModal() {
  document.getElementById("recordatorioModal").style.display = 'none';
  document.getElementById("modalOverlay").style.display = 'none';
}

function mostrarFecha() {
  document.getElementById("fechaInput").style.display = "block";
  document.getElementById("diasInput").style.display = "none";
}

function mostrarDiasSemana() {
  document.getElementById("fechaInput").style.display = "none";
  document.getElementById("diasInput").style.display = "block";
}

function guardarRecordatorio() {
  const tipo = document.querySelector('input[name="tipo"]:checked')?.value;
  const hora = document.getElementById('horaRecordatorio')?.value;
  const rutaId = document.getElementById('rutaSelect')?.value;
  console.log("tipo:", tipo);
  console.log("hora:", hora);
  console.log("rutaId:", rutaId);


  if (!tipo) {
    alert("Debes seleccionar si es un día específico o días de la semana.");
    return;
  }

  if (!hora || !rutaId) {
    alert("Debes ingresar la hora y seleccionar una ruta.");
    return;
  }

  // Validación: hora no puede estar entre 00:00 y 05:30
  // Convertimos la hora a minutos desde medianoche para facilitar la comparación
  function horaEnMinutos(horaStr) {
    const [h, m] = horaStr.split(':').map(Number);
    return h * 60 + m;
  }

  const minutosHora = horaEnMinutos(hora);
  const inicioProhibido = 0;      // 00:00 en minutos
  const finProhibido = 5 * 60 + 30; // 05:30 en minutos

  if (minutosHora >= inicioProhibido && minutosHora <= finProhibido) {
    alert("❌ No se pueden establecer recordatorios entre las 00:00 y las 05:30.");
    return;
  }


  let dias = [];
  let fecha = null;

  if (tipo === 'semanal') {
    document.querySelectorAll('#diasInput input[type="checkbox"]:checked')
      .forEach(cb => dias.push(cb.value));
    if (dias.length === 0) {
      alert("Selecciona al menos un día de la semana.");
      return;
    }
  } else if (tipo === 'unico') {
    fecha = document.getElementById('diaFecha')?.value;
    if (!fecha) {
      alert("Selecciona una fecha válida.");
      return;
    }
  }

  if (!recordatorioParaderoId) {
    alert("Paradero no definido.");
    return;
  }
  if (tipo === 'unico') {
  fecha = document.getElementById('diaFecha')?.value;
  if (!fecha) {
    alert("Selecciona una fecha válida.");
    return;
  }

  // Validación extra: que no sea una fecha pasada
  const fechaSeleccionada = new Date(fecha);
  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);

  if (fechaSeleccionada < hoy) {
    alert("La fecha no puede ser anterior a hoy.");
    return;
  }
}
  const datos = {
  paradero_id: recordatorioParaderoId,
  ruta_id: rutaId,
  hora: hora,
  tipo: tipo,
  dias: dias,
  fecha: fecha
};

if (recordatorioEditandoId) {
  datos.id = recordatorioEditandoId;
}

  console.log("Datos a enviar:", datos);

  const endpoint = recordatorioEditandoId ? 'editar_recordatorio.php' : 'guardar_recordatorio.php';

fetch(endpoint, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  credentials: 'include', // ESTO ES CLAVE
  body: JSON.stringify(datos)
})
  .then(res => res.text())
  .then(msg => {
    alert(msg);
    cerrarModal();
    cargarRecordatorios();
  })
  .catch(err => alert("Error al guardar el recordatorio."));
}




function cargarRecordatorios() {
  fetch('listar_recordatorios.php')
    .then(res => res.json())
    .then(data => {
      const contenedor = document.getElementById('listaRecordatorios');
      contenedor.innerHTML = '';

      if (data.length === 0) {
        contenedor.innerHTML = '<p>No tienes recordatorios.</p>';
        return;
      }

      data.forEach(rec => {
        rec.dias = Array.isArray(rec.dias) ? rec.dias : JSON.parse(rec.dias || '[]');

        let descripcionFecha;
        if (rec.tipo === 'unico' && rec.fecha) {
          // Formatea la fecha tipo único
          const fecha = new Date(rec.fecha);
          descripcionFecha = 'Fecha: ' + fecha.toLocaleDateString('es-CL'); // Muestra como dd/mm/aaaa
        } else if (rec.tipo === 'semanal' && rec.dias.length > 0) {
          // Muestra los días seleccionados
          descripcionFecha = 'Día(s): ' + rec.dias.join(', ');
        } else {
          descripcionFecha = 'Sin fecha';
        }
        const div = document.createElement('div');
        div.innerHTML = `
          <strong>Paradero:</strong> ${rec.paradero_nombre} <br>
          <strong>Ruta:</strong> ${rec.ruta_nombre} <br>
          <strong>Hora:</strong> ${rec.hora} <br>
          ${descripcionFecha}<br>
          <button onclick="editarRecordatorio(${rec.id})">Editar</button>
          <button onclick= "eliminarRecordatorio(${rec.id})" >Eliminar</button>
          <hr>
        `;
        contenedor.appendChild(div);
      });
    })
    .catch(err => {
      console.error('Error cargando recordatorios:', err);
      document.getElementById('listaRecordatorios').innerHTML = 'Error al cargar.';
    });
}
function eliminarRecordatorio(id) {
  if (!id) {
    alert('ID no válido');
    return;
  }

  if (!confirm('¿Estás seguro de que deseas eliminar este recordatorio?')) return;

  fetch('eliminar_recordatorios.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: id })
  })
  .then(res => res.json())
  .then(res => {
    if (res.error) {
      alert('Error: ' + res.error);
    } else {
      alert(res.message);
      cargarRecordatorios();
    }
  })
  .catch(err => {
    console.error(err);
    alert('Error al eliminar el recordatorio.');
  });
}

function editarRecordatorio(id) {

  const diaFechaInput = document.getElementById('diaFecha');
  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);
  diaFechaInput.min = hoy.toISOString().split('T')[0];
  fetch(`obtener_recordatorio.php?id=${id}`)
    .then(res => res.json())
    .then(rec => {
      recordatorioEditandoId = id;
      recordatorioParaderoId = rec.paradero_id;

      // Primero cargamos las rutas
      fetch('listar_rutas.php')
        .then(res => res.json())
        .then(rutas => {
          const selector = document.getElementById('rutaSelect');
          selector.innerHTML = '<option value="">-- Selecciona una ruta --</option>';
          rutas.forEach(ruta => {
            const option = document.createElement('option');
            option.value = ruta.id;
            option.textContent = ruta.nombre;
            selector.appendChild(option);
          });

          // Solo después de llenar el selector, asignamos la ruta seleccionada
          selector.value = rec.ruta_id;
        });

      // Mostrar modal
      document.getElementById("recordatorioModal").style.display = 'block';
      document.getElementById("modalOverlay").style.display = 'block';

      document.getElementById('horaRecordatorio').value = rec.hora;

      if (rec.tipo === 'unico') {
        mostrarFecha();
        document.getElementById('diaFecha').value = rec.fecha;
      } else if (rec.tipo === 'semanal') {
        mostrarDiasSemana();
        let diasCheckboxes = document.querySelectorAll('#diasInput input[type="checkbox"]');
        diasCheckboxes.forEach(cb => {
          cb.checked = rec.dias.includes(cb.value);
        });
      }
    });
}


</script>

    
<script>
document.getElementById('abrirPanel').addEventListener('click', () => {
  document.getElementById('panelRecordatorios').classList.add('abierto');
  cargarRecordatorios(); // cargar la lista al abrir
});

function cerrarPanel() {
  document.getElementById('panelRecordatorios').classList.remove('abierto');
}
</script>
<script>
  
  // Escucha el cambio de tipo de recordatorio
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="tipo"]').forEach(radio => {
      radio.addEventListener('change', () => {
        const tipo = document.querySelector('input[name="tipo"]:checked').value;
        if (tipo === 'unico') {
          document.getElementById("fechaInput").style.display = 'block';
          document.getElementById("diasInput").style.display = 'none';
        } else {
          document.getElementById("fechaInput").style.display = 'none';
          document.getElementById("diasInput").style.display = 'block';
        }
      });
    });
  });
</script>


  </body>
</html>
