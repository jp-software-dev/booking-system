<?php
// ENDPOINT DOCTORES: Archivo API que extrae la lista de médicos activos para llenar dinámicamente los selectores (dropdowns) en los formularios.

// DEPENDENCIAS: Carga la configuración centralizada para establecer la conexión con la base de datos.
require_once '../config/Database.php';

// FORMATO DE RESPUESTA: Define la cabecera HTTP para asegurar que el frontend reciba e interprete los datos como JSON.
header('Content-Type: application/json');

// CONEXIÓN DB: Obtiene la instancia única de la base de datos utilizando el patrón de diseño Singleton.
$db = Database::getInstance();

try {
    // CONSULTA SQL: Selecciona el ID y nombre de los doctores que están activos (estado = 1), ordenados alfabéticamente.
    $query = "SELECT id_doctor, nombre, apellido_paterno 
              FROM doctores 
              WHERE estado = 1 
              ORDER BY nombre ASC";
              
    // EJECUCIÓN: Lanza la consulta preparada directamente hacia el gestor de base de datos.
    $stmt = $db->query($query);
    
    // EXTRACCIÓN DE DATOS: Recupera todos los registros coincidentes y los estructura como un arreglo asociativo de PHP.
    $doctores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // RESPUESTA EXITOSA: Convierte el arreglo de doctores a formato JSON y lo envía al cliente (frontend).
    echo json_encode($doctores);
} catch (Exception $e) {
    // MANEJO DE ERRORES: Captura cualquier fallo, envía un código HTTP 500 (Error de servidor) y devuelve el detalle técnico.
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>