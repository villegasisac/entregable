<?php
require_once '../config/db.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $apellido = $conn->real_escape_string(trim($_POST['apellido']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = trim($_POST['password']);
    $tipo_usuario = $conn->real_escape_string($_POST['tipo_usuario']);

    if (empty($nombre) || empty($apellido) || empty($email) || empty($password) || empty($tipo_usuario)) {
        header("Location: ../../registro.html?error=camposvacios");
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../../registro.html?error=emailinvalido");
        exit();
    }
    if (strlen($password) < 6) { 
        header("Location: ../../registro.html?error=passwordcorto");
        exit();
    }
    if ($tipo_usuario !== 'paciente' && $tipo_usuario !== 'cuidador') {
        header("Location: ../../registro.html?error=tipoinvalido");
        exit();
    }

    $sql = "SELECT id FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        header("Location: ../../registro.html?error=emailusado");
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $conn->begin_transaction();

    try {
        $sql_user = "INSERT INTO usuarios (nombre, apellido, email, password_hash, tipo_usuario) VALUES (?, ?, ?, ?, ?)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("sssss", $nombre, $apellido, $email, $password_hash, $tipo_usuario);
        $stmt_user->execute();
        
        $usuario_id = $conn->insert_id;

        if ($tipo_usuario == 'paciente') {
            $sql_tipo = "INSERT INTO pacientes (usuario_id) VALUES (?)";
        } else { 
            $sql_tipo = "INSERT INTO cuidadores (usuario_id) VALUES (?)";
        }
        
        $stmt_tipo = $conn->prepare($sql_tipo);
        $stmt_tipo->bind_param("i", $usuario_id);
        $stmt_tipo->execute();

        $conn->commit();
        
        header("Location: ../../login.html?registro=exitoso");
        exit();

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        header("Location: ../../registro.html?error=dberror");
        exit();
    } finally {
        if (isset($stmt_user)) $stmt_user->close();
        if (isset($stmt_tipo)) $stmt_tipo->close();
        $conn->close();
    }
} else {
    header("Location: ../../index.html");
    exit();
}
?>