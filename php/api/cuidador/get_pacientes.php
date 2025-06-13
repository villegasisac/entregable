<?php
// php/api/cuidador/get_pacientes.php


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

$stmt_cuidador_id = null;
$stmt_pacientes = null;

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cuidador') {
    $response['message'] = 'Acceso denegado. Se requiere autenticación de cuidador.';
    echo json_encode($response);
    exit();
}

$cuidador_usuario_id = $_SESSION['usuario_id'];

try {
    $sql_cuidador_id = "SELECT id FROM cuidadores WHERE usuario_id = ?";
    $stmt_cuidador_id = $conn->prepare($sql_cuidador_id);
    if (!$stmt_cuidador_id) {
        throw new Exception("Error al preparar la consulta de cuidador: " . $conn->error);
    }
    $stmt_cuidador_id->bind_param("i", $cuidador_usuario_id);
    $stmt_cuidador_id->execute();
    
    $result_cuidador_id = $stmt_cuidador_id->get_result();

    if ($result_cuidador_id->num_rows === 0) {
        $response['message'] = 'No se encontró el perfil de cuidador asociado.';
    } else {
        $cuidador_data = $result_cuidador_id->fetch_assoc();
        $cuidador_id = $cuidador_data['id'];

        $result_cuidador_id->free(); // Libera la memoria del resultado anterior

        // --- CAMBIO CLAVE AQUÍ: Añadir u.apellido como paciente_apellido ---
        $sql_pacientes = "SELECT
                                p.id AS paciente_id,
                                u.nombre AS paciente_nombre,
                                u.apellido AS paciente_apellido,  -- ¡¡¡Añadido este campo!!!
                                u.email AS paciente_email
                            FROM
                                pacientes p
                            JOIN
                                usuarios u ON p.usuario_id = u.id
                            JOIN
                                relaciones_paciente_cuidador rpc ON p.id = rpc.paciente_id
                            WHERE
                                rpc.cuidador_id = ?";

        $stmt_pacientes = $conn->prepare($sql_pacientes);
        if (!$stmt_pacientes) {
            throw new Exception("Error al preparar la consulta de pacientes: " . $conn->error);
        }
        $stmt_pacientes->bind_param("i", $cuidador_id);
        $stmt_pacientes->execute();
        $result_pacientes = $stmt_pacientes->get_result();

        $pacientes = [];
        if ($result_pacientes->num_rows > 0) {
            while ($row = $result_pacientes->fetch_assoc()) {
                $pacientes[] = $row;
            }
            $response['status'] = 'success';
            $response['data'] = $pacientes;
            $response['message'] = 'Lista de pacientes cargada con éxito.';
        } else {
            $response['status'] = 'success';
            $response['message'] = 'No hay pacientes asociados a este cuidador.';
            $response['data'] = [];
        }
    }

} catch (Exception $e) {
    $response['message'] = 'Error en el servidor: ' . $e->getMessage();
    error_log('Error en get_pacientes.php: ' . $e->getMessage());
} finally {
    if ($stmt_cuidador_id && $stmt_cuidador_id instanceof mysqli_stmt) {
        try {
            $stmt_cuidador_id->close();
        } catch (Throwable $t) {
            error_log("Error al cerrar stmt_cuidador_id en get_pacientes.php: " . $t->getMessage());
        }
    }
    if ($stmt_pacientes && $stmt_pacientes instanceof mysqli_stmt) {
        try {
            $stmt_pacientes->close();
        } catch (Throwable $t) {
            error_log("Error al cerrar stmt_pacientes en get_pacientes.php: " . $t->getMessage());
        }
    }
    if ($conn && $conn instanceof mysqli && $conn->ping()) {
        try {
            $conn->close();
        } catch (Throwable $t) {
            error_log("Error al cerrar la conexión a la BD en get_pacientes.php: " . $t->getMessage());
        }
    }
}

echo json_encode($response);
?>