<?php
header('Content-Type: application/json');
require_once 'db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$offset = ($page - 1) * $limit;

try {
    $where = "";
    $params = [];
    if ($search) {
        $where = " WHERE title LIKE ?";
        $params[] = "%$search%";
    }

    // Get total count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM audio_library" . $where);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();
    
    // Get audios
    $stmt = $pdo->prepare("SELECT * FROM audio_library" . $where . " ORDER BY title ASC LIMIT ? OFFSET ?");
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k + 1, $v);
    }
    $stmt->execute();
    $audios = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'audios' => $audios,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
