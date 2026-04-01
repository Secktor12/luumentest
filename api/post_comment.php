<?php
header('Content-Type: application/json');
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$card_id = $data['card_id'] ?? 0;
$user_uuid = $data['user_uuid'] ?? '';
$text = trim($data['comment_text'] ?? '');

if (strlen($text) > 0 && strlen($text) <= 280 && $card_id > 0 && !empty($user_uuid)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO comments (card_id, user_uuid, comment_text) VALUES (?, ?, ?)");
        $stmt->execute([$card_id, $user_uuid, $text]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos o comentario muy largo']);
}
?>
