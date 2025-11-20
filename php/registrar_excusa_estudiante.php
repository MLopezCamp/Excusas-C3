<?php
/**
 * REGISTRO DE EXCUSAS DE ESTUDIANTES
 * 
 * Este archivo maneja el registro de excusas enviadas por los estudiantes.
 * Funcionalidades:
 * - Validación de sesión de estudiante
 * - Inserción de excusa en la base de datos
 * - Envío de notificación por correo al director de unidad
 * - Manejo de archivos de soporte (enlaces a Dropbox)
 * - Respuestas en formato JSON para comunicación con el frontend
 */

// Importar clases de PHPMailer para envío de correos
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuración de reporte de errores para desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar cabecera para respuestas JSON
header('Content-Type: application/json; charset=utf-8');

// Incluir archivo de conexión a la base de datos
include_once './conexion.php';

// Iniciar sesión para verificar autenticación
session_start();

// Limpiar buffer de salida para evitar contenido no deseado
ob_clean();

// Validar que el estudiante esté autenticado
if (!isset($_SESSION['estudiante_id'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No autenticado']);
    exit;
}

// Recolectar y validar datos del formulario
$id_curs_asig_es    = $_POST['id_curs_asig_es'] ?? '';      // ID del curso-asignatura-estudiante
$fecha_falta_excu   = $_POST['fecha_falta_excu'] ?? '';     // Fecha de la falta
$tipo_excu          = $_POST['tipo_excu'] ?? '';             // Tipo de excusa (1=Salud, 2=Laboral, 3=Otro)
$otro_tipo_excu     = $_POST['otro_tipo_excu'] ?? '';       // Especificación si es tipo "Otro"
$descripcion_excu   = $_POST['descripcion_excu'] ?? '';     // Descripción del motivo
$num_doc_estudiante = $_SESSION['estudiante_id'];            // Número de documento del estudiante

// Validaciones básicas de campos obligatorios
if (empty($id_curs_asig_es) || empty($fecha_falta_excu) || empty($tipo_excu) || empty($descripcion_excu)) {
    echo json_encode(['success' => false, 'mensaje' => 'Faltan datos obligatorios']);
    exit;
}

// Validar que se haya proporcionado un soporte (enlace a Dropbox)
$soporte_excu = $_POST['soporte_excu'] ?? '';
if (empty($soporte_excu)) {
    echo json_encode(['success' => false, 'mensaje' => 'Soporte vacío']);
    exit;
}

try {
    //Insertar la excusa en la base de datos
    $fecha_radicado_excu = date('Y-m-d');  // Fecha actual como fecha de radicado
    $estado_inicial = 3;                    // Estado inicial: 3 = Pendiente

    // Consulta preparada para insertar la excusa
    $stmt = $conn->prepare("
        INSERT INTO excusas (
            id_curs_asig_es,        -- ID del curso-asignatura-estudiante
            fecha_falta_excu,       -- Fecha de la falta
            fecha_radicado_excu,    -- Fecha de radicado (hoy)
            soporte_excu,           -- Enlace al archivo de soporte
            descripcion_excu,       -- Descripción del motivo
            tipo_excu,              -- Tipo de excusa
            otro_tipo_excu,         -- Especificación si es tipo Otro
            estado_excu,            -- Estado inicial (Pendiente)
            num_doc_estudiante      -- Número de documento del estudiante
        ) VALUES (
            :id_curs_asig_es,
            :fecha_falta_excu,
            :fecha_radicado_excu,
            :soporte_excu,
            :descripcion_excu,
            :tipo_excu,
            :otro_tipo_excu,
            :estado_excu,
            :num_doc_estudiante
        )
    ");
    
    // Ejecutar inserción con los datos de la excusa
    $stmt->execute([
        ':id_curs_asig_es'    => $id_curs_asig_es,
        ':fecha_falta_excu'   => $fecha_falta_excu,
        ':fecha_radicado_excu'=> $fecha_radicado_excu,
        ':soporte_excu'       => $soporte_excu,
        ':descripcion_excu'   => $descripcion_excu,
        ':tipo_excu'          => $tipo_excu,
        ':otro_tipo_excu'     => $otro_tipo_excu,
        ':estado_excu'        => $estado_inicial,
        ':num_doc_estudiante' => $num_doc_estudiante
    ]);

    // Obtener el ID de la excusa recién insertada
    $id_excusa = $conn->lastInsertId();

    //Obtener datos para enviar correo de notificación al director de unidad
    $sqlDatos = "
        SELECT 
            exc.fecha_falta_excu,           -- Fecha de la falta
            tex.tipo_excu,                  -- Tipo de excusa
            est.nombre_estudiante,          -- Nombre del estudiante
            u.nombre_unidad,                -- Nombre de la unidad académica
            e.nombre_empleado AS nombre_director,    -- Nombre del director
            e.correo_empleado AS correo_director     -- Correo del director
        FROM excusas AS exc
        INNER JOIN estudiantes AS est 
            ON exc.num_doc_estudiante = est.num_doc_estudiante
        INNER JOIN unidades AS u
            ON est.id_unidad = u.id_unidad
        INNER JOIN empleados AS e
            ON e.id_unidad = u.id_unidad
           AND e.rol_empleado = 2          -- Rol 2 = Director de Unidad
        INNER JOIN tiposexcusas AS tex 
            ON exc.tipo_excu = tex.id_tipo_excu
        WHERE exc.id_excusa = :id_excusa
        LIMIT 1
    ";
    
    $stmtDatos = $conn->prepare($sqlDatos);
    $stmtDatos->execute([':id_excusa' => $id_excusa]);
    $datos = $stmtDatos->fetch(PDO::FETCH_ASSOC);

    // Variables para control del envío de correo
    $mail_sent = false;
    $mail_error = '';

    //Enviar correo de notificación si se encontró información del director
    if ($datos && !empty($datos['correo_director'])) {
        // Incluir archivos necesarios de PHPMailer
        require_once './Terceros/dropbox/PHPMailer-master/src/Exception.php';
        require_once './Terceros/dropbox/PHPMailer-master/src/PHPMailer.php';
        require_once './Terceros/dropbox/PHPMailer-master/src/SMTP.php';

        try {
            // Configurar PHPMailer
            $mail = new PHPMailer(true);
            $mail->isSMTP();                                    // Usar SMTP
            $mail->Host = 'smtp.gmail.com';                     // Servidor SMTP de Gmail
            $mail->SMTPAuth = true;                             // Habilitar autenticación SMTP
            $mail->Username = 'stebanbusiness@gmail.com';       // Usuario SMTP
            $mail->Password = 'jywt gyer gujh qsjl';            // Contraseña SMTP (App Password)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Usar TLS
            $mail->Port = 587;                                  // Puerto SMTP para TLS

            // Configurar remitente y destinatario
            $mail->setFrom('stebanbusiness@gmail.com', 'Sistema Excusas Cotecnova');
            $mail->addAddress($datos['correo_director']);

            // Configurar contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Nueva excusa registrada - ' . $datos['nombre_unidad'];
            $mail->Body = "
                <p>Hola Director(a) {$datos['nombre_director']},</p>
                <p>El estudiante <strong>{$datos['nombre_estudiante']}</strong> ha registrado una nueva excusa en la unidad <strong>{$datos['nombre_unidad']}</strong>.</p>
                <p><strong>Fecha de la falta:</strong> {$datos['fecha_falta_excu']}</p>
                <p><strong>Tipo de excusa:</strong> {$datos['tipo_excu']}</p>
                <p>Por favor, ingrese al sistema para aprobar o rechazar esta solicitud.</p>
            ";
            
            // Enviar correo
            $mail->send();
            $mail_sent = true;
            
        } catch (Exception $e) {
            // Capturar y registrar error del envío de correo
            $mail_error = $mail->ErrorInfo ?? $e->getMessage();
            error_log("PHPMailer error: " . $mail_error);
        }
    } else {
        // Registrar que no se encontró correo del director
        error_log("No se encontró correo del director para la excusa id={$id_excusa}");
    }

    // PASO 4: Preparar respuesta final
    if ($mail_sent) {
        // Excusa registrada y correo enviado exitosamente
        echo json_encode(['success' => true, 'mensaje' => 'Excusa registrada correctamente.']);
    } else {
        // Excusa registrada pero hubo problema con el correo
        $msg = 'Excusa registrada correctamente.';
        if (!empty($mail_error)) {
            $msg .= ' Pero hubo un error al enviar correo: ' . $mail_error;
        } else {
            $msg .= ' No se envió correo (no se encontró correo del director).';
        }
        echo json_encode(['success' => true, 'mensaje' => $msg]);
    }
    
} catch (PDOException $e) {
    // Error en la base de datos
    echo json_encode(['success' => false, 'mensaje' => 'Error al registrar la excusa: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Error general inesperado
    echo json_encode(['success' => false, 'mensaje' => 'Error inesperado: ' . $e->getMessage()]);
}
