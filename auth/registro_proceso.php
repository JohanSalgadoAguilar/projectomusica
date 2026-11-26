<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_usuario']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $rol = 'consultor';

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, password_hash, rol) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $password, $rol]);
        header("Location: ../index.php?msg=Cuenta creada. Ya puedes iniciar sesión");
    } catch (PDOException $e) {
        header("Location: ../index.php?error=Ese nombre de usuario ya está en uso");
    }
    exit;
}