<?php
// api/fixer.php
header('Content-Type: application/json');
require_once 'db.php';

$report = [];

try {
    // 1. Verificar y Añadir columnas faltantes en 'cards'
    $colsToAdd = [
        "audio_start" => "DECIMAL(10,2) DEFAULT 0",
        "audio_duration" => "DECIMAL(10,2) DEFAULT 6",
        "video_volume" => "DECIMAL(3,2) DEFAULT 1.0",
        "music_volume" => "DECIMAL(3,2) DEFAULT 1.0"
    ];

    foreach ($colsToAdd as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM cards LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE cards ADD COLUMN $col $definition");
            $report[] = "Columna '$col' añadida con éxito.";
        } else {
            $report[] = "Columna '$col' ya existía.";
        }
    }

    // 2. Crear tabla audio_library si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS audio_library (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $report[] = "Tabla 'audio_library' verificada/creada.";

    // 3. Sincronizar canciones desde la carpeta /audios/
    $audioDir = '../audios/';
    if (is_dir($audioDir)) {
        $files = array_filter(scandir($audioDir), function($f) use ($audioDir) {
            return is_file($audioDir . $f) && preg_match('/\.(mp3|wav|ogg)$/i', $f);
        });

        foreach ($files as $file) {
            $path = 'audios/' . $file;
            $title = strtoupper(str_replace(['.mp3', '.wav', '.ogg', '-'], ['','','',' '], $file));
            
            $check = $pdo->prepare("SELECT id FROM audio_library WHERE file_path = ?");
            $check->execute([$path]);
            if (!$check->fetch()) {
                $ins = $pdo->prepare("INSERT INTO audio_library (title, file_path) VALUES (?, ?)");
                $ins->execute([$title, $path]);
                $report[] = "Nueva canción registrada: $title";
            }
        }
    }

    echo json_encode(['success' => true, 'actions' => $report]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
