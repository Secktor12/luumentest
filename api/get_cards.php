<?php
header('Content-Type: application/json');
require_once 'db.php';

$user_uuid = $_GET['uuid'] ?? '';
if (empty($user_uuid)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing uuid']);
    exit;
}

try {
    // Optional filter: all, fuego, muy_fuego, me_encanta
    $filter = $_GET['filter'] ?? 'all';

    // Build the query
    $sql = "
        SELECT 
            c.*, 
            ui.interaction_type 
        FROM cards c
        LEFT JOIN user_interactions ui ON c.id = ui.card_id AND ui.user_uuid = :uuid
    ";

    $params = [':uuid' => $user_uuid];

    if ($filter && $filter !== 'all') {
        $sql .= " WHERE ui.interaction_type = :filter";
        $params[':filter'] = $filter;
    }

    $sql .= " ORDER BY RAND()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cards = $stmt->fetchAll();

    echo json_encode(['status' => 'success', 'data' => $cards]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
