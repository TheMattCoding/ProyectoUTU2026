<?php

$host = 'localhost';
$dbname = 'sistema_torneos';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Error al conectar a la base de datos: " . $e->getMessage());
}

require_once __DIR__ . '/logica/procesarTorneosAuto.php';

?>