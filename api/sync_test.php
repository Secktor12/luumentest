<?php
/**
 * API para actualizar la biblioteca de audios (audio_library)
 * Escanea la carpeta /audios/, elimina registros obsoletos y agrega los nuevos nombres.
 */
header('Content-Type: application/json');
require_once 'db.php';

try {
    $audioDir = '../audios/';
    $report = ["deleted" => 0, "added" => 0, "skipped" => 0];

    if (!is_dir($audioDir)) {
        throw new Exception("La carpeta /audios/ no existe.");
    }

    // 1. Obtener archivos actuales en la carpeta
    $filesInFolder = array_filter(scandir($audioDir), function($f) use ($audioDir) {
        return is_file($audioDir . $f) && $f !== 'index.php' && preg_match('/\.(mp3|wav|ogg)$/i', $f);
    });

    // Rutas relativas para comparar con la DB: 'audios/archivo.mp3'
    $folderPaths = array_map(function($f) { return 'audios/' . $f; }, $filesInFolder);

    // 2. Obtener registros actuales de la DB
    $stmt = $pdo->query("SELECT id, file_path FROM audio_library");
    $dbEntries = $stmt->fetchAll();

    // 3. Eliminar registros de la DB cuyos archivos ya no existen
    foreach ($dbEntries as $entry) {
        if (!in_array($entry['file_path'], $folderPaths)) {
            $del = $pdo->prepare("DELETE FROM audio_library WHERE id = ?");
            $del->execute([$entry['id']]);
            $report['deleted']++;
        }
    }

    // 4. Agregar archivos nuevos a la DB
    foreach ($filesInFolder as $file) {
        $path = 'audios/' . $file;
        
        // Verificar si ya existe
        $check = $pdo->prepare("SELECT id FROM audio_library WHERE file_path = ?");
        $check->execute([$path]);
        
        if (!$check->fetch()) {
            // Generar título amigable desde el nombre del archivo
            // Ej: "mi_cancion-v2.mp3" -> "MI CANCION V2"
            $titleFromFilename = strtoupper(str_replace(['.mp3', '.wav', '.ogg', '_', '-'], ['', '', '', ' ', ' '], $file));
            $titleFromFilename = trim($titleFromFilename);

            $ins = $pdo->prepare("INSERT INTO audio_library (title, file_path) VALUES (?, ?)");
            $ins->execute([$titleFromFilename, $path]);
            $report['added']++;
        } else {
            $report['skipped']++;
        }
    }

    // 5. OPCIONAL: Actualizar los registros de la tabla 'cards' que tengan audios rotos
    // Si un card tiene un video_url o audio_url que ya no existe, podemos reasignarlo.
    // Buscamos audios válidos actuales
    $validAudioStmt = $pdo->query("SELECT file_path FROM audio_library");
    $validAudios = $validAudioStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($validAudios)) {
        // Encontrar cards con audio_url que NO están en folderPaths
        $stmtCards = $pdo->query("SELECT id, audio_url FROM cards");
        $cards = $stmtCards->fetchAll();
        $updatedCards = 0;

        foreach ($cards as $card) {
            if ($card['audio_url'] && !in_array($card['audio_url'], $folderPaths)) {
                // El audio ya no existe. Asignamos uno aleatorio de los nuevos.
                $newAudio = $validAudios[array_rand($validAudios)];
                $upd = $pdo->prepare("UPDATE cards SET audio_url = ? WHERE id = ?");
                $upd->execute([$newAudio, $card['id']]);
                $updatedCards++;
            }
        }
        $report['updated_cards'] = $updatedCards;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Actualización de biblioteca completada.',
        'stats' => $report
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
