<?php
/**
 * MÓDULO DE GESTIÓN DE EXCUSAS
 * 
 * Este archivo maneja la gestión completa de excusas para directores de unidad y docentes.
 * Funcionalidades:
 * - Registro de excusas por parte de directivos y directores (para estudiantes)
 * - Validación y aprobación/rechazo de excusas por directores
 * - Filtrado de excusas por curso en validacion de excusas e historial de excusas
 * - Carga dinámica de cursos según el estudiante
 * - Interfaz adaptativa según el rol del usuario
 */

// Incluir archivo de conexión a la base de datos
include_once '../../php/conexion.php';

// Iniciar sesión para verificar autenticación y rol
session_start();

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['rol'])) {
    header("Location: index.html");
    exit;
}

// Obtener rol del usuario para control de acceso
$rol = $_SESSION['rol'];

// Variables de control para mostrar funcionalidades según el rol
$mostrarExcusas = ($rol === "Directivo" || $rol === "Director de Unidad");  // Solo directivos y directores pueden registrar excusas
$mostrarValidacion = ($rol === "Director de Unidad");                        // Solo directores pueden validar excusas

// Inicializar variables para almacenar datos
$data = [];      // Array para almacenar excusas pendientes
$cursos = [];    // Array para almacenar cursos disponibles

// Si el usuario puede validar excusas, cargar datos de excusas pendientes
if ($mostrarValidacion) {
    try {
        // Consulta para obtener excusas pendientes de validación
        $stmt = $conn->prepare("
            SELECT 
                exc.id_excusa,               -- ID único de la excusa
                exc.fecha_falta_excu,        -- Fecha de la falta
                exc.fecha_radicado_excu,     -- Fecha de radicado
                tex.tipo_excu,               -- Tipo de excusa
                exc.soporte_excu,            -- Enlace al archivo de soporte
                est.num_doc_estudiante AS id_estudiante,  -- Número de documento del estudiante
                est.nombre_estudiante AS nombre_estudiante, -- Nombre del estudiante
                exc.descripcion_excu,        -- Descripción del motivo
                cae.curso,                   -- Nombre del curso
                cae.id_curs_asig_es,        -- ID del curso-asignatura-estudiante
                est.programa_estudiante AS programa,      -- Programa académico del estudiante
                exc.estado_excu              -- Estado actual de la excusa
            FROM excusas AS exc
            INNER JOIN estudiantes AS est 
                ON exc.num_doc_estudiante = est.num_doc_estudiante
            INNER JOIN (
                SELECT DISTINCT id_curs_asig_es, curso, est_codigo_unico
                FROM t_v_exc_asig_mat_est
            ) AS cae
                ON exc.num_doc_estudiante = cae.est_codigo_unico 
                AND exc.id_curs_asig_es = cae.id_curs_asig_es
            INNER JOIN tiposexcusas AS tex 
                ON exc.tipo_excu = tex.id_tipo_excu
            WHERE exc.estado_excu = 3       -- Solo excusas pendientes (estado 3)
            ORDER BY exc.id_excusa ASC;
        ");

        // Ejecutar consulta y obtener excusas pendientes
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); // liberar antes de nueva consulta

        // Consulta para obtener lista de cursos únicos con excusas
        $stmtCursos = $conn->prepare("
            SELECT DISTINCT cae.curso
            FROM excusas AS exc
            INNER JOIN t_v_exc_asig_mat_est AS cae 
                ON exc.num_doc_estudiante = cae.est_codigo_unico 
                AND exc.id_curs_asig_es = cae.id_curs_asig_es
        ");
        $stmtCursos->execute();
        $cursos = $stmtCursos->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (PDOException $e) {
        die("Error al obtener los datos: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <!-- Configuración básica del documento HTML -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Título de la página -->
    <title>Inicio Director De Unidad</title>
    
    <!-- Favicon de la institución -->
    <link rel="icon" type="image/x-icon" href="../../Images/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="../../Images/favicon.ico">
    
    <!-- Importación de Bootstrap para el diseño responsivo -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Hoja de estilos personalizada -->
    <link rel="stylesheet" href="../../CSS/ExcusasCotecnova/excusas.css">
</head>

<body>
    <!-- Contenedor principal de la página -->
    <div class="container">
        <!-- Encabezado con título y logo -->
        <div class="centered-content">
            <h1>Inicio Director De Unidad</h1>
        </div>

        <!-- Logo institucional -->
        <img src="../../Images/cotecnovaLogo.png" alt="imagen" width="150" height="150">

        <!-- MÓDULO 1: Registrar Excusas (solo para roles autorizados) -->
        <?php if ($mostrarExcusas): ?>
            <div class="excuse-form">
                <h2>Registrar Excusas</h2>
                
                <!-- Campo para cédula del estudiante -->
                <label for="studentId">Cédula del Estudiante:</label>
                <input type="text" id="studentId" name="studentId" class="form-control" required>

                <!-- Selector de curso (se habilita al ingresar cédula) -->
                <label for="selectCourse">Seleccionar Curso:</label>
                <select class="form-select" name="id_curs_asig_es" id="id_curs_asig_es" required disabled>
                    <option value="">Seleccione un curso</option>
                    <?php foreach ($asignaturas_estudiante as $asig): ?>
                        <!-- trim para limpiar espacios invisibles al importar de bd -->
                        <option value="<?= trim($asig[' id_curs_asig_es ']) ?>">
                            <?= trim($asig[' nombre_asignatura ']) . ' (' . trim($asig[' letra_grupo ']) . ') -' . trim($asig[' jornada ']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Campo para fecha de la excusa -->
                <div>
                    <br>
                    <label for="fecha">Fecha de la Excusa:</label>
                    <input type="date" id="fecha" name="fecha" required>
                    <br><br>
                </div>

                <!-- Selector de tipo de excusa -->
                <label>Tipo de Excusa:</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="excuseDocument" value="1" onclick="mostrarInput('otroInput')">
                    <label class="form-check-label" for="excuseDocument">Por Salud</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="excuseDocument" value="2" onclick="mostrarInput('otroInput')">
                    <label class="form-check-label" for="excuseDocument">Laboral</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="excuseDocument" value="3" onclick="mostrarInput('otroInput')">
                    <label class="form-check-label" for="excuseDocument">Otro</label>
                </div>
                
                <!-- Campo adicional para especificar tipo "Otro" -->
                <div id="otroInput" style="display: none;">
                    <label for="otroExplanation">Explique cuál:</label>
                    <input type="text" id="otroExplanation" name="otroExplanation" class="form-control" required>
                </div>
                <br>

                <!-- Campo para motivo de la excusa -->
                <label for="excuseReason">Motivo de la excusa:</label>
                <small>Si la excusa es por más de un día, indicar aqui la cantidad.</small>
                <input type="text" id="excuseReason" name="excuseReason" class="form-control" required>
                <br>

                <!-- Campo para subir archivo de soporte -->
                <div>
                    <label for="soporteExcusa">Subir soporte de la Excusa: </label>
                    <input type="file" name="file" id="fileInput" class="form-control" accept=".pdf,.zip,.jpg,.png" required>
                    <small>El archivo no puede superar los 10MB. (Solo se aceptan archivos en formato .pdf,.zip,.jpg,.png).</small>
                </div>
                <br>

                <!-- Botón para registrar la excusa -->
                <button class="btn btn-primary" onclick="registrarExcusa()" id="btnRegistrarExcusa" >Registrar Excusa</button>
            </div>
        <?php endif; ?>

        <!-- MÓDULO 2: Validar y Ver Historial (solo para directores de unidad) -->
        <?php if ($mostrarValidacion): ?>
            <div class="validate-form">
                <br><br>
                <h2>Validar y Ver Historial</h2>
                
                <!-- Selector de curso para filtrar excusas -->
                <label for="selectCourseValidate">Seleccionar Curso:</label>
                <select id="selectCourseValidate" name="selectCourseValidate" class="form-select" onchange="filtrarExcusas()">
                    <option value="Todos">Todos</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?= htmlspecialchars($curso) ?>"><?= htmlspecialchars($curso) ?></option>
                    <?php endforeach; ?>
                </select>

                <br>

                <!-- Tabla de excusas pendientes de validación -->
                <table id="excuseTable" class="table">
                    <thead>
                        <tr>
                            <th>Número Excusa</th>
                            <th>Fecha</th>
                            <th>Fecha de Radicado</th>
                            <th>Tipo de Excusa</th>
                            <th>Soporte</th>
                            <th>Número de Documento</th>
                            <th>Nombre del Estudiante</th>
                            <th>Observaciones</th>
                            <th>Curso</th>
                            <th>Programa</th>
                            <th>Aprobar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Iterar sobre cada excusa pendiente -->
                        <?php foreach ($data as $dat): ?>
                            <tr data-curso="<?= htmlspecialchars($dat['curso']) ?>">
                                <td><?= htmlspecialchars($dat['id_excusa']) ?></td>
                                <td><?= htmlspecialchars($dat['fecha_falta_excu']) ?></td>
                                <td><?= htmlspecialchars($dat['fecha_radicado_excu']) ?></td>
                                <td><?= htmlspecialchars($dat['tipo_excu']) ?></td>
                                <td><a href="<?= htmlspecialchars($dat['soporte_excu']) ?>" target="_blank">Ver soporte</a></td>
                                <td><?= htmlspecialchars($dat['id_estudiante']) ?></td>
                                <td><?= htmlspecialchars($dat['nombre_estudiante']) ?></td>
                                <td><?= htmlspecialchars($dat['descripcion_excu']) ?></td>
                                <td><?= htmlspecialchars($dat['curso']) ?></td>
                                <td><?= htmlspecialchars($dat['id_curs_asig_es']) ?></td>
                                <td>
                                    <!-- Opciones de aprobación/rechazo para cada excusa -->
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="approvalRadio_<?= $dat['id_excusa'] ?>" value="1"
                                            <?= $dat['estado_excu'] == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="approvalRadio_<?= $dat['id_excusa'] ?>">Aprobar</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="approvalRadio_<?= $dat['id_excusa'] ?>" value="2"
                                            <?= $dat['id_excusa'] == 2 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="approvalRadio_<?= $dat['id_excusa'] ?>">Denegar</label>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Botones de acción -->
                <button class="btn btn-primary" onclick="guardarCambios()">Guardar</button><br><br><br>
                <a href="./principal.php" class="btn btn-secondary">Volver al inicio</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Importación de Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script principal de funcionalidades -->
    <script>
        // Array para almacenar cambios seleccionados en las excusas
        let cambiosSeleccionados = [];

        /**
         * Función para guardar los cambios de estado de las excusas
         * Recopila todas las selecciones de aprobación/rechazo y solicita justificación
         */
        function guardarCambios() {
            const radiosSeleccionados = document.querySelectorAll('input[type="radio"]:checked');
            cambiosSeleccionados = [];

            // Procesar cada excusa con cambios
            radiosSeleccionados.forEach(radio => {
                const name = radio.name;
                const id_excusa = name.split('_')[1];
                const estado = radio.value;

                // Solicitar justificación para cada cambio
                const justificacion = prompt(`Ingrese la justificación para la excusa #${id_excusa} (puede dejarlo vacío):`) || '';

                cambiosSeleccionados.push({ id_excusa, estado, justificacion });
            });

            // Validar que haya cambios para guardar
            if (cambiosSeleccionados.length === 0) {
                alert('No hay cambios seleccionados.');
                return;
            }

            // Enviar cambios al servidor
            enviarCambios();
        }

        /**
         * Función para enviar los cambios al servidor
         * Realiza petición AJAX para actualizar el estado de las excusas
         */
        function enviarCambios() {
            fetch('../../php/actualizar_estado_excusa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cambios: cambiosSeleccionados })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Cambios guardados y correos enviados.');
                    location.reload(); // Recargar página para mostrar cambios
                } else {
                    alert('Error al guardar: ' + data.mensaje);
                }
            })
            .catch(err => {
                console.error('Error al enviar los datos:', err);
                alert('Error al procesar la solicitud');
            });
        }

        /**
         * Función para filtrar excusas por curso seleccionado
         * Muestra/oculta filas de la tabla según el curso seleccionado
         */
        function filtrarExcusas() {
            var selectedCurso = document.getElementById('selectCourseValidate').value;
            var table = document.getElementById('excuseTable');
            var rows = table.getElementsByTagName('tr');
            
            // Iterar sobre todas las filas de la tabla (excluyendo el encabezado)
            for (var i = 1; i < rows.length; i++) {
                var rowCurso = rows[i].getAttribute('data-curso');
                // Mostrar fila si es "Todos" o coincide con el curso seleccionado
                rows[i].style.display = (selectedCurso === 'Todos' || selectedCurso === rowCurso) ? '' : 'none';
            }
        }

        /**
         * Función para registrar una nueva excusa
         * Valida formulario, sube archivo y registra excusa en la base de datos
         */
        function registrarExcusa() {
            // Obtener valores del formulario
            const studentid = document.getElementById('studentId').value.trim();
            const curso = document.getElementById('id_curs_asig_es').value.trim();
            const fecha = document.getElementById('fecha').value.trim();
            const motivo = document.getElementById('excuseReason').value.trim();
            const tipoExcusa = document.querySelector('input[name="excuseDocument"]:checked');
            const otroTipo = document.getElementById('otroExplanation').value.trim();
            const archivo = document.querySelector('input[type="file"]').files[0];
            const btn = document.getElementById("btnRegistrarExcusa");

            // Validar que todos los campos estén completos
            if (!studentid || !curso || !fecha || !motivo || !tipoExcusa || !archivo) {
                alert("Complete todos los campos");
                return;
            }

            btn.disabled = true;
            btn.textContent = "Guardando...";

            // Subir archivo a Dropbox
            const fileData = new FormData();
            fileData.append('file', archivo);
            fileData.append('num_doc_estudiante', studentid);


            fetch('../../php/uploadFiles.php', {
                    method: 'POST',
                    body: fileData
                })
                .then(res => res.json())
                .then(uploadResp => {

                    if (!uploadResp.success || !uploadResp.url) {
                        throw new Error('Error al subir archivo: ' + uploadResp.mensaje);
                    }

                    //Registrar excusa en la base de datos
                    const formData = new FormData();
                    formData.append('num_doc_estudiante', studentid);
                    formData.append('id_curs_asig_es', curso);
                    formData.append('fecha_falta_excu', fecha);
                    formData.append('tipo_excu', tipoExcusa.value);
                    formData.append('otro_tipo_excu', otroTipo);
                    formData.append('descripcion_excu', motivo);
                    formData.append('soporte_excu', uploadResp.url);

                    return fetch('../../php/registrar_excusa_docente.php', {
                        method: 'POST',
                        body: formData
                    });
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error HTTP al registrar excusa: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Excusa registrada correctamente');
                        location.reload(); // Recargar página
                    } else {
                        alert('Error: ' + data.mensaje);
                    }
                })
                .finally(() => {
                    // 🔹 Rehabilitar botón al terminar
                    btn.disabled = false;
                    btn.textContent = "Registrar Excusa";
                });

        }

        /**
         * Función para mostrar/ocultar campo de especificación de tipo "Otro"
         * Se ejecuta cuando cambia la selección del tipo de excusa
         */
        function mostrarInput(id) {
            const input = document.getElementById(id);
            const selectedValue = document.querySelector('input[name="excuseDocument"]:checked').value;

            if (selectedValue === "3") { // Si selecciona "Otro"
                input.style.display = "block";
                document.getElementById('otroExplanation').required = true;
            } else {
                input.style.display = "none";
                document.getElementById('otroExplanation').required = false;
            }
        }

        // Event listener para cargar cursos del estudiante cuando se ingresa la cédula
        document.getElementById('studentId').addEventListener('blur', function() {
            const studentId = this.value.trim();
            const courseSelect = document.getElementById('id_curs_asig_es');

            // Validar que se haya ingresado una cédula
            if (studentId === "") {
                courseSelect.innerHTML = '<option value="">Ingrese la cédula</option>';
                courseSelect.disabled = true;
                return;
            }

            // Preparar datos para la consulta
            const formData = new FormData();
            formData.append('num_doc_estudiante', studentId);

            // Realizar petición AJAX para obtener cursos del estudiante
            fetch('../../php/obtener_cursos_estudiantes.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log("Respuesta recibida:", data);
                    if (data.success && data.cursos.length > 0) {
                        // Llenar selector con cursos encontrados
                        courseSelect.innerHTML = '<option value="">Seleccione un curso</option>';
                        data.cursos.forEach(curso => {
                            const option = document.createElement('option');
                            option.value = curso.id_curs_asig_es; // valor real que se guardará
                            option.textContent = curso.curso; // nombre visible
                            courseSelect.appendChild(option);
                        });
                        courseSelect.disabled = false;
                    } else {
                        // No se encontraron cursos
                        courseSelect.innerHTML = '<option value="">No se encontraron cursos</option>';
                        courseSelect.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    courseSelect.innerHTML = '<option value="">Error al cargar cursos</option>';
                    courseSelect.disabled = true;
                });
        });
    </script>
</body>

<!-- Modal para agregar justificación adicional -->
<div class="modal fade" id="modalJustificacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar nota adicional</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea id="textoJustificacion" class="form-control" rows="4" placeholder="Escriba una nota para el docente (opcional)"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="enviarCambios()">Enviar</button>
            </div>
        </div>
    </div>
</div>

</html>