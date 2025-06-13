<?php
// php/api/paciente/get_medicamentos.php (o cualquier otro en esta carpeta)


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

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'paciente') {
    $response['message'] = 'Acceso denegado. Se requiere autenticación de paciente.';
    echo json_encode($response);
    exit();
}

$paciente_usuario_id = $_SESSION['usuario_id'];

try {
    // Obtener el ID del paciente desde la tabla 'pacientes'
    $sql_paciente_id = "SELECT id FROM pacientes WHERE usuario_id = ?";
    $stmt_paciente_id = $conn->prepare($sql_paciente_id);
    if (!$stmt_paciente_id) {
        throw new Exception("Error al preparar la consulta para obtener paciente_id: " . $conn->error);
    }
    $stmt_paciente_id->bind_param("i", $paciente_usuario_id);
    $stmt_paciente_id->execute();
    $result_paciente_id = $stmt_paciente_id->get_result();

    if ($result_paciente_id->num_rows === 0) {
        $response['message'] = 'No se encontró el perfil de paciente asociado a este usuario.';
        echo json_encode($response);
        exit();
    }
    $paciente_data = $result_paciente_id->fetch_assoc();
    $paciente_id = $paciente_data['id'];
    $stmt_paciente_id->close();

    // Obtener los medicamentos y sus horarios para el paciente
    // Usamos h.id AS horario_id para la clave foránea en historial_tomas
    $sql_medicamentos = "SELECT
                                m.id AS medicamento_id,
                                m.nombre_medicamento,
                                m.dosis,
                                m.instrucciones,
                                h.id AS horario_id,
                                h.hora_toma
                            FROM
                                medicamentos m
                            JOIN
                                horarios h ON m.id = h.medicamento_id
                            WHERE
                                m.paciente_id = ?
                            ORDER BY
                                h.hora_toma ASC";

    $stmt_medicamentos = $conn->prepare($sql_medicamentos);
    if (!$stmt_medicamentos) {
        throw new Exception("Error al preparar la consulta de medicamentos: " . $conn->error);
    }
    $stmt_medicamentos->bind_param("i", $paciente_id);
    $stmt_medicamentos->execute();
    $result_medicamentos = $stmt_medicamentos->get_result();

    $medicamentos_programados = [];
    // Define la zona horaria a 'America/Lima' para asegurar consistencia
    date_default_timezone_set('America/Lima');
    $hoy = date('Y-m-d'); // Fecha actual para las tomas

    // Iniciar transacción para la inserción de tomas pendientes
    $conn->begin_transaction();

    while ($row = $result_medicamentos->fetch_assoc()) {
        $medicamento_id = $row['medicamento_id'];
        $horario_id = $row['horario_id']; // ID del horario, necesario para historial_tomas
        $hora_toma = $row['hora_toma']; // Formato HH:MM:SS
        $fecha_hora_programada = $hoy . ' ' . $hora_toma;

        // Verificar si ya existe un registro en historial_tomas para este horario, paciente y día
        $sql_check_toma = "SELECT id, estado FROM historial_tomas
                           WHERE paciente_id = ? AND horario_id = ?
                           AND DATE(fecha_hora_programada) = ?";
        $stmt_check_toma = $conn->prepare($sql_check_toma);
        if (!$stmt_check_toma) {
            throw new Exception("Error al preparar la verificación de toma: " . $conn->error);
        }
        $stmt_check_toma->bind_param("iis", $paciente_id, $horario_id, $hoy);
        $stmt_check_toma->execute();
        $result_check_toma = $stmt_check_toma->get_result();

        if ($result_check_toma->num_rows > 0) {
            // Ya existe un registro, obtener su ID y estado
            $toma_existente = $result_check_toma->fetch_assoc();
            $row['toma_id'] = $toma_existente['id']; // Este es el ID de historial_tomas
            $row['estado_toma'] = $toma_existente['estado'];
        } else {
            // No existe, crear un nuevo registro en historial_tomas como 'pendiente'
            $sql_insert_toma = "INSERT INTO historial_tomas (horario_id, paciente_id, fecha_hora_programada, estado)
                                VALUES (?, ?, ?, 'pendiente')";
            $stmt_insert_toma = $conn->prepare($sql_insert_toma);
            if (!$stmt_insert_toma) {
                throw new Exception("Error al preparar la inserción de toma: " . $conn->error);
            }
            $stmt_insert_toma->bind_param("iis", $horario_id, $paciente_id, $fecha_hora_programada);
            $stmt_insert_toma->execute();

            $row['toma_id'] = $conn->insert_id; // Obtener el ID de la nueva toma en historial_tomas
            $row['estado_toma'] = 'pendiente';
            $stmt_insert_toma->close();
        }
        $stmt_check_toma->close();
        $medicamentos_programados[] = $row;
    }

    $conn->commit(); // Confirmar la transacción
    $response['status'] = 'success';
    $response['data'] = $medicamentos_programados;
    $response['message'] = 'Recordatorios de medicamentos cargados y tomas pendientes generadas.';

} catch (Exception $e) {
    $conn->rollback(); // Revertir la transacción en caso de error
    $response['message'] = 'Error en el servidor: ' . $e->getMessage();
    error_log('Error en get_medicamentos.php: ' . $e->getMessage());
} finally {
    if (isset($stmt_medicamentos) && $stmt_medicamentos) $stmt_medicamentos->close();
    $conn->close();
}

echo json_encode($response);
?>