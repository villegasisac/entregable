<?php
// php/api/paciente/get_historial.php


require_once '../../config/db.php';
require_once '../../auth/verificar_sesion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Max-Age: 86400");
    exit(0);
}

header("Access-Control-Allow-Origin: *");

$response = ['status' => 'error', 'message' => ''];

// Inicializa las variables de sentencia a null para asegurar que se cierren correctamente en finally
$stmt_paciente_id = null;
$stmt_historial = null;

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
        throw new Exception("Error al preparar la consulta de paciente: " . $conn->error);
    }
    $stmt_paciente_id->bind_param("i", $paciente_usuario_id);
    $stmt_paciente_id->execute();
    $result_paciente_id = $stmt_paciente_id->get_result();

    if ($result_paciente_id->num_rows === 0) {
        $response['message'] = 'No se encontró el perfil de paciente asociado.';
        // Aquí no se usa exit() directamente para que el bloque finally se ejecute.
        // La respuesta ya está establecida y se enviará al final del script.
    } else {
        $paciente_data = $result_paciente_id->fetch_assoc();
        $paciente_id = $paciente_data['id'];

        // NO cerrar $stmt_paciente_id aquí. Se cierra en el bloque finally.

        // Obtener el historial de tomas para el paciente logueado
        $sql_historial = "SELECT
                                ht.fecha_hora_programada,
                                ht.fecha_hora_confirmacion,
                                ht.estado,
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
                                ht.fecha_hora_programada DESC, h.hora_toma DESC
                            LIMIT 50";

        $stmt_historial = $conn->prepare($sql_historial);
        if (!$stmt_historial) {
            throw new Exception("Error al preparar la consulta de historial: " . $conn->error);
        }
        $stmt_historial->bind_param("i", $paciente_id);
        $stmt_historial->execute();
        $result_historial = $stmt_historial->get_result();

        $historial = [];
        if ($result_historial->num_rows > 0) {
            while ($row = $result_historial->fetch_assoc()) {
                $historial[] = $row;
            }
            $response['status'] = 'success';
            $response['data'] = $historial;
            $response['message'] = 'Historial de tomas cargado con éxito.';
        } else {
            $response['status'] = 'success';
            $response['message'] = 'No hay historial de tomas disponible.';
            $response['data'] = [];
        }
        // NO cerrar $stmt_historial aquí. Se cierra en el bloque finally.
    }

} catch (Exception $e) {
    $response['message'] = 'Error en el servidor: ' . $e->getMessage();
    error_log('Error en get_historial.php: ' . $e->getMessage());
} finally {
    // Cerrar las sentencias preparadas si fueron inicializadas
    if ($stmt_paciente_id && $stmt_paciente_id instanceof mysqli_stmt) {
        try {
            $stmt_paciente_id->close();
        } catch (Throwable $t) {
            // Capturar y registrar si falla el cierre, pero no detener el script
            error_log("Error al cerrar stmt_paciente_id en get_historial.php: " . $t->getMessage());
        }
    }
    if ($stmt_historial && $stmt_historial instanceof mysqli_stmt) {
        try {
            $stmt_historial->close();
        } catch (Throwable $t) {
            error_log("Error al cerrar stmt_historial en get_historial.php: " . $t->getMessage());
        }
    }
    // Cerrar la conexión a la base de datos si está abierta y es válida
    if ($conn && $conn instanceof mysqli && $conn->ping()) { // ping() verifica si la conexión está activa
        try {
            $conn->close();
        } catch (Throwable $t) {
            error_log("Error al cerrar la conexión a la BD en get_historial.php: " . $t->getMessage());
        }
    }
}

echo json_encode($response);
?>