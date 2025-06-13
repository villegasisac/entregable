<?php require_once 'php/auth/verificar_sesion.php';  ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - MiniMed</title>
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>
    <header class="panel-header">
        <div class="logo">MiniMed</div>
        <div class="user-info">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Paciente'); ?></span>
            <div id="clock"></div>
            <a href="php/auth/logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </header>

    <main class="panel-container">
        <section class="reminders">
            <h2>Próximas Tomas</h2>
            <div id="medication-reminders">
                <p>Cargando recordatorios...</p>
            </div>
        </section>

        <section class="history">
            <h2>Mi Historial Reciente</h2>
            <ul id="history-list">
                <p>Cargando historial...</p>
            </ul>
        </section>
    </main>
    <script src="js/paciente.js"></script>
 
</body>
</html>