<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id_usuario, nombre_usuario, password_hash, rol FROM usuarios WHERE nombre_usuario = ?");
    $stmt->execute([$nombre_usuario]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($password, $usuario['password_hash'])) {
        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
        $_SESSION['rol'] = $usuario['rol'];
        header("Location: ../reproductor.php");
        exit;
    } else {
        header("Location: ../index.php?error=Usuario o contraseña incorrectos");
        exit;
    }
}