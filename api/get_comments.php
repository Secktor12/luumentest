<?php
header('Content-Type: application/json');
require_once 'db.php';

$card_id = $_GET['card_id'] ?? 0;
$user_uuid = $_GET['user_uuid'] ?? '';

try {
    // Ordenar por likes (desc) y luego por fecha (desc)
    $stmt = $pdo->prepare("SELECT c.*, 
                           (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as likes_count,
                           (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_uuid = ?) as user_has_liked
                           FROM comments c 
                           WHERE c.card_id = ? 
                           ORDER BY likes_count DESC, c.created_at DESC");
    $stmt->execute([$user_uuid, $card_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($comments);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
