<?php
// php/api/paciente/get_medicamentos.php (o cualquier otro en esta carpeta)

// RUTA CORREGIDA DEFINITIVA
require_once '../../config/db.php';         // Sube dos niveles a /php/, luego va a /config/
require_once '../../auth/verificar_sesion.php'; // Sube dos niveles a /php/, luego va a /auth/

header('Content-Type: application/json');

// ... el resto de tu código


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
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

// Obtener el ID del paciente real de la tabla `pacientes`
$sql_paciente_id = "SELECT id FROM pacientes WHERE usuario_id = ?";
$stmt_paciente_id = $conn->prepare($sql_paciente_id);
if (!$stmt_paciente_id) {
    $response['message'] = 'Error interno al preparar la consulta de paciente: ' . $conn->error;
    echo json_encode($response);
    exit();
}
$stmt_paciente_id->bind_param("i", $paciente_usuario_id);
$stmt_paciente_id->execute();
$result_paciente_id = $stmt_paciente_id->get_result();

if ($result_paciente_id->num_rows === 0) {
    $response['message'] = 'No se encontró el perfil de paciente asociado.';
    echo json_encode($response);
    exit();
}
$paciente_data = $result_paciente_id->fetch_assoc();
$paciente_id = $paciente_data['id'];
$stmt_paciente_id->close();


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents('php://input'), true);

    $toma_id = $data['toma_id'] ?? null;
    $estado = $data['estado'] ?? null;
    $confirmado_por = 'paciente';

    if (!$toma_id || !$estado) {
        $response['message'] = 'Faltan datos para confirmar la toma (ID de toma o estado).';
        echo json_encode($response);
        exit();
    }

    if (!in_array($estado, ['tomado', 'no_tomado'])) {
        $response['message'] = 'Estado de toma inválido.';
        echo json_encode($response);
        exit();
    }

    try {
        // Verificar que la toma a actualizar pertenezca al paciente logueado
        $sql_check_ownership = "SELECT id FROM historial_tomas WHERE id = ? AND paciente_id = ?";
        $stmt_check_ownership = $conn->prepare($sql_check_ownership);
        if (!$stmt_check_ownership) {
            throw new Exception("Error al preparar la verificación de propiedad de toma: " . $conn->error);
        }
        $stmt_check_ownership->bind_param("ii", $toma_id, $paciente_id);
        $stmt_check_ownership->execute();
        $result_ownership = $stmt_check_ownership->get_result();

        if ($result_ownership->num_rows === 0) {
            throw new Exception("La toma de medicamento no existe o no pertenece a este paciente.");
        }
        $stmt_check_ownership->close();

        // Actualizar el estado de la toma en historial_tomas
        date_default_timezone_set('America/Lima'); // Asegura la zona horaria para NOW()
        $sql_update = "UPDATE historial_tomas
                       SET estado = ?, fecha_hora_confirmacion = NOW(), confirmado_por = ?
                       WHERE id = ? AND estado = 'pendiente'"; // Solo actualiza si está pendiente
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception("Error al preparar la actualización de toma: " . $conn->error);
        }
        $stmt_update->bind_param("ssi", $estado, $confirmado_por, $toma_id);

        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                $response['status'] = 'success';
                $response['message'] = 'Registro recibido. ';
            } else {
                $response['message'] = 'La toma ya había sido confirmada o no existe como pendiente.';
                $response['status'] = 'info'; // Usar 'info' para indicar que no es un error, pero tampoco una acción de actualización
            }
        } else {
            throw new Exception("Error al actualizar el estado de la toma: " . $stmt_update->error);
        }
        $stmt_update->close();

    } catch (Exception $e) {
        $response['message'] = 'Error en el servidor: ' . $e->getMessage();
        error_log('Error en confirmar_toma.php: ' . $e->getMessage());
    }

} else {
    $response['message'] = 'Método no permitido.';
}

$conn->close();
echo json_encode($response);
?>