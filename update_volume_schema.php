<?php
require_once 'api/db.php';
try {
    $pdo->exec("ALTER TABLE cards ADD COLUMN video_volume DECIMAL(3,2) DEFAULT 1.00");
} catch(PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE cards ADD COLUMN music_volume DECIMAL(3,2) DEFAULT 1.00");
} catch(PDOException $e) {}

echo "Schema updated.";
?>
