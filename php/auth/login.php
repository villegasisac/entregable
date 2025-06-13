<?php
session_start(); // Iniciar la sesión al principio de todo
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Recoger y sanitizar datos
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = trim($_POST['password']);

    // 2. Validar campos
    if (empty($email) || empty($password)) {
        header("Location: ../../login.html?error=camposvacios");
        exit();
    }

    // 3. Preparar la consulta para buscar al usuario
    $sql = "SELECT id, nombre, password_hash, tipo_usuario FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // El usuario existe, ahora verificamos la contraseña
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {
            // Contraseña correcta. Iniciar sesión.
            
            // 4. Guardar datos en la variable de sesión
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
            $_SESSION['loggedin'] = true;

            // 5. Redirigir al panel correspondiente
            if ($user['tipo_usuario'] == 'paciente') {
                header("Location: ../../panel_paciente.php");
                exit();
            } elseif ($user['tipo_usuario'] == 'cuidador') {
                header("Location: ../../panel_cuidador.php");
                exit();
            }

        } else {
            // Contraseña incorrecta
            header("Location: ../../login.html?error=credenciales");
            exit();
        }
    } else {
        // Usuario no encontrado
        header("Location: ../../login.html?error=credenciales");
        exit();
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: ../../index.html");
    exit();
}
?>