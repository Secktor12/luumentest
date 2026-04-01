<?php
date_default_timezone_set('America/Mexico_City');
/* --- CONFIGURACIÓN LOCAL (XAMPP) ---
$host = 'localhost';
$db = 'luumenmx_web';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
*/

// --- CONFIGURACIÓN PRODUCCIÓN (Neubox) ---
$host = 'localhost';
$db = 'luumenmx_web';
$user = 'luumenmx_admin';
$pass = 'zkm6J_n}_u8gh[iH';
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
