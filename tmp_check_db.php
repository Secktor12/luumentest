<?php
require 'db.php';
try {
    $stmt = $pdo->query('SELECT * FROM audio_library LIMIT 5');
    $rows = $stmt->fetchAll();
    echo "ROWS:\n";
    print_r($rows);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
