// js/cuidador.js

console.log("cuidador.js cargado");

document.addEventListener('DOMContentLoaded', () => {
    const patientSelect = document.getElementById('patient-select');
    const addPatientBtn = document.getElementById('add-patient-btn');
    const associatePatientForm = document.getElementById('associate-patient-form');
    const patientEmailInput = document.getElementById('patient-email-input');
    const submitAssociatePatientBtn = document.getElementById('submit-associate-patient');

    const medicationScheduleTitle = document.getElementById('medication-schedule-title');
    const addMedBtn = document.getElementById('add-med-btn');
    const medicationList = document.getElementById('medication-list');
    const patientHistoryTitle = document.getElementById('patient-history-title');
    const patientHistoryList = document.getElementById('patient-history-list');

    const medicationFormModal = document.getElementById('medication-form-modal');
    const closeModalBtn = medicationFormModal.querySelector('.close-button');
    const medicationFormTitle = document.getElementById('medication-form-title');
    const medicationForm = document.getElementById('medication-form');
    const medIdInput = document.getElementById('med-id');
    const medNameInput = document.getElementById('med-name');
    const medDosisInput = document.getElementById('med-dosis');
    const medInstructionsInput = document.getElementById('med-instructions');
    const horariosContainer = document.getElementById('horarios-container');
    const addHorarioBtn = document.getElementById('add-horario-btn');

    let selectedPatientId = null;
    let selectedPatientName = '';
    async function loadPatients() {
        try {
            const response = await fetch('php/api/cuidador/get_pacientes.php');
            const data = await response.json();

            patientSelect.innerHTML = '<option value="">Selecciona un paciente</option>';
            if (data.status === 'success' && data.data.length > 0) {
                data.data.forEach(patient => {
                    const option = document.createElement('option');
                    option.value = patient.paciente_id;
                    option.textContent = `${patient.paciente_nombre} ${patient.paciente_apellido}`; 
                    // ------------------------------------------------------------------
                    patientSelect.appendChild(option);
                });
            } else {
                patientSelect.innerHTML = '<option value="">No hay pacientes asociados</option>';
            }
        } catch (error) {
            console.error('Error al cargar pacientes:', error);
            patientSelect.innerHTML = '<option value="">Error al cargar pacientes</option>';
        }
    }

    async function loadMedicationsForPatient(patientId) {
        if (!patientId) {
            medicationList.innerHTML = '<p>Por favor, selecciona un paciente.</p>';
            addMedBtn.style.display = 'none';
            return;
        }
        try {
            const response = await fetch('php/api/cuidador/gestionar_medicamento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get', paciente_id: patientId })
            });
            const data = await response.json();
            console.log(data); 

            medicationList.innerHTML = '';
            addMedBtn.style.display = 'block';

            if (data.status === 'success' && data.data.length > 0) {
                data.data.forEach(med => {
                    const medCard = document.createElement('div');
                    medCard.classList.add('med-card');
                    medCard.innerHTML = `
                        <h3>${med.nombre_medicamento}</h3>
                        <p><strong>Dosis:</strong> ${med.dosis || 'N/A'}</p>
                        <p><strong>Instrucciones:</strong> ${med.instrucciones || 'Ninguna'}</p>
                        <p><strong>Horarios:</strong> ${med.horarios_toma.map(formatTime).join(', ')}</p>
                        <div class="med-actions">
                            <button class="btn btn-secondary btn-small edit-med-btn" data-med-id="${med.medicamento_id}">Editar</button>
                            <button class="btn btn-danger btn-small delete-med-btn" data-med-id="${med.medicamento_id}">Eliminar</button>
                        </div>
                    `;
                    medicationList.appendChild(medCard);
                });
                attachMedicationActionListeners();
            } else if (data.status === 'success' && data.data.length === 0) {
                medicationList.innerHTML = '<p>No hay medicamentos programados para este paciente. <br> Usa el botón "Agregar Medicamento" para empezar.</p>';
            } else {
                medicationList.innerHTML = '<p>Error al cargar medicamentos. Por favor, inténtalo de nuevo.</p>';
            }
        } catch (error) {
            console.error('Error al cargar medicamentos:', error);
            medicationList.innerHTML = '<p>Error al cargar medicamentos. Por favor, inténtalo de nuevo.</p>';
            addMedBtn.style.display = 'none';
        }
    }

    async function loadPatientHistory(patientId) {
        if (!patientId) {
            patientHistoryList.innerHTML = '<p>Por favor, selecciona un paciente.</p>';
            return;
        }
        try {
            const response = await fetch(`php/api/cuidador/get_historial_paciente.php?paciente_id=${patientId}`);
            const data = await response.json();

            patientHistoryList.innerHTML = ''; 

            if (data.status === 'success' && data.data.length > 0) {
                data.data.forEach(item => {
                    const listItem = document.createElement('li');
                    const formattedDate = new Date(item.fecha_hora_programada).toLocaleDateString('es-PE', {
                        day: '2-digit', month: '2-digit', year: 'numeric'
                    });
                    const formattedTimeProgrammed = formatTime(item.hora_toma);
                    const formattedTimeConfirmed = item.fecha_hora_confirmacion 
                        ? new Date(item.fecha_hora_confirmacion).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true })
                        : 'N/A';

                    let statusClass = '';
                    let statusText = '';
                    if (item.estado === 'tomado') {
                        statusClass = 'status-taken';
                        statusText = `Tomado por ${item.confirmado_por || 'paciente'}`;
                    } else if (item.estado === 'no_tomado') {
                        statusClass = 'status-skipped';
                        statusText = `Omitido por ${item.confirmado_por || 'paciente'}`;
                    } else {
                        statusClass = 'status-pending';
                        statusText = 'Pendiente';
                    }

                    listItem.innerHTML = `
                        <span>[${formattedDate} ${formattedTimeProgrammed}]</span> 
                        ${item.nombre_medicamento} ${item.dosis} - 
                        <span class="${statusClass}">${statusText}</span>
                        ${item.fecha_hora_confirmacion ? ` (Confirmado: ${formattedTimeConfirmed})` : ''}
                    `;
                    patientHistoryList.appendChild(listItem);
                });
            } else {
                patientHistoryList.innerHTML = '<li>No hay historial de tomas registrado para este paciente.</li>';
            }
        } catch (error) {
            console.error('Error al cargar historial del paciente:', error);
            patientHistoryList.innerHTML = '<li>Error al cargar el historial del paciente.</li>';
        }
    }

    let previousOverdueIds = [];

    async function loadOverdueReminders() {
        const overdueList = document.getElementById('overdue-list');
        try {
            const response = await fetch('php/api/cuidador/get_tomas_atrasadas.php');
            const data = await response.json();
            overdueList.innerHTML = '';
            if (data.status === 'success' && data.data.length > 0) {
                const currentIds = data.data.map(toma => toma.toma_id);
                const newTomas = data.data.filter(toma => !previousOverdueIds.includes(toma.toma_id));
                if (newTomas.length > 0 && Notification.permission === "granted") {
                    newTomas.forEach(toma => {
                        new Notification("MiniMed - Toma atrasada", {
                            body: `Paciente: ${toma.nombre_paciente} ${toma.apellido_paciente}\nMedicamento: ${toma.nombre_medicamento} (${toma.dosis})`,
                            icon: "img/icono.png" 
                        });
                    });
                }
                previousOverdueIds = currentIds;

                data.data.forEach(toma => {
                    const div = document.createElement('div');
                    div.classList.add('overdue-card');
                    div.innerHTML = `
                        <strong>${toma.nombre_paciente} ${toma.apellido_paciente}</strong>: 
                        ${toma.nombre_medicamento} (${toma.dosis}) - 
                        Programado para: ${new Date(toma.fecha_hora_programada).toLocaleString('es-PE')}
                    `;
                    overdueList.appendChild(div);
                });
            } else {
                overdueList.innerHTML = '<p>No hay tomas atrasadas pendientes.</p>';
                previousOverdueIds = [];
            }
        } catch (error) {
            overdueList.innerHTML = '<p>Error al cargar tomas atrasadas.</p>';
        }
    }

    loadOverdueReminders();
    setInterval(loadOverdueReminders, 10000); 
    patientSelect.addEventListener('change', (event) => {
        selectedPatientId = event.target.value;
        selectedPatientName = event.target.options[event.target.selectedIndex].textContent;

        if (selectedPatientId) {
            medicationScheduleTitle.textContent = `Plan de Medicamentos para ${selectedPatientName}`;
            patientHistoryTitle.textContent = `Historial de Cumplimiento de ${selectedPatientName}`;
            loadMedicationsForPatient(selectedPatientId);
            loadPatientHistory(selectedPatientId);
        } else {
            medicationScheduleTitle.textContent = `Plan de Medicamentos para [Paciente Seleccionado]`;
            patientHistoryTitle.textContent = `Historial de Cumplimiento de [Paciente Seleccionado]`;
            medicationList.innerHTML = '<p>Por favor, selecciona un paciente para gestionar sus medicamentos.</p>';
            patientHistoryList.innerHTML = '<p>Selecciona un paciente para ver su historial de cumplimiento.</p>';
            addMedBtn.style.display = 'none';
        }
    });

    addPatientBtn.addEventListener('click', () => {
        associatePatientForm.style.display = associatePatientForm.style.display === 'none' ? 'block' : 'none';
    });

    submitAssociatePatientBtn.addEventListener('click', async () => {
        const patientEmail = patientEmailInput.value;
        if (!patientEmail) {
            alert('Por favor, ingresa el correo electrónico del paciente.');
            return;
        }

        try {
            const response = await fetch('php/api/cuidador/asignar_paciente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ patient_email: patientEmail })
            });
            const data = await response.json();

            if (data.status === 'success') {
                alert(data.message);
                patientEmailInput.value = '';
                associatePatientForm.style.display = 'none';
                loadPatients(); 
            } else {
                alert('Error al asociar paciente: ' + data.message);
            }
        } catch (error) {
            console.error('Error al asociar paciente:', error);
            alert('Error de conexión al asociar paciente.');
        }
    });

    addMedBtn.addEventListener('click', () => {
    
        medIdInput.value = '';
        medicationFormTitle.textContent = 'Agregar Nuevo Medicamento';
        medicationForm.reset();
        horariosContainer.innerHTML = '<input type="time" name="horarios[]" required class="form-input">';
        medicationFormModal.style.display = 'block';
    });

    closeModalBtn.addEventListener('click', () => {
        medicationFormModal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target == medicationFormModal) {
            medicationFormModal.style.display = 'none';
        }
    });

    addHorarioBtn.addEventListener('click', () => {
        const input = document.createElement('input');
        input.type = 'time';
        input.name = 'horarios[]';
        input.required = true;
        input.classList.add('form-input');
        horariosContainer.appendChild(input);
    });

    medicationForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!selectedPatientId) {
            alert('Por favor, selecciona un paciente antes de guardar un medicamento.');
            return;
        }

        const formData = new FormData(medicationForm);
        const horarios = [];
        formData.getAll('horarios[]').forEach(time => {
            if (time) horarios.push(time);
        });

        const action = medIdInput.value ? 'edit' : 'add';
        const payload = {
            action: action,
            paciente_id: selectedPatientId,
            medicamento_id: medIdInput.value,
            nombre_medicamento: formData.get('nombre_medicamento'),
            dosis: formData.get('dosis'),
            instrucciones: formData.get('instrucciones'),
            horarios: horarios
        };

        if (horarios.length === 0) {
            alert('Debes añadir al menos un horario de toma.');
            return;
        }

        try {
            const response = await fetch('php/api/cuidador/gestionar_medicamento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();

            if (data.status === 'success') {
                alert(data.message);
                medicationFormModal.style.display = 'none';
                loadMedicationsForPatient(selectedPatientId); 
            } else {
                alert('Error al guardar medicamento: ' + data.message);
            }
        } catch (error) {
            console.error('Error al guardar medicamento:', error);
            alert('Error de conexión al guardar el medicamento.');
        }
    });

    function attachMedicationActionListeners() {
        document.querySelectorAll('.edit-med-btn').forEach(button => {
            button.onclick = async (event) => {
                const medId = event.target.dataset.medId;
                try {
                    const response = await fetch('php/api/cuidador/gestionar_medicamento.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'get', paciente_id: selectedPatientId })
                    });
                    const data = await response.json();

                    if (data.status === 'success' && data.data.length > 0) {
                        const medToEdit = data.data.find(m => m.medicamento_id == medId);
                        if (medToEdit) {
                            medIdInput.value = medToEdit.medicamento_id;
                            medicationFormTitle.textContent = 'Editar Medicamento';
                            medNameInput.value = medToEdit.nombre_medicamento;
                            medDosisInput.value = medToEdit.dosis;
                            medInstructionsInput.value = medToEdit.instrucciones;
                            
                            horariosContainer.innerHTML = '';
                            medToEdit.horarios_toma.forEach(hora => {
                                const input = document.createElement('input');
                                input.type = 'time';
                                input.name = 'horarios[]';
                                input.required = true;
                                input.classList.add('form-input');
                                input.value = hora;
                                horariosContainer.appendChild(input);
                            });
                            medicationFormModal.style.display = 'block';
                        } else {
                            alert('Medicamento no encontrado para editar.');
                        }
                    } else {
                        alert('No se pudieron cargar los datos del medicamento.');
                    }
                } catch (error) {
                    console.error('Error al cargar datos para editar:', error);
                    alert('Error de conexión al cargar los datos del medicamento.');
                }
            };
        });

        document.querySelectorAll('.delete-med-btn').forEach(button => {
            button.onclick = async (event) => {
                const medId = event.target.dataset.medId;
                if (confirm('¿Estás seguro de que quieres eliminar este medicamento y todos sus horarios?')) {
                    try {
                        const response = await fetch('php/api/cuidador/gestionar_medicamento.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'delete', paciente_id: selectedPatientId, medicamento_id: medId })
                        });
                        const data = await response.json();

                        if (data.status === 'success') {
                            alert(data.message);
                            loadMedicationsForPatient(selectedPatientId); 
                        } else {
                            alert('Error al eliminar medicamento: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error al eliminar medicamento:', error);
                        alert('Error de conexión al eliminar el medicamento.');
                    }
                }
            };
        });
    }

    function formatTime(timeString) {
        if (!timeString) return '';
        const [hours, minutes] = timeString.split(':');
        const date = new Date();
        date.setHours(hours, minutes, 0);
        return date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    loadPatients();

    if ("Notification" in window) {
        if (Notification.permission === "default") {
            Notification.requestPermission();
        }
    }
});