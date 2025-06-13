<?php require_once 'php/auth/verificar_sesion.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Cuidador - MiniMed</title>
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <header class="panel-header">
        <div class="logo">MiniMed</div>
        <div class="user-info">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Cuidador'); ?></span>
            <a href="php/auth/logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </header>

    <main class="panel-container">
        <!-- Gestión de Pacientes -->
        <section class="patient-management">
            <h2>Gestionar Pacientes</h2>
            <div class="form-group">
                <label for="patient-select">Selecciona un paciente:</label>
                <select id="patient-select">
                    <option value="">Cargando pacientes...</option>
                </select>
            </div>
            <button id="add-patient-btn" class="btn btn-primary">Asociar Nuevo Paciente</button>
            <div id="associate-patient-form" style="display:none; margin-top: 20px;">
                <h3>Asociar Paciente por Email</h3>
                <input type="email" id="patient-email-input" placeholder="Email del paciente" class="form-input">
                <button id="submit-associate-patient" class="btn btn-success">Asociar</button>
            </div>
        </section>

        <!-- Tomas atrasadas -->
        <section class="overdue-reminders">
            <h2>Tomas Pendientes/Atrasadas</h2>
            <div id="overdue-list">
                <p>Cargando...</p>
            </div>
        </section>

        <hr>

        <!-- Plan de Medicamentos -->
        <section class="medication-schedule">
            <h3 id="medication-schedule-title">Plan de Medicamentos para <span id="selected-patient-name">[Paciente Seleccionado]</span></h3>
            <button id="add-med-btn" class="btn btn-success" style="display:none;">Agregar Medicamento</button>
            <div id="medication-list">
                <p>Por favor, selecciona un paciente para gestionar sus medicamentos.</p>
            </div>

            <!-- Modal para agregar/editar medicamento -->
            <div id="medication-form-modal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="close-button" title="Cerrar">&times;</span>
                    <h3 id="medication-form-title">Agregar Nuevo Medicamento</h3>
                    <form id="medication-form" autocomplete="off">
                        <input type="hidden" id="med-id" name="med_id">
                        <div class="form-group">
                            <label for="med-name">Nombre del Medicamento:</label>
                            <input type="text" id="med-name" name="nombre_medicamento" required class="form-input" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label for="med-dosis">Dosis:</label>
                            <input type="text" id="med-dosis" name="dosis" class="form-input" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label for="med-instructions">Instrucciones:</label>
                            <textarea id="med-instructions" name="instrucciones" rows="3" class="form-input" maxlength="255"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Horarios de Toma (ej. 08:00, 14:30):</label>
                            <div id="horarios-container">
                                <input type="time" name="horarios[]" required class="form-input">
                            </div>
                            <button type="button" id="add-horario-btn" class="btn btn-secondary btn-small">Añadir Horario</button>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar Medicamento</button>
                    </form>
                </div>
            </div>
        </section>

        <hr>

        <!-- Historial de Cumplimiento -->
        <section class="patient-history">
            <h3 id="patient-history-title">Historial de Cumplimiento de <span id="history-patient-name">[Paciente Seleccionado]</span></h3>
            <ul id="patient-history-list">
                <li><em>Selecciona un paciente para ver su historial de cumplimiento.</em></li>
            </ul>
        </section>
    </main>

    <script src="js/cuidador.js"></script>
</body>
</html>