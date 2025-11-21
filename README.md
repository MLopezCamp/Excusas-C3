# Sistema de Gestión de Excusas – COTECNOVA

## Descripción General

El Sistema de Gestión de Excusas de COTECNOVA es un módulo web desarrollado en PHP que permite a estudiantes registrar excusas por inasistencias y a docentes/directores de unidad gestionar y validar estas solicitudes. El sistema implementa un flujo completo desde el registro de la excusa hasta su aprobación o rechazo.

## Arquitectura del Sistema

### Tecnologías Utilizadas

- Backend: PHP 7.4+
- Base de Datos: MySQL 8
- Frontend: HTML5, CSS3, JavaScript (ES6+)
- Framework CSS: Bootstrap 5.3.2
- Almacenamiento de Archivos: Dropbox API (Kunnalvarma)
- Correo: PHPMailer
- Autenticación: Sesiones PHP
- Orquestación: Docker Compose

## Estructura de Directorios
´´´
ExcusasC3/
├── Dockerfile
├── docker-compose.yml
├── v_exc_asig_mat_est.sql
├── php/
│   ├── registrar_excusa_estudiante.php
│   ├── registrar_excusa_docente.php
│   ├── actualizar_estado_excusa.php
│   ├── uploadFiles.php
│   ├── login_estudiante_api.php
│   ├── login_docente_api.php
│   └── Terceros/
│       └── Dropbox SDK
├── CSS/
│   ├── estudiante/
│   └── ExcusasCotecnova/
├── Images/
└── Modules/
    ├── Estudiantes/
    └── ExcusasCotecnova/
   
´´´

## Despliegue con Docker

### Servicios definidos en docker-compose.yml

1. Web (PHP + Apache)
- Construido desde Dockerfile
- Puerto expuesto: 8090
- Código montado en /var/www/html
- Conexión directa al servicio de base de datos

2. Base de Datos (MySQL 8)
- Puerto 3306 (interno)
- Usuario/contraseña: root / root_pass
- Inicialización automática desde v_exc_asig_mat_est.sql
- Volumen persistente: db_data

3. phpMyAdmin
URL: http://localhost:8091
Credenciales: root / root_pass

## Cómo levantar el proyecto

Ejecutar desde la raíz del proyecto:

docker-compose up -d --build

Servicios disponibles:
- Aplicación Web: http://localhost:8090
- phpMyAdmin: http://localhost:8091
- Base de datos: db:3306 (solo interno)

## Credenciales de Ejemplo

MySQL / phpMyAdmin:
- Usuario: root
- Contraseña: root_pass
- Base de datos: v_exc_asig_mat_est

## Funcionamiento del Sistema

### Módulo Estudiantes
- Inicio de sesión
- Visualización de cursos inscritos
- Registro de excusas con:
  - Selección de curso
  - Tipo de excusa
  - Motivo
  - Fecha de la falta
  - Subida de archivo a Dropbox
- Notificación por correo al director de unidad

### Módulo Docentes y Administrativos
- Gestión de cursos asignados
- Registro de excusas para estudiantes
- Validación de excusas
- Aprobación/rechazo con comentarios

## Base de Datos

Tablas principales:
- estudiantes
- empleados
- excusas
- t_v_exc_asig_mat_est
- tiposexcusas
- unidades

Relaciones clave:
- Excusa ↔ Estudiante ↔ Curso ↔ Estado
- Estudiante ↔ Unidad ↔ Director de Unidad
- Docente ↔ Cursos asignados

## Seguridad

- Validación estricta de sesiones por rol
- Validación de archivos
- Consultas PDO preparadas
- Manejo seguro de tokens de Dropbox

## APIs Disponibles

Archivo                          | Descripción
--------------------------------|------------------------------------------
login_estudiante_api.php       | Autenticación estudiantes
login_docente_api.php          | Autenticación docentes/administrativos
registrar_excusa_estudiante.php | Registro de excusas por estudiante
registrar_excusa_docente.php    | Registro por administrativos
actualizar_estado_excusa.php    | Aprobación o rechazo
uploadFiles.php                 | Subida de archivos a Dropbox
obtener_cursos_estudiantes.php  | Cursos del estudiante
obtener_cursos_docentes.php     | Cursos del docente

## Mantenimiento en Docker

Ver logs aplicación web:
docker logs excusas_web

Ver logs BD:
docker logs excusas_db

Reiniciar todo:
docker-compose down
docker-compose up -d --build

Acceder a MySQL dentro del contenedor:
docker exec -it excusas_db mysql -u root -p

## Backups

Generar backup:
docker exec excusas_db mysqldump -u root -proot_pass v_exc_asig_mat_est > backup.sql

Restaurar backup:
docker exec -i excusas_db mysql -u root -proot_pass v_exc_asig_mat_est < backup.sql

## Problemas Comunes

1. PHP no encuentra archivos montados  
   Solución: verificar volumen:
   - .:/var/www/html

2. Error “Database connection failed”
   Verificar que $DB_HOST dentro del contenedor web sea: db

3. phpMyAdmin no conecta
   Verificar variables:
   PMA_HOST=db  
   PMA_USER=root  
   PMA_PASSWORD=root_pass

## Derechos

Desarrollado para la Corporación de Estudios Tecnológicos del Norte del Valle (COTECNOVA).
