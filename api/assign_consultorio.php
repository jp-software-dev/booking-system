<?php
// Endpoint transaccional que permite asignar un médico o liberar el consultorio dejándolo vacío
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

$id_consultorio = $data['id_consultorio'] ?? null;
$id_doctor = $data['id_doctor'] ?? '';

if (!$id_consultorio) {
    echo json_encode(['status' => 'error', 'message' => 'Consultorio no especificado']);
    exit;
}

try {
    $db = Database::getInstance();
    $db->beginTransaction();
    $stmt1 = $db->prepare("UPDATE doctores SET id_consultorio = NULL WHERE id_consultorio = ?");
    $stmt1->execute([$id_consultorio]);
    if (!empty($id_doctor)) {
        $stmt2 = $db->prepare("UPDATE doctores SET id_consultorio = ? WHERE id_doctor = ?");
        $stmt2->execute([$id_consultorio, $id_doctor]);
    }
    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Operación realizada con éxito']);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar la asignación']);
}