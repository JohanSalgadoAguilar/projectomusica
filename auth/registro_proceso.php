<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_usuario']);
    $correo = trim($_POST['correo']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, correo, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $correo, $password]);
        header("Location: ../index.php?msg=Cuenta creada. Ya puedes iniciar sesión");
    } catch (PDOException $e) {
        header("Location: ../index.php?error=El usuario o correo ya existe");
    }
    exit;
}