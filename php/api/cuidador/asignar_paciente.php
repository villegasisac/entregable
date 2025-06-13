<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents('php://input'), true);
    $paciente_email = trim($data['paciente_email'] ?? '');

    if (empty($paciente_email) || !filter_var($paciente_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Correo electrónico de paciente inválido.';
        echo json_encode($response);
        exit();
    }

    $sql_paciente = "SELECT p.id AS paciente_id, u.id AS usuario_id_paciente, u.tipo_usuario
                     FROM pacientes p
                     JOIN usuarios u ON p.usuario_id = u.id
                     WHERE u.email = ?";
    $stmt_paciente = $conn->prepare($sql_paciente);
    $stmt_paciente->bind_param("s", $paciente_email);
    $stmt_paciente->execute();
    $result_paciente = $stmt_paciente->get_result();

    if ($result_paciente->num_rows === 0) {
        $response['message'] = 'Paciente con ese correo electrónico no encontrado.';
        echo json_encode($response);
        exit();
    }
    $paciente_info = $result_paciente->fetch_assoc();
    $paciente_id = $paciente_info['paciente_id'];
    $stmt_paciente->close();

    $sql_check_relation = "SELECT id FROM relaciones_paciente_cuidador 
                           WHERE paciente_id = ? AND cuidador_id = ?";
    $stmt_check_relation = $conn->prepare($sql_check_relation);
    $stmt_check_relation->bind_param("ii", $paciente_id, $cuidador_id);
    $stmt_check_relation->execute();
    $result_check_relation = $stmt_check_relation->get_result();

    if ($result_check_relation->num_rows > 0) {
        $response['message'] = 'Esta relación paciente-cuidador ya existe.';
        echo json_encode($response);
        exit();
    }
    $stmt_check_relation->close();

    $sql_insert_relation = "INSERT INTO relaciones_paciente_cuidador (paciente_id, cuidador_id) VALUES (?, ?)";
    $stmt_insert_relation = $conn->prepare($sql_insert_relation);
    $stmt_insert_relation->bind_param("ii", $paciente_id, $cuidador_id);

    if ($stmt_insert_relation->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Paciente asociado con éxito.';
    } else {
        $response['message'] = 'Error al asociar paciente: ' . $stmt_insert_relation->error;
    }

    $stmt_insert_relation->close();
    $conn->close();

} else {
    $response['message'] = 'Método no permitido.';
}

echo json_encode($response);
?>