<?php
/**
 * REGISTRO DE EXCUSAS DE ESTUDIANTES
 * 
 * Este archivo maneja el registro de excusas enviadas por los estudiantes.
 * Sube el soporte a Dropbox, guarda la información en la BD
 * y notifica por correo al estudiante o director según corresponda.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // PHPMailer y Kunnu Dropbox SDK

// ✅ Configuración de errores — oculta en producción
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// ✅ Cabecera JSON
header('Content-Type: application/json; charset=utf-8');

// ✅ Incluir conexión a la base de datos
include_once './conexion.php';

// ✅ Iniciar sesión
session_start();

// ✅ Limpiar buffers previos solo si existen
if (ob_get_length()) {
    ob_clean();
}

// ----------------------------------------------------------
// 1️⃣ VALIDAR SESIÓN Y DATOS
// ----------------------------------------------------------
if (!isset($_SESSION['estudiante_id'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Sesión no válida. Por favor, inicia sesión nuevamente.'
    ]);
    exit;
}

$id_estudiante = $_SESSION['estudiante_id'];

// Validar datos del formulario
$curso = $_POST['curso'] ?? null;
$fecha_falta = $_POST['fecha_falta'] ?? null;
$descripcion = $_POST['descripcion'] ?? null;
$tipo_excusa = $_POST['tipo_excusa'] ?? null;
$otro_tipo = $_POST['otro_tipo'] ?? null;

if (!$curso || !$fecha_falta || !$descripcion || !$tipo_excusa) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Faltan campos obligatorios.'
    ]);
    exit;
}

// ----------------------------------------------------------
// 2️⃣ SUBIDA DE ARCHIVO A DROPBOX
// ----------------------------------------------------------
try {
    if (isset($_FILES['soporte']) && $_FILES['soporte']['error'] === UPLOAD_ERR_OK) {

        $dropboxKeyFile = __DIR__ . '/../drp_app_info.json';
        $appInfo = json_decode(file_get_contents($dropboxKeyFile), true);

        $app = new Kunnu\Dropbox\DropboxApp(
            $appInfo['dropboxKey'],
            $appInfo['dropboxSecret'],
            $appInfo['dropboxToken']
        );

        $dropbox = new Kunnu\Dropbox\Dropbox($app);

        $fileTempPath = $_FILES['soporte']['tmp_name'];
        $fileName = basename($_FILES['soporte']['name']);
        $dropboxPath = '/excusas/' . $fileName;

        // Subir archivo
        $uploadedFile = $dropbox->simpleUpload($fileTempPath, $dropboxPath, ['autorename' => true]);

        // Crear enlace compartido
        $sharedLink = $dropbox->postToAPI("/sharing/create_shared_link_with_settings", [
            "path" => $uploadedFile->getPathDisplay()
        ]);

        $url = $sharedLink['url'];
        $soporte_excu = str_replace("?dl=0", "?raw=1", $url);

    } else {
        $soporte_excu = null;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al subir el archivo a Dropbox: ' . $e->getMessage()
    ]);
    exit;
}

// ----------------------------------------------------------
// 3️⃣ REGISTRAR EXCUSA EN BD
// ----------------------------------------------------------
try {
    $sql = "INSERT INTO excusas 
        (id_curs_asig_es, fecha_falta_excu, fecha_radicado_excu, soporte_excu, descripcion_excu, tipo_excu, otro_tipo_excu, estado_excu, num_doc_estudiante)
        VALUES (:curso, :fecha_falta, NOW(), :soporte, :descripcion, :tipo, :otro, 1, :num_doc)";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':curso', $curso);
    $stmt->bindParam(':fecha_falta', $fecha_falta);
    $stmt->bindParam(':soporte', $soporte_excu);
    $stmt->bindParam(':descripcion', $descripcion);
    $stmt->bindParam(':tipo', $tipo_excusa);
    $stmt->bindParam(':otro', $otro_tipo);
    $stmt->bindParam(':num_doc', $id_estudiante);

    $stmt->execute();

    // ------------------------------------------------------
    // 4️⃣ NOTIFICAR POR CORREO AL DIRECTOR (opcional)
    // ------------------------------------------------------
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tu_correo@gmail.com'; // 🔁 CAMBIAR
        $mail->Password = 'tu_contraseña';        // 🔁 CAMBIAR o usar App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('tu_correo@gmail.com', 'Sistema de Excusas');
        $mail->addAddress('director@tucolegio.edu'); // 🔁 CAMBIAR destinatario

        $mail->isHTML(true);
        $mail->Subject = 'Nueva excusa registrada';
        $mail->Body = "
            <h3>Excusa registrada por estudiante</h3>
            <p><b>Descripción:</b> {$descripcion}</p>
            <p><b>Fecha de falta:</b> {$fecha_falta}</p>
            <p><b>Curso:</b> {$curso}</p>
        ";

        $mail->send();
    } catch (Exception $e) {
        // No se detiene el flujo si falla el envío
    }

    echo json_encode([
        'success' => true,
        'mensaje' => 'Excusa registrada correctamente.'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al registrar la excusa: ' . $e->getMessage()
    ]);
    exit;
}
