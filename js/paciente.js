document.addEventListener('DOMContentLoaded', () => {
    const clockElement = document.getElementById('clock');
    const medicationRemindersContainer = document.getElementById('medication-reminders');
    const historyList = document.getElementById('history-list');

    const alarmSound = new Audio('audio/alarm.mp3');
    alarmSound.loop = true;

    function updateClock() {
        const now = new Date();
        const options = {
            timeZone: 'America/Lima',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        clockElement.textContent = now.toLocaleTimeString('es-PE', options);
    }
    setInterval(updateClock, 1000);
    updateClock();

    function formatTime(timeString) {
        if (!timeString) return '';
        const [hours, minutes] = timeString.split(':');
        const date = new Date();
        date.setHours(parseInt(hours), parseInt(minutes), 0);
        return date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function stopAlarm() {
        alarmSound.pause();
        alarmSound.currentTime = 0;
    }

    async function loadMedicationReminders() {
        try {
            const response = await fetch('php/api/paciente/get_medicamentos.php');
            const data = await response.json();

            medicationRemindersContainer.innerHTML = '';
            let hasPendingMedicationsDue = false;

            if (data.status === 'success' && data.data.length > 0) {
                const now = new Date();
                const currentHour = now.getHours();
                const currentMinute = now.getMinutes();

                data.data.forEach(med => {
                    const [medHour, medMinute] = med.hora_toma.split(':').map(Number);
                    const formattedTime = formatTime(med.hora_toma);

                    let reminderClass = '';
                    let actionButtons = '';

                    if (med.estado_toma === 'pendiente') {
                        actionButtons = `
                            <button class="btn-confirm" data-toma-id="${med.toma_id}" data-estado="tomado">✔️ Tomé</button>
                            <button class="btn-skip" data-toma-id="${med.toma_id}" data-estado="no_tomado">❌ Omití</button>
                        `;
                        const isPastDue = (medHour < currentHour) || (medHour === currentHour && medMinute < currentMinute);
                        if (isPastDue) {
                            reminderClass = 'past-due';
                            hasPendingMedicationsDue = true;
                        } else {
                            reminderClass = 'pending';
                        }

                        const scheduledTime = new Date();
                        scheduledTime.setHours(medHour, medMinute, 0, 0);

                        if (med.estado_toma === 'pendiente' && now.getTime() >= (scheduledTime.getTime() - (60 * 1000))) {
                            if ('Notification' in window) {
                                Notification.requestPermission().then(permission => {
                                    if (permission === 'granted') {
                                        new Notification("¡HORA DE TU MEDICAMENTO AHORA!", {
                                            body: `¡Atención! Es hora de tomar tu ${med.nombre_medicamento} (${med.dosis}). Programado para: ${formattedTime}`,
                                            icon: 'img/logo.png',
                                            tag: `toma-${med.toma_id}`,
                                            renotify: true,
                                            vibrate: [200, 100, 200]
                                        });
                                    }
                                });
                            }
                        }
                    } else if (med.estado_toma === 'tomado') {
                        reminderClass = 'status-taken';
                        actionButtons = `<span class="status-badge status-taken">Tomado</span>`;
                        if ('Notification' in window) {
                            Notification.getNotifications({ tag: `toma-${med.toma_id}` }).then(notifications => {
                                notifications.forEach(notification => notification.close());
                            });
                        }
                    } else if (med.estado_toma === 'no_tomado') {
                        reminderClass = 'status-skipped';
                        actionButtons = `<span class="status-badge status-skipped">Omitido</span>`;
                        if ('Notification' in window) {
                            Notification.getNotifications({ tag: `toma-${med.toma_id}` }).then(notifications => {
                                notifications.forEach(notification => notification.close());
                            });
                        }
                    }

                    const reminderCard = document.createElement('div');
                    reminderCard.classList.add('reminder-card', reminderClass);
                    if (reminderClass === 'past-due') {
                        reminderCard.classList.add('urgent-reminder');
                    }

                    reminderCard.innerHTML = `
                        <p class="time">${formattedTime}</p>
                        <div class="details">
                            <p class="med-name">${med.nombre_medicamento}</p>
                            <p class="med-dose">${med.dosis}</p>
                            ${med.instrucciones ? `<p class="med-instructions">${med.instrucciones}</p>` : ''}
                        </div>
                        <div class="actions">
                            ${actionButtons}
                        </div>
                    `;
                    medicationRemindersContainer.appendChild(reminderCard);
                });

                if (hasPendingMedicationsDue) {
                    alarmSound.play().catch(e => console.log("No se pudo reproducir la alarma (posible interacción de usuario requerida):", e));
                } else {
                    stopAlarm();
                }

                attachConfirmationListeners();
            } else {
                medicationRemindersContainer.innerHTML = '<p>No tienes medicamentos programados para hoy.</p>';
                stopAlarm();
            }
        } catch (error) {
            console.error('Error al cargar recordatorios:', error);
            medicationRemindersContainer.innerHTML = '<p>No tienes medicamentos programados para hoy.</p>';
            stopAlarm();
        }
    }

    async function loadHistory() {
        try {
            const response = await fetch('php/api/paciente/get_historial.php');
            const data = await response.json();

            historyList.innerHTML = '';

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
                        statusText = 'Tomado';
                    } else if (item.estado === 'no_tomado') {
                        statusClass = 'status-skipped';
                        statusText = 'Omitido';
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
                    historyList.appendChild(listItem);
                });
            } else {
                historyList.innerHTML = '<li>No hay historial de tomas reciente.</li>';
            }
        } catch (error) {
            console.error('Error al cargar historial:', error);
            historyList.innerHTML = '<li>Error al cargar el historial.</li>';
        }
    }

    function attachConfirmationListeners() {
        document.querySelectorAll('.btn-confirm, .btn-skip').forEach(button => {
            button.onclick = async (event) => {
                const tomaId = event.target.dataset.tomaId;
                const estado = event.target.dataset.estado;

                if (!tomaId) {
                    alert('Error: ID de toma no disponible. Recarga la página.');
                    return;
                }

                try {
                    const response = await fetch('php/api/paciente/confirmar_toma.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ toma_id: tomaId, estado: estado })
                    });
                    const data = await response.json();
                    if (data.status === 'success') {
                        alert('Registro recibido. ');
                        try {
                            await loadMedicationReminders();
                        } catch (e) {
                            console.error('Error al recargar recordatorios:', e);
                        }
                    } else {
                        alert('Error al confirmar la toma: ' + data.message);
                    }
                } catch (error) {
                    alert('Error de conexión al confirmar la toma.');
                }
            };
        });
    }

    loadMedicationReminders();
    loadHistory();

    setInterval(loadMedicationReminders, 5000);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadMedicationReminders();
        }
    });
});