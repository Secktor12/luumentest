<?php
date_default_timezone_set('America/Mexico_City');
// Configuración de la base de datos
$host = 'localhost';
$db = 'luumen';
$user = 'root';
$pass = ''; // Por defecto en XAMPP es vacío
/*$db = 'luumenmx_web';
$user = 'luumenmx_admin';
$pass = 'zkm6J_n}_u8gh[iH'; // Por defecto en XAMPP es vacío*/
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
}
catch (\PDOException $e) {
    // En producción, no mostrar el error detallado
    die("Error de conexión: " . $e->getMessage());
}
?>
