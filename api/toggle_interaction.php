<?php
header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_uuid = $data['uuid'] ?? '';
    $card_id = (int)($data['card_id'] ?? 0);
    $interaction_type = $data['interaction_type'] ?? '';

    if (empty($user_uuid) || $card_id <= 0 || !in_array($interaction_type, ['fuego', 'muy_fuego', 'me_encanta'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data provided']);
        exit;
    }

    try {
        // Check if the interaction exists
        $stmt = $pdo->prepare("SELECT id, interaction_type FROM user_interactions WHERE user_uuid = ? AND card_id = ?");
        $stmt->execute([$user_uuid, $card_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Already exists.
            if ($existing['interaction_type'] === $interaction_type) {
                // Toggle off
                $del = $pdo->prepare("DELETE FROM user_interactions WHERE id = ?");
                $del->execute([$existing['id']]);
                echo json_encode(['status' => 'success', 'action' => 'removed']);
            } else {
                // Change to new interaction
                $update = $pdo->prepare("UPDATE user_interactions SET interaction_type = ? WHERE id = ?");
                $update->execute([$interaction_type, $existing['id']]);
                echo json_encode(['status' => 'success', 'action' => 'updated']);
            }
        } else {
            // Insert
            $insert = $pdo->prepare("INSERT INTO user_interactions (user_uuid, card_id, interaction_type) VALUES (?, ?, ?)");
            $insert->execute([$user_uuid, $card_id, $interaction_type]);
            echo json_encode(['status' => 'success', 'action' => 'added']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
}
?>
