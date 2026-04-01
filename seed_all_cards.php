<?php
require_once 'api/db.php';

$videoDir = 'videos/';
$files = scandir($videoDir);

$inserted = 0;
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'mp4') {
        // Simple title from filename
        $title = pathinfo($file, PATHINFO_FILENAME);
        $videoUrl = 'videos/' . $file;
        
        // Infer rarity
        $rarity = 'Común';
        if (stripos($file, 'Epico') !== false) $rarity = 'Épico';
        else if (stripos($file, 'Legendario') !== false) $rarity = 'Legendario';
        else if (stripos($file, 'Rara') !== false) $rarity = 'Raro';
        else if (stripos($file, 'Raro') !== false) $rarity = 'Raro';

        // Check if exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cards WHERE video_url = ?");
        $stmt->execute([$videoUrl]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO cards (title, video_url, audio_url, serial_number, rarity) VALUES (?, ?, '', ?, ?)");
            $stmt->execute([$title, $videoUrl, 'SN-' . bin2hex(random_bytes(4)), $rarity]);
            $inserted++;
        }
    }
}

echo "Proceso finalizado. Se agregaron $inserted tarjetas nuevas.";
?>
