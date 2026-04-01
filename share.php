<?php
require_once 'api/db.php';

$card_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($card_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ?");
    $stmt->execute([$card_id]);
    $card = $stmt->fetch();
}

if (!$card) {
    header("Location: index.html");
    exit;
}

// URL base para los archivos (cambiar por la URL real al subir a internet)
$base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/";
$page_url = $base_url . "index.html?card_id=" . $card['id'];
$video_url = $base_url . $card['video_url'];
$title = "Luumen - " . $card['title'];
$description = "¡Mira mi nuevo coleccionable de " . $card['rarity'] . "! 🔥 Entra ahora para ver la colección completa.";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    
    <!-- Meta Tags para WhatsApp / Facebook (Open Graph) -->
    <meta property="og:title" content="<?php echo $title; ?>">
    <meta property="og:description" content="<?php echo $description; ?>">
    <meta property="og:type" content="video.other">
    <meta property="og:url" content="<?php echo $page_url; ?>">
    <meta property="og:image" content="<?php echo $base_url; ?>logo.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    
    <!-- Video Direct Play (WhatsApp Support) -->
    <meta property="og:video" content="<?php echo $video_url; ?>">
    <meta property="og:video:secure_url" content="<?php echo $video_url; ?>">
    <meta property="og:video:type" content="video/mp4">
    <meta property="og:video:width" content="720">
    <meta property="og:video:height" content="1280">

    <!-- Meta Tags para Twitter -->
    <meta name="twitter:card" content="player">
    <meta name="twitter:title" content="<?php echo $title; ?>">
    <meta name="twitter:description" content="<?php echo $description; ?>">
    <meta name="twitter:image" content="<?php echo $base_url; ?>logo.png">
    <meta name="twitter:player" content="<?php echo $video_url; ?>">
    <meta name="twitter:player:width" content="720">
    <meta name="twitter:player:height" content="1280">
    <meta name="twitter:player:stream" content="<?php echo $video_url; ?>">
    <meta name="twitter:player:stream:content_type" content="video/mp4">

    <style>
        body { background: #050505; color: white; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
        .loader { border: 4px solid #D4AF37; border-top: 4px solid transparent; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .branding { color: #D4AF37; font-weight: bold; letter-spacing: 2px; }
    </style>
    
    <script>
        // Redirigir automáticamente a la app principal
        setTimeout(() => {
            window.location.href = "<?php echo $page_url; ?>";
        }, 100);
    </script>
</head>
<body>
    <div>
        <div class="loader"></div>
        <p class="branding">LUUMEN</p>
        <p>Cargando pieza exclusiva...</p>
    </div>
</body>
</html>
