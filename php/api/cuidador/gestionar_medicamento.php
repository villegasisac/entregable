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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? ''; // 'add', 'edit', 'delete', 'get'
$paciente_id = $data['paciente_id'] ?? null;

// Verificar que el cuidador esté asociado al paciente
if (!empty($paciente_id)) {
    $sql_check_association = "SELECT id FROM relaciones_paciente_cuidador 
                              WHERE paciente_id = ? AND cuidador_id = ?";
    $stmt_check_association = $conn->prepare($sql_check_association);
    $stmt_check_association->bind_param("ii", $paciente_id, $cuidador_id);
    $stmt_check_association->execute();
    $result_association = $stmt_check_association->get_result();
    if ($result_association->num_rows === 0) {
        $response['message'] = 'No tienes permiso para gestionar medicamentos de este paciente.';
        echo json_encode($response);
        exit();
    }
    $stmt_check_association->close();
} else if ($action !== 'get') { // 'get' puede no requerir paciente_id si lista todos los meds del cuidador
    $response['message'] = 'ID de paciente no proporcionado.';
    echo json_encode($response);
    exit();
}


switch ($action) {
    case 'add':
        $nombre_medicamento = trim($data['nombre_medicamento'] ?? '');
        $dosis = trim($data['dosis'] ?? '');
        $instrucciones = trim($data['instrucciones'] ?? '');
        $horarios = $data['horarios'] ?? [];

        if (empty($nombre_medicamento) || empty($paciente_id) || empty($horarios)) {
            $response['message'] = 'Datos incompletos para agregar medicamento.';
            break;
        }

        $conn->begin_transaction();
        try {
            $sql_med = "INSERT INTO medicamentos (paciente_id, nombre_medicamento, dosis, instrucciones, creado_por_cuidador_id) VALUES (?, ?, ?, ?, ?)";
            $stmt_med = $conn->prepare($sql_med);
            $stmt_med->bind_param("isssi", $paciente_id, $nombre_medicamento, $dosis, $instrucciones, $cuidador_id);
            $stmt_med->execute();
            $medicamento_id = $conn->insert_id;

            foreach ($horarios as $hora) {
                $sql_horario = "INSERT INTO horarios (medicamento_id, hora_toma) VALUES (?, ?)";
                $stmt_horario = $conn->prepare($sql_horario);
                $stmt_horario->bind_param("is", $medicamento_id, $hora);
                $stmt_horario->execute();
                $stmt_horario->close();
            }

            $conn->commit();
            $response['status'] = 'success';
            $response['message'] = 'Medicamento y horarios agregados con éxito.';

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $response['message'] = 'Error al agregar medicamento: ' . $exception->getMessage();
        }
        if (isset($stmt_med)) $stmt_med->close();
        break;

    case 'edit':
        $medicamento_id = $data['medicamento_id'] ?? null;
        $nombre_medicamento = trim($data['nombre_medicamento'] ?? '');
        $dosis = trim($data['dosis'] ?? '');
        $instrucciones = trim($data['instrucciones'] ?? '');
        $horarios = $data['horarios'] ?? [];

        if (empty($medicamento_id) || empty($nombre_medicamento) || empty($paciente_id) || empty($horarios)) {
            $response['message'] = 'Datos incompletos para editar medicamento.';
            break;
        }

        // Verificar que el medicamento pertenezca al paciente y haya sido creado por este cuidador (o que tenga permiso)
        $sql_check_med = "SELECT id FROM medicamentos WHERE id = ? AND paciente_id = ?";
        $stmt_check_med = $conn->prepare($sql_check_med);
        $stmt_check_med->bind_param("ii", $medicamento_id, $paciente_id);
        $stmt_check_med->execute();
        if ($stmt_check_med->get_result()->num_rows === 0) {
            $response['message'] = 'Medicamento no encontrado o no pertenece a este paciente.';
            $stmt_check_med->close();
            break;
        }
        $stmt_check_med->close();

        $conn->begin_transaction();
        try {
            // Actualizar medicamento
            $sql_update_med = "UPDATE medicamentos SET nombre_medicamento = ?, dosis = ?, instrucciones = ? WHERE id = ?";
            $stmt_update_med = $conn->prepare($sql_update_med);
            $stmt_update_med->bind_param("sssi", $nombre_medicamento, $dosis, $instrucciones, $medicamento_id);
            $stmt_update_med->execute();
            $stmt_update_med->close();

            // Eliminar horarios existentes y reinsertar los nuevos
            $sql_delete_horarios = "DELETE FROM horarios WHERE medicamento_id = ?";
            $stmt_delete_horarios = $conn->prepare($sql_delete_horarios);
            $stmt_delete_horarios->bind_param("i", $medicamento_id);
            $stmt_delete_horarios->execute();
            $stmt_delete_horarios->close();

            foreach ($horarios as $hora) {
                $sql_insert_horario = "INSERT INTO horarios (medicamento_id, hora_toma) VALUES (?, ?)";
                $stmt_insert_horario = $conn->prepare($sql_insert_horario);
                $stmt_insert_horario->bind_param("is", $medicamento_id, $hora);
                $stmt_insert_horario->execute();
                $stmt_insert_horario->close();
            }

            $conn->commit();
            $response['status'] = 'success';
            $response['message'] = 'Medicamento y horarios actualizados con éxito.';

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $response['message'] = 'Error al actualizar medicamento: ' . $exception->getMessage();
        }
        break;

    case 'delete':
        $medicamento_id = $data['medicamento_id'] ?? null;

        if (empty($medicamento_id) || empty($paciente_id)) {
            $response['message'] = 'ID de medicamento o paciente no proporcionado para eliminar.';
            break;
        }

        // Verificar que el medicamento pertenezca al paciente y haya sido creado por este cuidador (o que tenga permiso)
        $sql_check_med = "SELECT id FROM medicamentos WHERE id = ? AND paciente_id = ?";
        $stmt_check_med = $conn->prepare($sql_check_med);
        $stmt_check_med->bind_param("ii", $medicamento_id, $paciente_id);
        $stmt_check_med->execute();
        if ($stmt_check_med->get_result()->num_rows === 0) {
            $response['message'] = 'Medicamento no encontrado o no pertenece a este paciente.';
            $stmt_check_med->close();
            break;
        }
        $stmt_check_med->close();
        
        $conn->begin_transaction();
        try {
            // Primero eliminar horarios y luego el medicamento
            $sql_delete_horarios = "DELETE FROM horarios WHERE medicamento_id = ?";
            $stmt_delete_horarios = $conn->prepare($sql_delete_horarios);
            $stmt_delete_horarios->bind_param("i", $medicamento_id);
            $stmt_delete_horarios->execute();
            $stmt_delete_horarios->close();

            $sql_delete_med = "DELETE FROM medicamentos WHERE id = ?";
            $stmt_delete_med = $conn->prepare($sql_delete_med);
            $stmt_delete_med->bind_param("i", $medicamento_id);
            $stmt_delete_med->execute();
            $stmt_delete_med->close();

            $conn->commit();
            $response['status'] = 'success';
            $response['message'] = 'Medicamento eliminado con éxito.';
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $response['message'] = 'Error al eliminar medicamento: ' . $exception->getMessage();
        }
        break;

    case 'get': // Obtener medicamentos y horarios para un paciente específico
        if (empty($paciente_id)) {
            $response['message'] = 'ID de paciente no proporcionado para obtener medicamentos.';
            break;
        }

        $sql_get_meds = "SELECT 
                            m.id AS medicamento_id,
                            m.nombre_medicamento,
                            m.dosis,
                            m.instrucciones,
                            GROUP_CONCAT(h.hora_toma ORDER BY h.hora_toma ASC) AS horarios_toma
                        FROM 
                            medicamentos m
                        LEFT JOIN 
                            horarios h ON m.id = h.medicamento_id
                        WHERE 
                            m.paciente_id = ?
                        GROUP BY 
                            m.id
                        ORDER BY 
                            m.nombre_medicamento ASC";
        $stmt_get_meds = $conn->prepare($sql_get_meds);
        $stmt_get_meds->bind_param("i", $paciente_id);
        $stmt_get_meds->execute();
        $result_get_meds = $stmt_get_meds->get_result();

        $medicamentos_data = [];
        while ($row = $result_get_meds->fetch_assoc()) {
            if (!empty($row['horarios_toma'])) {
                $row['horarios_toma'] = explode(',', $row['horarios_toma']);
            } else {
                $row['horarios_toma'] = [];
            }
            $medicamentos_data[] = $row;
        }
        $stmt_get_meds->close();
        $response['status'] = 'success';
        $response['data'] = $medicamentos_data;
        break;

    default:
        $response['message'] = 'Acción no válida.';
        break;
}

$conn->close();
echo json_encode($response);
?>