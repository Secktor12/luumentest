<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Mexico_City');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manejar tanto JSON como x-www-form-urlencoded
    $data = json_decode(file_get_contents('php://input'), true);
    $email_raw = $data['email'] ?? $_POST['email'] ?? '';
    $email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);

    if (!$email) {
        echo json_encode(['status' => 'error', 'message' => 'El formato del correo electrónico no es válido.']);
        exit;
    }

    try {
        // Verificar si el correo ya existe
        $stmt = $pdo->prepare("SELECT id_suscriptor FROM suscriptores WHERE correo = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Este correo ya está suscrito.']);
            exit;
        }

        // Insertar nuevo suscriptor
        // La tabla tiene: id_suscriptor (AUTO_INCREMENT), correo, fecha
        $stmt = $pdo->prepare("INSERT INTO suscriptores (correo, fecha) VALUES (?, NOW())");
        $stmt->execute([$email]);

        echo json_encode(['status' => 'success', 'message' => '¡Gracias por suscribirte!']);
    }
    catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en el servidor. Inténtalo de nuevo más tarde.']);
    }
}
else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
}
?>
