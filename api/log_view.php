<?php
require_once 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$uuid = $input['uuid'] ?? null;
$cardId = $input['card_id'] ?? null;
$duration = $input['duration'] ?? 0;

if (!$uuid || !$cardId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing basic data']);
    exit;
}

try {
    // 1. Registro de visita general (solo una vez por sesión o cada 24h sería ideal, pero por ahora registramos el evento)
    // Usamos INSERT IGNORE o ON DUPLICATE KEY si quisiéramos contar visitantes únicos reales por día.
    // Para simplificar, registramos la entrada en una tabla de analíticas.
    
    // 2. Registrar tiempo de vista
    if ($duration > 0) {
        $stmt = $pdo->prepare("INSERT INTO view_analytics (user_uuid, card_id, view_duration) VALUES (?, ?, ?)");
        $stmt->execute([$uuid, $cardId, $duration]);
    } else {
        // Registro de "Entró a la página" (impresión activa)
        $stmt = $pdo->prepare("INSERT INTO view_analytics (user_uuid, card_id, view_duration) VALUES (?, ?, 0)");
        $stmt->execute([$uuid, $cardId]);
    }

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
