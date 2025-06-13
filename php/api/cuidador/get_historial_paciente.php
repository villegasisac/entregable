<?php

require_once '../../config/db.php';
require_once '../../auth/verificar_sesion.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => ''];

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cuidador') {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

$cuidador_usuario_id = $_SESSION['usuario_id'];

// Obtener el ID del cuidador
$sql_cuidador_id = "SELECT id FROM cuidadores WHERE usuario_id = ?";
$stmt_cuidador_id = $conn->prepare($sql_cuidador_id);
$stmt_cuidador_id->bind_param("i", $cuidador_usuario_id);
$stmt_cuidador_id->execute();
$result_cuidador_id = $stmt_cuidador_id->get_result();

if ($result_cuidador_id->num_rows === 0) {
    $response['message'] = 'No se encontró el perfil de cuidador.';
    echo json_encode($response);
    exit();
}
$cuidador_data = $result_cuidador_id->fetch_assoc();
$cuidador_id = $cuidador_data['id'];
$stmt_cuidador_id->close();


if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $paciente_id = $_GET['paciente_id'] ?? null;

    if (empty($paciente_id)) {
        $response['message'] = 'ID de paciente no proporcionado.';
        echo json_encode($response);
        exit();
    }

    // Verificar que el cuidador esté asociado a este paciente
    $sql_check_association = "SELECT id FROM relaciones_paciente_cuidador 
                              WHERE paciente_id = ? AND cuidador_id = ?";
    $stmt_check_association = $conn->prepare($sql_check_association);
    $stmt_check_association->bind_param("ii", $paciente_id, $cuidador_id);
    $stmt_check_association->execute();
    if ($stmt_check_association->get_result()->num_rows === 0) {
        $response['message'] = 'No tienes permiso para ver el historial de este paciente.';
        echo json_encode($response);
        exit();
    }
    $stmt_check_association->close();


    // Consulta para obtener el historial de tomas de un paciente específico
    $sql = "SELECT 
                ht.fecha_hora_programada,
                ht.fecha_hora_confirmacion,
                ht.estado,
                ht.confirmado_por,
                m.nombre_medicamento,
                m.dosis,
                h.hora_toma
            FROM 
                historial_tomas ht
            JOIN 
                horarios h ON ht.horario_id = h.id
            JOIN 
                medicamentos m ON h.medicamento_id = m.id
            WHERE 
                ht.paciente_id = ?
            ORDER BY 
                ht.fecha_hora_programada DESC
            LIMIT 50"; // Limitar a las últimas 50 tomas para el historial

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $historial = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $historial[] = $row;
        }
        $response['status'] = 'success';
        $response['data'] = $historial;
    } else {
        $response['status'] = 'success';
        $response['message'] = 'No hay historial de tomas registrado para este paciente.';
        $response['data'] = [];
    }

    $stmt->close();
    $conn->close();

} else {
    $response['message'] = 'Método no permitido.';
}

echo json_encode($response);
?>