<?php
$host = 'localhost';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS luumen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database created or exists.\n";
    $pdo->exec("USE luumen");

    // 1. Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            video_url VARCHAR(255) NOT NULL,
            audio_url VARCHAR(255) NOT NULL,
            serial_number VARCHAR(50) NOT NULL,
            rarity VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_uuid VARCHAR(100) NOT NULL,
            card_id INT NOT NULL,
            interaction_type ENUM('fuego', 'muy_fuego', 'me_encanta') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_interaction (user_uuid, card_id, interaction_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS view_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_uuid VARCHAR(100) NOT NULL,
            card_id INT NOT NULL,
            view_duration INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Tables created successfully.\n";

    // 2. Clear existing cards for idempotency
    $pdo->exec("TRUNCATE TABLE cards");

    // 3. Insert 100 cards
    $videos = [
        ['name' => 'genesis', 'title' => 'Cubo Génesis'],
        ['name' => 'esfera', 'title' => 'Esfera del Vacío'],
        ['name' => 'prisma', 'title' => 'Prisma del Tiempo'],
        ['name' => 'aura', 'title' => 'Aura Esmeralda'],
        ['name' => 'nova', 'title' => 'Nova Púrpura']
    ];

    $stmt = $pdo->prepare("INSERT INTO cards (title, video_url, audio_url, serial_number, rarity) VALUES (?, ?, ?, ?, ?)");

    for ($i = 1; $i <= 100; $i++) {
        $serial = sprintf("#%03d", $i);
        
        $rarity = 'Común';
        if ($i == 100) {
            $rarity = 'Legendario';
        } elseif ($i >= 96) {
            $rarity = 'Raro';
        }

        $videoItem = $videos[array_rand($videos)];
        $title = $videoItem['title'] . " " . $serial;
        $videoUrl = 'videos/' . $videoItem['name'] . '.mp4';
        $audioUrl = 'audios/' . $videoItem['name'] . '.mp3';

        $stmt->execute([$title, $videoUrl, $audioUrl, $serial, $rarity]);
    }

    echo "100 Cards inserted successfully.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>
