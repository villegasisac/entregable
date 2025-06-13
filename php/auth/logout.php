<?php
session_start();

// Destruir todas las variables de sesión.
$_SESSION = array();

// Destruir la sesión completamente.
session_destroy();

// Redirigir al usuario a la página de inicio de sesión.
header("location: ../../login.html?logout=exitoso");
exit;
?>