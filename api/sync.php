<?php
require_once 'db.php';

// Configuración de carpetas
$videoDir = '../videos/';
$audioDir = '../audios/';

// Obtener lista de audios (excluyendo index.php)
$audioFiles = array_values(array_filter(scandir($audioDir), function($f) use ($audioDir) {
    return is_file($audioDir . $f) && $f !== 'index.php' && preg_match('/\.(mp3|wav|ogg)$/i', $f);
}));

if (empty($audioFiles)) {
    die(json_encode(['status' => 'error', 'message' => 'No se encontraron audios en la carpeta.']));
}

// OPCIONAL: MODO LIMPIEZA TOTAL (?clean=1)
if (isset($_GET['clean']) && $_GET['clean'] == '1') {
    $pdo->exec("TRUNCATE TABLE cards");
}

// Obtener lista de videos (excluyendo index.php)
$videoFiles = array_filter(scandir($videoDir), function($f) use ($videoDir) {
    return is_file($videoDir . $f) && $f !== 'index.php' && preg_match('/\.(mp4|webm|mov)$/i', $f);
});

$addedCount = 0;
$skippedCount = 0;
$index = 0;

foreach ($videoFiles as $filename) {
    // Intentar extraer Rareza y Serial del nombre: "Rareza (ID).mp4"
    if (preg_match('/^([a-zA-ZáéíóúÁÉÍÓÚñÑ]+)\s*\((\d+)\)\./i', $filename, $matches)) {
        $rarityRaw = $matches[1];
        $serial = $matches[2];
    } else {
        // Fallback si el nombre no sigue el patrón (ej: "LEG_Test_001.mp4")
        $parts = explode('_', str_replace(['.mp4','.webm'], '', $filename));
        $rarityRaw = $parts[0] ?? 'Comun';
        $serial = $parts[2] ?? '000';
    }

    // Normalizar rareza para la base de datos
    $rarity = 'Común';
    $search = mb_strtolower($rarityRaw, 'UTF-8');
    
    if (strpos($search, 'legend') !== false) $rarity = 'Legendario';
    else if (strpos($search, 'rara') !== false) $rarity = 'Raro';
    else if (strpos($search, 'epico') !== false || strpos($search, 'épico') !== false) $rarity = 'Épico';
    else if (strpos($search, 'exclu') !== false) $rarity = 'Exclusivo';
    
    $title = $rarity . " #" . $serial;
    $video_url = 'videos/' . $filename;
    
    // Asignación cíclica de audio
    $audio_url = 'audios/' . $audioFiles[$index % count($audioFiles)];

    // Verificar si ya existe
    $check = $pdo->prepare("SELECT id FROM cards WHERE video_url = ?");
    $check->execute([$video_url]);
    
    if ($check->fetch()) {
        $skippedCount++;
    } else {
        $stmt = $pdo->prepare("INSERT INTO cards (title, video_url, audio_url, serial_number, rarity) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $video_url, $audio_url, $serial, $rarity]);
        $addedCount++;
    }
    
    $index++;
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => "Sincronización completada.",
    'summary' => [
        'nuevas_cartas' => $addedCount,
        'ya_existentes' => $skippedCount,
        'audios_usados' => count($audioFiles)
    ]
]);
