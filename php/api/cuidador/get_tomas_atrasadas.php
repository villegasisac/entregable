<?php



// RUTA CORREGIDA DEFINITIVA
require_once '../../config/db.php';         // Sube dos niveles a /php/, luego va a /config/
require_once '../../auth/verificar_sesion.php'; // Sube dos niveles a /php/, luego va a /auth/

header('Content-Type: application/json');

// ... el resto de tu código


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Max-Age: 86400");
    exit(0);
}

header("Access-Control-Allow-Origin: *");

$response = ['status' => 'error', 'message' => ''];

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cuidador') {
    $response['message'] = 'Acceso denegado. Se requiere autenticación de cuidador.';
    echo json_encode($response);
    exit();
}

$cuidador_usuario_id = $_SESSION['usuario_id'];

try {
    // Obtener el ID del cuidador
    $sql_cuidador_id = "SELECT id FROM cuidadores WHERE usuario_id = ?";
    $stmt_cuidador_id = $conn->prepare($sql_cuidador_id);
    if (!$stmt_cuidador_id) {
        throw new Exception("Error al preparar consulta de cuidador: " . $conn->error);
    }
    $stmt_cuidador_id->bind_param("i", $cuidador_usuario_id);
    $stmt_cuidador_id->execute();
    $result_cuidador_id = $stmt_cuidador_id->get_result();
    if ($result_cuidador_id->num_rows === 0) {
        $response['message'] = 'Perfil de cuidador no encontrado.';
        echo json_encode($response);
        exit();
    }
    $cuidador_data = $result_cuidador_id->fetch_assoc();
    $cuidador_id = $cuidador_data['id'];
    $stmt_cuidador_id->close();

    // Definir la zona horaria para NOW()
    date_default_timezone_set('America/Lima');
    $now = date('Y-m-d H:i:s'); // Hora actual

    // Obtener las tomas "pendientes" que ya pasaron su hora programada
    $sql_tomas_atrasadas = "SELECT
                                ht.id AS toma_id,
                                ht.fecha_hora_programada,
                                ht.estado,
                                p.id AS paciente_id,
                                u_paciente.nombre AS nombre_paciente,
                                u_paciente.apellido AS apellido_paciente,
                                m.nombre_medicamento,
                                m.dosis,
                                h.hora_toma
                            FROM
                                historial_tomas ht
                            JOIN
                                pacientes p ON ht.paciente_id = p.id
                            JOIN
                                usuarios u_paciente ON p.usuario_id = u_paciente.id
                            JOIN
                                horarios h ON ht.horario_id = h.id
                            JOIN
                                medicamentos m ON h.medicamento_id = m.id
                            JOIN
                                relaciones_paciente_cuidador rpc ON p.id = rpc.paciente_id
                            WHERE
                                rpc.cuidador_id = ?
                                AND ht.estado = 'pendiente'
                                AND ht.fecha_hora_programada < ?
                            ORDER BY
                                ht.fecha_hora_programada ASC"; // Las más antiguas primero

    $stmt_tomas_atrasadas = $conn->prepare($sql_tomas_atrasadas);
    if (!$stmt_tomas_atrasadas) {
        throw new Exception("Error al preparar consulta de tomas atrasadas: " . $conn->error);
    }
    $stmt_tomas_atrasadas->bind_param("is", $cuidador_id, $now);
    $stmt_tomas_atrasadas->execute();
    $result_tomas_atrasadas = $stmt_tomas_atrasadas->get_result();

    $tomas_atrasadas = [];
    if ($result_tomas_atrasadas->num_rows > 0) {
        while ($row = $result_tomas_atrasadas->fetch_assoc()) {
            $tomas_atrasadas[] = $row;
        }
        $response['status'] = 'success';
        $response['data'] = $tomas_atrasadas;
        $response['message'] = 'Tomas atrasadas cargadas correctamente.';
    } else {
        $response['status'] = 'success';
        $response['message'] = 'No hay tomas atrasadas pendientes.';
        $response['data'] = [];
    }
    $stmt_tomas_atrasadas->close();

} catch (Exception $e) {
    $response['message'] = 'Error en el servidor: ' . $e->getMessage();
    error_log('Error en get_tomas_atrasadas.php: ' . $e->getMessage());
} finally {
    $conn->close();
}

echo json_encode($response);
?>