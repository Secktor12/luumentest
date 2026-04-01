<?php
/**
 * api/render_video.php
 * Script para renderizar una tarjeta de video con overlays permanentes (Burn-in)
 */
header('Content-Type: application/json');
require_once 'db.php';

// Configuración de rutas
$ffmpegPath = 'C:\\ffmpeg\\bin\\ffmpeg.exe';
$baseDir = dirname(__DIR__); // Raíz del proyecto
$videosDir = $baseDir . DIRECTORY_SEPARATOR . 'videos';
$audiosDir = $baseDir . DIRECTORY_SEPARATOR . 'audios';
$outputDir = $baseDir . DIRECTORY_SEPARATOR . 'render';
$fontPath = 'C:\\Windows\\Fonts\\arial.ttf'; // Fuente por defecto en Windows

// Parámetros
$cardId = $_GET['card_id'] ?? null;
$musicId = $_GET['music_id'] ?? null; // Si se envía, cambia la música

if (!$cardId) {
    die(json_encode(['status' => 'error', 'message' => 'Falta el ID de la tarjeta']));
}

try {
    // 1. Obtener datos de la tarjeta
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ?");
    $stmt->execute([$cardId]);
    $card = $stmt->fetch();

    if (!$card) {
        throw new Exception("Tarjeta no encontrada");
    }

    $videoFile = $videosDir . DIRECTORY_SEPARATOR . basename($card['video_url']);
    // Si no se especifica musicId, usamos el audio_url original de la tarjeta
    $audioFile = $musicId ? $audiosDir . DIRECTORY_SEPARATOR . "audio ($musicId).mp3" : $baseDir . DIRECTORY_SEPARATOR . $card['audio_url'];
    
    $outputFile = $outputDir . DIRECTORY_SEPARATOR . "render_" . $cardId . "_" . time() . ".mp4";

    // 2. Preparar textos (Escapar para FFmpeg)
    $title = strtoupper($card['title']);
    $rarity = strtoupper($card['rarity'] ?: 'EDICIÓN LIMITADA');
    $code = "LUU-FORJ-" . str_pad($cardId, 3, '0', STR_PAD_LEFT);
    $logo = "LUUMEN.MX";

    // 3. Activos de imagen (Iconos)
    $assetsDir = $baseDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'render';
    $heartIcon = $assetsDir . DIRECTORY_SEPARATOR . 'heart.png';
    $fireIcon = $assetsDir . DIRECTORY_SEPARATOR . 'fire.png';
    $veryfireIcon = $assetsDir . DIRECTORY_SEPARATOR . 'veryfire.png';

    // 4. Construir el filtro complejo de FFmpeg
    $lastLabel = "[0:v]";
    $filterInputs = ""; // Para inputs extra de imágenes
    $inputIndex = 2; // El 0 es video, el 1 es audio
    
    // Lista de iconos a verificar
    $icons = [
        'heart.png' => 'W-w-20:H-h-220', // Posición similar a TikTok
        'fire.png' => 'W-w-20:H-h-160',
        'veryfire.png' => 'W-w-20:H-h-100'
    ];

    $filterStrings = [];
    $extraInputs = "";
    foreach ($icons as $filename => $pos) {
        $path = $assetsDir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($path)) {
            $extraInputs .= " -i \"$path\"";
            $nextLabel = "v$inputIndex";
            $filterStrings[] = "[$lastLabel][" . $inputIndex . ":v]overlay=$pos" . "[$nextLabel]";
            $lastLabel = "[$nextLabel]";
            $inputIndex++;
        }
    }

    // Filtros de texto (Name, Rarity, Code, Logo)
    $textFilters = [
        "drawtext=fontfile='$fontPath':text='$rarity':fontcolor=white@0.8:fontsize=24:x=(w-text_w)/2:y=40:box=1:boxcolor=black@0.4:boxborderw=5",
        "drawtext=fontfile='$fontPath':text='$code':fontcolor=gold@0.8:fontsize=18:x=w-text_w-20:y=20",
        "drawtext=fontfile='$fontPath':text='$title':fontcolor=white:fontsize=48:x=(w-text_w)/2:y=H-120:shadowcolor=black:shadowx=2:shadowy=2",
        "drawtext=fontfile='$fontPath':text='$logo':fontcolor=gold:fontsize=20:x=(w-text_w)/2:y=H-50"
    ];

    $filterComplex = implode(';', $filterStrings);
    if (!empty($filterComplex)) $filterComplex .= ";";
    $filterComplex .= $lastLabel . "," . implode(',', $textFilters);

    // 5. Comando final
    $command = "\"$ffmpegPath\" -y -i \"$videoFile\" -i \"$audioFile\" $extraInputs -filter_complex \"$filterComplex\" -map 0:v -map 1:a -c:v libx264 -preset fast -crf 22 -c:a aac -shortest \"$outputFile\" 2>&1";

    // 5. Ejecutar
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        throw new Exception("Error al renderizar: " . implode("\n", $output));
    }

    $downloadUrl = "render/" . basename($outputFile);
    echo json_encode([
        'status' => 'success',
        'message' => 'Video renderizado con éxito',
        'url' => $downloadUrl,
        'command_used' => $command
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
