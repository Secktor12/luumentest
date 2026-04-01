<?php
// api/get_playlist.php
header('Content-Type: application/json');
$audioDir = '../audios/';

if (!is_dir($audioDir)) {
    echo json_encode(['status' => 'error', 'playlist' => []]);
    exit;
}

$audios = array_values(array_filter(scandir($audioDir), function($f) use ($audioDir) {
    return is_file($audioDir . $f) && preg_match('/\.(mp3|wav|ogg)$/i', $f) && $f !== 'index.php';
}));

echo json_encode(['status' => 'success', 'playlist' => $audios]);
?>
