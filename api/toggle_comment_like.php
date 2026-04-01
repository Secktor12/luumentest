<?php
header('Content-Type: application/json');
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$comment_id = $data['comment_id'] ?? 0;
$user_uuid = $data['user_uuid'] ?? '';

if ($comment_id > 0 && !empty($user_uuid)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_uuid = ?");
        $stmt->execute([$comment_id, $user_uuid]);
        
        if ($stmt->fetch()) {
            $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_uuid = ?")->execute([$comment_id, $user_uuid]);
            $liked = false;
        } else {
            $pdo->prepare("INSERT INTO comment_likes (comment_id, user_uuid) VALUES (?, ?)")->execute([$comment_id, $user_uuid]);
            $liked = true;
        }
        
        // Retornar el nuevo conteo
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
        $stmt->execute([$comment_id]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'liked' => $liked, 'likes_count' => $count]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
}
?>
