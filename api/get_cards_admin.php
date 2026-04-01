<?php
header('Content-Type: application/json');
require_once 'db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$rarity = isset($_GET['rarity']) ? $_GET['rarity'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_desc';

// Map sort
$sortMap = [
    'id_desc' => 'id DESC',
    'id_asc' => 'id ASC',
    'title_asc' => 'title ASC',
    'title_desc' => 'title DESC',
    'date_desc' => 'created_at DESC',
    'date_asc' => 'created_at ASC'
];
$orderBy = $sortMap[$sort] ?? 'id DESC';

try {
    $where = " WHERE 1=1 ";
    $params = [];
    if ($rarity) {
        $where .= " AND rarity = :rarity ";
        $params[':rarity'] = $rarity;
    }

    // Get total count (with filter)
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM cards $where");
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();
    
    // Get cards
    $sql = "SELECT id, title, video_url, audio_url, audio_start, audio_duration, rarity, video_volume, music_volume 
            FROM cards 
            $where 
            ORDER BY $orderBy 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) $stmt->bindValue($key, $val);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cards = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'cards' => $cards,
        'total' => (int)$total,
        'pages' => ceil($total / $limit)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
