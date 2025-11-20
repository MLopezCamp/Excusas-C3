<?php
/**
 * ARCHIVO DE CONEXIÓN A LA BASE DE DATOS
 * 
 * Este archivo establece la conexión con la base de datos MySQL del sistema de excusas.
 * Utiliza PDO (PHP Data Objects) para una conexión segura y moderna.
 * 
 * Base de datos: v_exc_asig_mat_est (Vista de excusas, asignaturas, matrículas y estudiantes)
 */

// Configuración de conexión a la base de datos
$host = 'db';        // Servidor de base de datos (local)
$dbname = 'v_exc_asig_mat_est';  // Nombre de la base de datos
$username = 'root';         // Usuario de la base de datos
$password = 'root_pass';             // Contraseña de la base de datos 

try {
    // Crear nueva instancia de PDO con configuración de conexión
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Configurar PDO para lanzar excepciones en caso de errores
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // En caso de error de conexión, terminar la ejecución y mostrar el error
    die("Error de conexión: " . $e->getMessage());
}
?>
