<?php
header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

$data = json_decode(file_get_contents('php://input'), true);

$card_id = $data['card_id'] ?? null;
$audio_url = $data['audio_url'] ?? null;
$audio_start = $data['audio_start'] ?? 0;
$audio_duration = $data['audio_duration'] ?? 0;
$video_volume = $data['video_volume'] ?? 1.0;
$music_volume = $data['music_volume'] ?? 1.0;

if (!$card_id) {
    die(json_encode(['success' => false, 'error' => 'Missing required fields']));
}

try {
    $stmt = $pdo->prepare("UPDATE cards SET audio_url = ?, audio_start = ?, audio_duration = ?, video_volume = ?, music_volume = ? WHERE id = ?");
    $stmt->execute([$audio_url, $audio_start, $audio_duration, $video_volume, $music_volume, $card_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
