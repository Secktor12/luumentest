<?php
require_once 'api/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT NOT NULL,
        user_uuid VARCHAR(100) NOT NULL,
        comment_text VARCHAR(280) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comment_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_uuid VARCHAR(100) NOT NULL,
        UNIQUE KEY unique_user_comment_like (user_uuid, comment_id)
    ) ENGINE=InnoDB");

    echo "Tables 'comments' and 'comment_likes' created successfully.";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?>
