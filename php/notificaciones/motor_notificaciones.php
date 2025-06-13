<?php
if (php_sapi_name() !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    echo "Acceso Denegado. Este script solo puede ser ejecutado desde la línea de comandos.";
    exit();
}

require_once '../config/db.php';

date_default_timezone_set('America/Lima'); // Zona horaria GMT-5

echo "--- Iniciando MiniMed Motor de Notificaciones ---\n";
echo "Fecha y hora actual (Lima): " . date('Y-m-d H:i:s') . "\n";

$sql_pendientes = "SELECT 
                        ht.id AS historial_id,
                        ht.paciente_id,
                        m.nombre_medicamento,
                        m.dosis,
                        h.hora_toma,
                        u_paciente.nombre AS paciente_nombre,
                        u_paciente.apellido AS paciente_apellido
                    FROM 
                        historial_tomas ht
                    JOIN 
                        horarios h ON ht.horario_id = h.id
                    JOIN 
                        medicamentos m ON h.medicamento_id = m.id
                    JOIN 
                        pacientes p ON ht.paciente_id = p.id
                    JOIN
                        usuarios u_paciente ON p.usuario_id = u_paciente.id
                    WHERE 
                        ht.estado = 'pendiente' 
                        AND DATE(ht.fecha_hora_programada) = CURDATE() -- Para tomas de hoy
                        AND h.hora_toma < CURTIME() -- Cuya hora de toma ya pasó
                    ORDER BY 
                        h.hora_toma ASC";

$result_pendientes = $conn->query($sql_pendientes);

if ($result_pendientes->num_rows > 0) {
    echo "Identificando tomas pendientes...\n";
    while ($row = $result_pendientes->fetch_assoc()) {
        echo "- Toma pendiente para " . $row['paciente_nombre'] . " " . $row['paciente_apellido'] . ": " 
             . $row['nombre_medicamento'] . " " . $row['dosis'] . " a las " . $row['hora_toma'] . "\n";

        // Aquí es donde se "enviaría" la notificación al cuidador.
        // Dado que no tenemos servicios externos de notificaciones, lo "simulamos"
        // marcando la toma como 'no_tomado' automáticamente después de un tiempo prudente
        // si el paciente no la ha confirmado. Esto es una simplificación.
        // En un sistema real, primero notificarías al cuidador, y luego,
        // si no hay acción, quizás marcarla como no tomada después de un período.

        // Por ejemplo, si una toma tiene más de X minutos de retraso y sigue pendiente:
        // (Esto requiere que `fecha_hora_programada` tenga la fecha Y hora completa)
        // Por ahora, solo detectamos las que ya pasaron la hora.

        // OPCIONAL: Marcar como 'no_tomado' si ha pasado mucho tiempo y sigue pendiente.
        // Esto sería más avanzado, requiriendo `fecha_hora_programada` y la hora actual.
        // Por simplicidad, este motor solo las IDENTIFICA. La confirmación sigue siendo del paciente o cuidador.
        // La notificación al cuidador se reflejará en el historial de cumplimiento.

        // Lógica de "notificación" al cuidador:
        // En este diseño, la "notificación" para el cuidador será que el historial_tomas
        // se actualizará a 'no_tomado' si el paciente no actuó, o que el cuidador
        // podrá ver rápidamente las tomas pendientes/no_tomadas en su panel.
        // Una notificación "automática" real implicaría:
        // 1. Obtener los IDs de los cuidadores asociados a este paciente.
        // 2. Para cada cuidador, enviar un email/SMS/notificación push.
        
        // Simplemente registramos que la toma está "pendiente" para la atención del cuidador.
        // Las notificaciones *visibles* para el cuidador provendrán de su panel
        // al cargar el historial, que mostrará el estado de la toma.
    }
} else {
    echo "No se encontraron tomas pendientes que hayan pasado su hora programada.\n";
}

// --- Lógica para procesar notificaciones de tomas confirmadas por el paciente ---
// Esto se maneja directamente en `confirmar_toma.php`. Cuando el paciente confirma,
// el estado se actualiza en `historial_tomas`. El cuidador simplemente ve este cambio
// al consultar el historial en su panel. No hay una "notificación push" separada desde aquí.

echo "--- MiniMed Motor de Notificaciones Finalizado ---\n";

$conn->close();
?>