<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    // 1. Get all available audios
    $stmt = $pdo->query("SELECT file_path FROM audio_library");
    $audios = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($audios)) {
        throw new Exception("No hay canciones en la biblioteca.");
    }

    // 2. Get all cards
    $stmtCards = $pdo->query("SELECT id FROM cards");
    $cards = $stmtCards->fetchAll(PDO::FETCH_COLUMN);

    // 3. Update each card with a random audio from the list
    $pdo->beginTransaction();
    $updateStmt = $pdo->prepare("UPDATE cards SET audio_url = ?, audio_start = ?, audio_duration = 6, music_volume = 1, video_volume = 1 WHERE id = ?");

    foreach ($cards as $cardId) {
        $randomAudio = $audios[array_rand($audios)];
        $randomStart = rand(0, 15); // Random start between 0 and 15 seconds
        $updateStmt->execute([$randomAudio, $randomStart, $cardId]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Se han asignado canciones aleatorias a ' . count($cards) . ' tarjetas correctamente.'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
