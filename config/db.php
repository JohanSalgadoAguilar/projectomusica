<?php
// Credenciales (Cámbialas por las de InfinityFree al subirlo)
$host = 'localhost';          // En InfinityFree es algo como: sql204.infinityfree.com
$db   = 'musica_db';          // En InfinityFree es algo como: if0_37482910_musica
$user = 'root';               // En InfinityFree es algo como: if0_37482910
$pass = '';                   // La contraseña de tu cuenta de hosting
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>