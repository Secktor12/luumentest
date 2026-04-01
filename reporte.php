<?php
session_start();
require_once 'api/db.php';

// Manejo de Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: reporte.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    // 1. Checar bloqueo previo en DB (Intento de Brute Force)
    $pdo->query("CREATE TABLE IF NOT EXISTS login_attempts (ip VARCHAR(45) PRIMARY KEY, attempts INT DEFAULT 1, last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    
    $stmt = $pdo->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $attempt_info = $stmt->fetch();

    $is_blocked = false;
    if ($attempt_info && $attempt_info['attempts'] >= 5) {
        $last_attempt = strtotime($attempt_info['last_attempt']);
        if (time() - $last_attempt < 900) { // 15 minutos de bloqueo
            $is_blocked = true;
            $error = 'Demasiados intentos. Bloqueado temporalmente (15 min).';
        } else {
            // Ya pasaron los 15 mins, reseteamos para dar otra oportunidad
            $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
        }
    }

    if (!$is_blocked) {
        // Consistencia con tu DB anterior: tabla 'admninistrador'
        // NOTA: te recomiendo corregir el typo de 'admninistrador' en el futuro
        $stmt = $pdo->prepare("SELECT * FROM admninistrador WHERE usuario = ? AND contraseña = ?");
        $stmt->execute([$usuario, $password]);
        
        if ($stmt->fetch()) {
            $_SESSION['admin_logged_in'] = true;
            $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
            header('Location: reporte.php');
            exit;
        } else {
            // Incrementar contador de fallos
            $pdo->prepare("INSERT INTO login_attempts (ip, attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = CURRENT_TIMESTAMP")->execute([$ip]);
            $error = 'Credenciales inválidas';
        }
    }
}


$isLogged = isset($_SESSION['admin_logged_in']);

// --- ESTADÍSTICAS ---
$stats = [];
if ($isLogged) {
    // 1. Visitantes únicos totales
    $stats['unique_visitors'] = $pdo->query("SELECT COUNT(DISTINCT user_uuid) FROM view_analytics")->fetchColumn();
    
    // 2. Total de interacciones
    $stats['total_likes'] = $pdo->query("SELECT COUNT(*) FROM user_interactions WHERE interaction_type = 'me_encanta'")->fetchColumn();
    $stats['total_fire'] = $pdo->query("SELECT COUNT(*) FROM user_interactions WHERE interaction_type = 'fuego'")->fetchColumn();
    $stats['total_super_fire'] = $pdo->query("SELECT COUNT(*) FROM user_interactions WHERE interaction_type = 'muy_fuego'")->fetchColumn();

    // 3. Top Tarjetas más vistas (por número de entradas)
    $stmt = $pdo->query("SELECT c.title, COUNT(v.id) as total_views, ROUND(AVG(v.view_duration), 1) as avg_time 
                         FROM view_analytics v 
                         JOIN cards c ON v.card_id = c.id 
                         GROUP BY v.card_id 
                         ORDER BY total_views DESC LIMIT 10");
    $stats['top_cards'] = $stmt->fetchAll();

    // 4. Ranking de Popularidad (Interacciones)
    $stmt = $pdo->query("SELECT c.title, 
                         COUNT(CASE WHEN ui.interaction_type='me_encanta' THEN 1 END) as likes,
                         COUNT(CASE WHEN ui.interaction_type='fuego' THEN 1 END) as fire,
                         COUNT(CASE WHEN ui.interaction_type='muy_fuego' THEN 1 END) as super_fire
                         FROM user_interactions ui
                         JOIN cards c ON ui.card_id = c.id
                         GROUP BY ui.card_id
                         ORDER BY (
                            COUNT(CASE WHEN ui.interaction_type='me_encanta' THEN 1 END) + 
                            COUNT(CASE WHEN ui.interaction_type='fuego' THEN 1 END) + 
                            (COUNT(CASE WHEN ui.interaction_type='muy_fuego' THEN 1 END) * 2)
                         ) DESC LIMIT 10");
    $stats['popular_cards'] = $stmt->fetchAll();

    // 5. Vistas por día (últimos 7 días)
    $stmt = $pdo->query("SELECT DATE(created_at) as fecha, COUNT(*) as vistas 
                         FROM view_analytics 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                         GROUP BY DATE(created_at) 
                         ORDER BY fecha ASC");
    $raw_views = $stmt->fetchAll();
    
    // Preparar labels y data para Chart.js (rellenar huecos con 0)
    $chart_labels = [];
    $chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('d/m', strtotime($d));
        $val = 0;
        foreach($raw_views as $rv) {
            if ($rv['fecha'] == $d) { $val = $rv['vistas']; break; }
        }
        $chart_data[] = $val;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luumen Analytics - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#D4AF37",
                        "primary-glow": "rgba(212, 175, 55, 0.4)",
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Cinzel:wght@400;700;900&family=Comfortaa:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <style>
        body { font-family: 'Outfit', sans-serif; background: #050505; color: #fff; height: 100vh; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .gold-text { background: linear-gradient(to right, #D4AF37, #F9E79F); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>

</head>
<body class="p-4 sm:p-8">

<?php if (!$isLogged): ?>
    <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-yellow-600/10 rounded-full blur-[120px] opacity-40"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-blue-900/10 rounded-full blur-[120px] opacity-20"></div>
    </div>

    <div class="relative z-10 max-w-md mx-auto mt-32">
        <div class="flex flex-col items-center mb-10">
            <img src="logo.png" alt="Luumen" class="h-16 mb-4 filter drop-shadow-[0_0_15px_rgba(212,175,55,0.8)]">
            <p class="text-primary/60 text-[10px] font-bold tracking-[0.4em] uppercase">Panel de Control</p>
        </div>

        <div class="glass p-8 rounded-[40px] shadow-2xl relative overflow-hidden border-white/5">
            <!-- Subtle glow effect inside -->
            <div class="absolute -top-24 -left-24 w-48 h-48 bg-yellow-600/20 rounded-full blur-3xl"></div>
            
            <h2 class="text-3xl font-bold mb-8 text-center gold-text">Iniciar Sesión</h2>
            
            <?php if ($error): ?> 
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-xs p-3 rounded-xl mb-6 text-center animate-pulse">
                    <?= $error ?>
                </div> 
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="login" value="1">
                <div class="space-y-2">
                    <label class="text-[10px] uppercase tracking-widest text-gray-500 ml-2">Usuario</label>
                    <input type="text" name="usuario" placeholder="Admin User" 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-2xl focus:outline-none focus:border-yellow-600/50 transition-all text-sm placeholder:text-gray-600" required>
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] uppercase tracking-widest text-gray-500 ml-2">Contraseña</label>
                    <input type="password" name="password" placeholder="••••••••" 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-2xl focus:outline-none focus:border-yellow-600/50 transition-all text-sm placeholder:text-gray-600" required>
                </div>
                
                <div class="pt-4">
                    <button type="submit" 
                            class="w-full bg-yellow-600 hover:bg-yellow-500 text-black font-bold py-4 rounded-2xl transition-all shadow-lg shadow-yellow-600/20 hover:shadow-yellow-600/40 transform hover:scale-[1.02] active:scale-[0.98]">
                        Ingresar al Sistema
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>

    <!-- DASHBOARD PRINCIPAL -->
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
            <div>
                <h1 class="text-4xl font-bold gold-text">Panel Central</h1>
                <p class="text-gray-400 mt-1">Gestión administrativa de Luumen</p>
            </div>
            
            <div class="flex gap-4 items-center">
                <nav class="glass flex p-1 rounded-2xl">
                    <button onclick="switchTab('analytics')" id="tab-analytics" class="tab-btn px-6 py-2 rounded-xl text-sm font-semibold transition-all bg-yellow-600 text-black">
                        📊 Reportes
                    </button>
                    <button onclick="switchTab('music')" id="tab-music" class="tab-btn px-6 py-2 rounded-xl text-sm font-semibold text-gray-400 transition-all hover:text-white">
                        🎵 Música
                    </button>
                </nav>
                <a href="?logout=1" class="px-6 py-2 border border-white/10 rounded-full hover:bg-white/5 transition-all text-sm">Cerrar Sesión</a>
            </div>
        </div>

        <div id="section-analytics" class="tab-section">
            <!-- STAT CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="glass p-6 rounded-3xl">
                <p class="text-gray-400 text-sm uppercase tracking-widest mb-1">Visitantes Únicos</p>
                <h3 class="text-4xl font-bold"><?= $stats['unique_visitors'] ?></h3>
            </div>
            <div class="glass p-6 rounded-3xl border-red-500/20">
                <p class="text-gray-400 text-sm uppercase tracking-widest mb-1">Me Encanta ❤️</p>
                <h3 class="text-4xl font-bold text-red-500"><?= $stats['total_likes'] ?></h3>
            </div>
            <div class="glass p-6 rounded-3xl border-orange-500/20">
                <p class="text-gray-400 text-sm uppercase tracking-widest mb-1">Fuego 🔥</p>
                <h3 class="text-4xl font-bold text-orange-500"><?= $stats['total_fire'] ?></h3>
            </div>
            <div class="glass p-6 rounded-3xl border-yellow-500/20 relative overflow-hidden">
                <div class="absolute inset-0 bg-yellow-500/5 animate-pulse"></div>
                <p class="text-gray-400 text-sm uppercase tracking-widest mb-1">Muy de Fuego ⚡</p>
                <h3 class="text-4xl font-bold text-yellow-500"><?= $stats['total_super_fire'] ?></h3>
            </div>
        </div>

        <!-- TABLES SECTION -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- TOP VISTAS -->
            <div class="glass p-8 rounded-3xl">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                    <span class="p-2 bg-blue-500/20 rounded-lg">👁️</span> Mayor Retención y Vistas
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-gray-500 text-xs uppercase border-b border-white/5">
                            <tr>
                                <th class="pb-4">Tarjeta</th>
                                <th class="pb-4 text-center">Vistas</th>
                                <th class="pb-4 text-right">Prom. Tiempo</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach($stats['top_cards'] as $card): ?>
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-all">
                                <td class="py-4 font-semibold"><?= htmlspecialchars($card['title']) ?></td>
                                <td class="py-4 text-center"><?= $card['total_views'] ?></td>
                                <td class="py-4 text-right text-blue-400"><?= $card['avg_time'] ?>s</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TOP POPULARIDAD -->
            <div class="glass p-8 rounded-3xl border-primary/20">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                    <span class="p-2 bg-yellow-500/20 rounded-lg">⭐</span> Ranking de Popularidad
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-gray-500 text-xs uppercase border-b border-white/5">
                            <tr>
                                <th class="pb-4">Tarjeta</th>
                                <th class="pb-4 text-right">Interacciones</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach($stats['popular_cards'] as $card): ?>
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-all">
                                <td class="py-4 font-semibold"><?= htmlspecialchars($card['title']) ?></td>
                                <td class="py-4 text-right">
                                    <div class="flex justify-end gap-3 text-[11px] font-mono">
                                        <span class="text-red-400">❤️ <?= $card['likes'] ?></span>
                                        <span class="text-orange-400">🔥 <?= $card['fire'] ?></span>
                                        <span class="text-yellow-400">⚡ <?= $card['super_fire'] ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CHARTS SECTION -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
            <div class="glass p-8 rounded-3xl lg:col-span-2">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                    <span class="p-2 bg-purple-500/20 rounded-lg">📈</span> Vistas en los últimos 7 días
                </h2>
                <div class="h-[300px] w-full">
                    <canvas id="viewsChart"></canvas>
                </div>
            </div>
        </div>

            </div> <!-- Closes section-analytics -->
        </div> <!-- Closes max-w-6xl mx-auto -->

        <!-- MUSIC MANAGEMENT SECTION -->
        <div id="section-music" class="tab-section hidden animate-fade-in">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Cards List Column -->
                <div class="lg:col-span-8 space-y-6">
                    <div class="glass p-8 rounded-3xl">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold flex items-center gap-2">
                                <span class="p-2 bg-yellow-500/20 rounded-lg">🎞️</span> Selección de Tarjetas
                            </h2>
                            <div class="flex flex-wrap gap-2">
                                <select id="filter-rarity" class="bg-white/5 border border-white/10 text-[10px] rounded-xl px-3 py-2 outline-none focus:border-primary/50" onchange="loadCards(1)">
                                    <option value="" class="bg-[#121212]">Rareza: Todas</option>
                                    <option value="Comun" class="bg-[#121212]">Común</option>
                                    <option value="Raro" class="bg-[#121212]">Raro</option>
                                    <option value="Epico" class="bg-[#121212]">Épico</option>
                                    <option value="Legendario" class="bg-[#121212]">Legendario</option>
                                </select>
                                <select id="sort-cards" class="bg-white/5 border border-white/10 text-[10px] rounded-xl px-3 py-2 outline-none focus:border-primary/50" onchange="loadCards(1)">
                                    <option value="id_desc" class="bg-[#121212]">Sort: ID Desc</option>
                                    <option value="id_asc" class="bg-[#121212]">Sort: ID Asc</option>
                                    <option value="title_asc" class="bg-[#121212]">Sort: A-Z</option>
                                    <option value="title_desc" class="bg-[#121212]">Sort: Z-A</option>
                                    <option value="date_desc" class="bg-[#121212]">Sort: Fecha Desc</option>
                                </select>
                                <select id="cards-limit" class="bg-white/5 border border-white/10 text-[10px] rounded-xl px-3 py-2 outline-none focus:border-primary/50" onchange="loadCards(1)">
                                    <option value="20" class="bg-[#121212]">20 por pág</option>
                                    <option value="50" class="bg-[#121212]">50 por pág</option>
                                    <option value="100" class="bg-[#121212]">100 por pág</option>
                                </select>
                            </div>
                        </div>

                        <div id="cards-container" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <!-- Cards will be loaded here -->
                            <div class="col-span-full py-20 flex justify-center">
                                <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-primary"></div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div id="cards-pagination" class="mt-8 flex justify-center gap-2"></div>
                    </div>
                </div>

                <!-- Music Config Column -->
                <div class="lg:col-span-4 space-y-6">
                    <div id="music-editor" class="glass p-8 rounded-3xl sticky top-8 opacity-50 pointer-events-none transform transition-all">
                        <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                            <span class="p-2 bg-purple-500/20 rounded-lg">⚙️</span> Configurar Audio
                        </h2>
                        
                        <div id="selected-card-info" class="mb-6 p-4 bg-white/5 rounded-2xl border border-white/10 text-center">
                            <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Tarjeta Seleccionada</p>
                            <h3 id="card-name" class="font-bold text-lg">Ninguna</h3>
                        </div>

                        <!-- Audio Library Trigger -->
                        <div class="space-y-4">
                            <label class="block text-sm text-gray-400">Audio Actual:</label>
                            <div class="flex gap-2">
                                <p id="current-audio-name" class="flex-grow bg-white/5 p-3 rounded-xl border border-white/10 text-sm truncate uppercase tracking-tighter">Sin asignar</p>
                                <button onclick="openAudioLibrary()" class="bg-primary text-black p-3 rounded-xl hover:bg-yellow-500 transition-all font-bold text-sm whitespace-nowrap">
                                    Cambiar
                                </button>
                            </div>

                            <div id="trimmer-container" class="mt-8 space-y-8 hidden">
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <label class="text-sm text-gray-400">Segmento de la canción:</label>
                                        <span id="trim-time-info" class="text-xs font-mono text-primary">0:00 - 0:06</span>
                                    </div>
                                    
                                    <!-- Visual Trimmer Interface (Instagram Style) -->
                                    <div class="relative h-24 bg-white/5 rounded-2xl overflow-hidden border border-white/10 group">
                                        <!-- Draggable Track Area -->
                                        <div id="trimmer-track" class="absolute inset-y-0 w-[500%] left-0 cursor-grab active:cursor-grabbing transition-transform duration-75">
                                            <!-- Waveform bars inside the track to move with it -->
                                            <div class="absolute inset-0 flex items-center justify-around px-4 opacity-40 pointer-events-none" id="waveform-viz">
                                                <?php for($i=0; $i<80; $i++): $h = rand(20, 80); ?>
                                                    <div class="w-1 bg-white/20 rounded-full h-[<?= $h ?>%]"></div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>

                                        <!-- The Window (Selector) - Static in center, track moves below it -->
                                        <div class="absolute top-0 bottom-0 left-1/2 -ml-8 w-16 border-2 border-primary bg-primary/20 rounded-lg pointer-events-none z-10 transition-shadow group-hover:shadow-[0_0_20px_rgba(212,175,55,0.4)]">
                                            <div class="absolute -top-1 left-1/2 -ml-1 w-2 h-2 bg-primary rounded-full ring-4 ring-primary/20"></div>
                                            <div class="absolute -bottom-1 left-1/2 -ml-1 w-2 h-2 bg-primary rounded-full ring-4 ring-primary/20"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2 flex justify-between text-[10px] text-gray-600 uppercase tracking-widest font-bold">
                                        <span>INICIO</span>
                                        <span>FIN</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">INICIO (SEG)</label>
                                        <input type="number" id="audio-start-val" step="0.1" class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-center font-mono outline-none focus:border-primary/50">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">DURACIÓN (SEG)</label>
                                        <input type="number" id="audio-duration-val" step="0.1" value="6" class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-center font-mono outline-none focus:border-primary/50">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-white/5 mt-4">
                                    <div class="space-y-3">
                                        <div class="flex justify-between text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                                            <label>Volumen Video Original</label>
                                            <span id="video-vol-text" class="text-primary">100%</span>
                                        </div>
                                        <input type="range" id="video-vol-input" min="0" max="1" step="0.01" value="1" oninput="updateVolumes()" 
                                               class="w-full h-1 bg-white/10 rounded-lg appearance-none cursor-pointer accent-primary">
                                    </div>
                                    <div class="space-y-3">
                                        <div class="flex justify-between text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                                            <label>Volumen Música Extra</label>
                                            <span id="music-vol-text" class="text-primary">100%</span>
                                        </div>
                                        <input type="range" id="music-vol-input" min="0" max="1" step="0.01" value="1" oninput="updateVolumes()" 
                                               class="w-full h-1 bg-white/10 rounded-lg appearance-none cursor-pointer accent-primary">
                                    </div>
                                </div>

                                <div class="flex gap-4 pt-4">
                                     <button id="preview-audio-btn" onclick="togglePreview()" class="flex-grow bg-blue-600/20 border border-blue-500/30 text-blue-400 font-bold py-4 rounded-2xl hover:bg-blue-600/30 transition-all flex items-center justify-center gap-2">
                                         <span id="preview-icon">▶️</span> Previsualizar
                                     </button>
                                     <button id="save-audio-btn" onclick="saveAudioConfig()" class="flex-grow bg-white text-black font-bold py-4 rounded-2xl hover:bg-gray-200 transition-all shadow-xl">
                                         Guardar
                                     </button>
                                </div>

                                <!-- Export/Render Section -->
                                <div class="pt-6 mt-6 border-t border-white/5 space-y-4">
                                     <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Renderizado (Burn-in)</h4>
                                     <button id="render-btn" onclick="renderVideoAction()" class="w-full bg-primary/10 border border-primary/20 text-primary font-bold py-4 rounded-2xl hover:bg-primary/20 transition-all flex items-center justify-center gap-2">
                                         <span class="material-symbols-outlined text-[20px]">movie_edit</span>
                                         Grabar Elementos al Video
                                     </button>
                                     <p class="text-[9px] text-gray-500 text-center italic">Esto grabará el logo, código y rareza de forma permanente usando FFmpeg.</p>
                                </div>

                                <!-- Acciones Masivas -->
                                <div class="mt-8 pt-8 border-t border-white/5">
                                    <div class="p-5 bg-yellow-500/10 border border-yellow-500/20 rounded-3xl">
                                        <h4 class="text-xs font-bold text-yellow-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                                            <span class="material-symbols-outlined text-sm">auto_fix_high</span> Acciones Masivas
                                        </h4>
                                        <p class="text-[10px] text-gray-500 mb-4 leading-relaxed">
                                            Asigna una canción **aleatoria** a todas las tarjetas con un solo clic.
                                        </p>
                                        <button onclick="bulkRandomAudio()" id="bulk-btn" class="w-full bg-yellow-600 border border-yellow-500 text-black font-bold py-3 rounded-xl hover:bg-yellow-500 transition-all flex items-center justify-center gap-2 shadow-lg shadow-yellow-600/20">
                                            Música Aleatoria (Todas)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Hidden Audio element for preview -->
    <audio id="preview-player" preload="auto"></audio>

    <!-- MODAL AUDIO LIBRARY -->
    <div id="audio-modal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-md" onclick="closeAudioLibrary()"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl max-h-[80vh] overflow-hidden">
            <div class="glass rounded-[40px] border-white/10 shadow-2xl flex flex-col h-full bg-[#080808]">
                <div class="p-8 border-b border-white/5 flex justify-between items-center">
                    <h2 class="text-2xl font-bold gold-text">Biblioteca Musical</h2>
                    <button onclick="closeAudioLibrary()" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-8">
                    <div class="relative mb-6">
                        <input type="text" id="audio-search" placeholder="Buscar canciones, artistas..." oninput="loadAudios(1)"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-2xl pl-12 outline-none focus:border-primary/50 text-sm">
                        <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <div id="audios-list" class="space-y-2 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        <!-- Audios will be loaded here -->
                    </div>

                    <div id="audios-pagination" class="mt-6 flex justify-center gap-2"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    .animate-fade-in { animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(212,175,55,0.2); border-radius: 10px; }
    
    .card-selected { border-color: #D4AF37 !important; background: rgba(212,175,55,0.1) !important; }
    .audio-selected { background: rgba(212,175,55,0.1) !important; border-color: rgba(212,175,55,0.3) !important; }
</style>

<script>
    // --- APP STATE ---
    let currentTab = 'analytics';
    let selectedCard = null;
    let selectedAudio = null;
    let audioFiles = [];
    let audioTotal = 0;
    let cardPages = 1;
    let audioPages = 1;

    function switchTab(tab) {
        currentTab = tab;
        document.querySelectorAll('.tab-section').forEach(s => s.classList.add('hidden'));
        document.getElementById('section-' + tab).classList.remove('hidden');
        
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('bg-yellow-600', 'text-black');
            b.classList.add('text-gray-400');
        });
        
        const activeBtn = document.getElementById('tab-' + tab);
        activeBtn.classList.add('bg-yellow-600', 'text-black');
        activeBtn.classList.remove('text-gray-400');

        if (tab === 'music') {
            loadCards(1);
        }
    }

    // --- CARDS LOADING ---
    async function loadCards(page) {
        const limit = document.getElementById('cards-limit').value;
        const rarity = document.getElementById('filter-rarity').value;
        const sort = document.getElementById('sort-cards').value;
        const container = document.getElementById('cards-container');
        container.innerHTML = `<div class="col-span-full py-20 flex justify-center"><div class="animate-spin rounded-full h-8 w-8 border-t-2 border-primary"></div></div>`;
        
        try {
            const resp = await fetch(`api/get_cards_admin.php?page=${page}&limit=${limit}&rarity=${rarity}&sort=${sort}`);
            const data = await resp.json();
            
            if (data.success) {
                container.innerHTML = '';
                data.cards.forEach(card => {
                    const el = document.createElement('div');
                    el.className = `glass p-4 rounded-[32px] border border-white/5 cursor-pointer hover:border-primary/50 transition-all flex flex-col items-center text-center card-item ${selectedCard?.id == card.id ? 'card-selected shadow-[0_0_20px_rgba(212,175,55,0.2)] border-primary/50' : ''}`;
                    el.setAttribute('data-id', card.id);
                    el.onclick = () => selectCard(card, el);
                    
                    // Rarity styling for admin preview
                    const rarityName = card.rarity || 'COMÚN';
                    const rarityCode = rarityName.substring(0, 3).toUpperCase();
                    const displayID = String(card.id).padStart(3, '0');

                    el.innerHTML = `
                        <div class="w-full aspect-[9/16] bg-black/40 rounded-[30px] mb-3 flex items-center justify-center overflow-hidden relative group border border-white/10">
                             <video src="${card.video_url}" class="w-full h-full object-cover pointer-events-none" loop muted playsinline></video>
                             
                             <!-- Top Right Code -->
                             <div class="absolute top-3 right-3 z-20 pointer-events-none">
                                <span class="bg-black/60 backdrop-blur-md border border-white/10 text-[7px] text-primary/80 px-2 py-0.5 rounded font-mono font-bold">
                                    ${rarityCode}-FORJ-${displayID}
                                </span>
                             </div>

                             <!-- Bottom Brand & Name -->
                             <div class="absolute bottom-4 inset-x-0 flex flex-col items-center gap-1 pointer-events-none z-20">
                                <span class="text-[7px] border border-primary/40 bg-black/40 px-3 py-0.5 rounded-full text-primary uppercase font-bold tracking-[0.2em] mb-1 scale-90">${rarityName}</span>
                                <h4 style="font-family: 'Cinzel', serif;" class="text-[12px] font-bold text-white uppercase tracking-widest drop-shadow-[0_0_10px_rgba(0,0,0,0.8)]">${card.title}</h4>
                                <span style="font-family: 'Comfortaa', cursive;" class="text-[9px] text-primary/90 font-bold mb-1">Luumen.mx</span>
                             </div>

                             <!-- TikTok-style Vertical Side Actions (Preview) -->
                             <div class="absolute bottom-16 right-3 flex flex-col gap-3 opacity-60">
                                <div class="w-8 h-8 rounded-full bg-black/60 border border-white/20 flex items-center justify-center scale-75">
                                    <span class="text-[14px]">⚡</span>
                                </div>
                                <div class="w-8 h-8 rounded-full bg-black/60 border border-white/20 flex items-center justify-center scale-75">
                                    <span class="text-[14px]">🔥</span>
                                </div>
                                <div class="w-8 h-8 rounded-full bg-black/60 border border-white/20 flex items-center justify-center scale-75">
                                    <span class="text-[14px]">❤️</span>
                                </div>
                             </div>

                             <!-- Overlay solo en hover -->
                             <div class="active-overlay absolute inset-0 bg-primary/20 opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center">
                                 <span class="material-symbols-outlined text-white text-3xl">play_circle</span>
                             </div>
                        </div>
                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest truncate w-full text-center px-2 group-hover:text-primary transition-colors">${card.title}</p>
                        <p class="text-[8px] text-gray-500 mt-1">${card.audio_url ? '🎵 Audio OK' : '❌ Sin audio'}</p>
                    `;
                    
                    const video = el.querySelector('video');
                    el.onmouseenter = () => { if (selectedCard?.id != card.id) video.play().catch(e => {}); };
                    el.onmouseleave = () => { if (selectedCard?.id != card.id) video.pause(); };

                    container.appendChild(el);
                });

                renderPagination('cards-pagination', page, data.pages, loadCards);
            }
        } catch (e) {
            container.innerHTML = `<div class="col-span-full text-red-500 py-10 text-center">Error al cargar tarjetas</div>`;
        }
    }

    function selectCard(card, element) {
        selectedCard = card;
        
        // Mute all other videos
        document.querySelectorAll('.card-item video').forEach(v => {
            v.muted = true;
            v.volume = 0;
            v.pause();
        });

        document.querySelectorAll('#cards-container > div').forEach(el => el.classList.remove('card-selected'));
        element.classList.add('card-selected');
        
        // Play selected video WITH audio (Original)
        const video = element.querySelector('video');
        if (video) {
            video.muted = false;
            video.volume = 1.0;
            video.currentTime = 0;
            video.play().catch(e => console.error("Error playing video:", e));
        }

        // UNLOCK the right panel
        const editor = document.getElementById('music-editor');
        editor.classList.remove('opacity-50', 'pointer-events-none');
        editor.classList.add('shadow-[0_0_50px_rgba(212,175,55,0.1)]', 'border-primary/20');

        // Show configuration panel
        document.getElementById('card-name').textContent = card.title;
        const audioName = card.audio_url ? card.audio_url.split('/').pop() : 'Sin asignar';
        document.getElementById('current-audio-name').textContent = audioName;

        // Reset music selection if no custom audio assigned yet for this session
        selectedAudio = null; 

        // Load existing config
        document.getElementById('audio-start-val').value = card.audio_start || 0;
        document.getElementById('audio-duration-val').value = card.audio_duration || 6;
        
        // Set volumes from DB or default
        const vVol = card.video_volume !== null ? card.video_volume : 1.0;
        const mVol = card.music_volume !== null ? card.music_volume : 1.0;
        document.getElementById('video-vol-input').value = vVol;
        document.getElementById('music-vol-input').value = mVol;
        updateVolumes();
        
        updateTrimmerProgress();
        document.getElementById('trimmer-container').classList.remove('hidden');
    }

    // --- AUDIO LIBRARY ---
    function openAudioLibrary() {
        document.getElementById('audio-modal').classList.remove('hidden');
        loadAudios(1);
    }

    function closeAudioLibrary() {
        document.getElementById('audio-modal').classList.add('hidden');
    }

    async function loadAudios(page) {
        const search = document.getElementById('audio-search').value;
        const listContainer = document.getElementById('audios-list');
        listContainer.innerHTML = `<div class="py-10 flex justify-center"><div class="animate-spin rounded-full h-8 w-8 border-t-2 border-primary"></div></div>`;

        try {
            const resp = await fetch(`api/get_audios_admin.php?page=${page}&limit=100&search=${search}`);
            const data = await resp.json();

            if (data.success) {
                listContainer.innerHTML = '';
                data.audios.forEach(audio => {
                    const el = document.createElement('div');
                    el.className = `p-4 glass rounded-[24px] border border-white/5 cursor-pointer hover:bg-white/10 flex items-center gap-4 transition-all group`;
                    el.onclick = () => assignAudio(audio);
                    el.innerHTML = `
                        <button class="p-3 bg-primary/10 rounded-xl hover:bg-primary/20 transition-all flex items-center justify-center group/play" onclick="event.stopPropagation(); playQuickPreview(this, '${audio.file_path}')">
                            <span class="material-symbols-outlined text-primary group-hover/play:scale-110 transition-transform preview-icon">play_arrow</span>
                        </button>
                        <div class="flex-grow">
                            <h4 class="font-bold text-sm uppercase tracking-tight group-hover:text-primary transition-colors">${audio.title}</h4>
                            <p class="text-[10px] text-gray-500 font-mono">Audio Original .MP3</p>
                        </div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest bg-white/5 px-2 py-1 rounded-full">Seleccionar</span>
                            <span class="material-symbols-outlined text-sm text-primary">chevron_right</span>
                        </div>
                    `;
                    listContainer.appendChild(el);
                });
                renderPagination('audios-pagination', page, data.pages, loadAudios);
            }
        } catch (e) {
            listContainer.innerHTML = `<p class="text-center text-red-500 py-4">Error al cargar audios</p>`;
        }
    }

    function assignAudio(audio) {
        selectedAudio = audio;
        document.getElementById('current-audio-name').textContent = audio.title;
        
        // Default to first 6 seconds
        document.getElementById('audio-start-val').value = 0;
        document.getElementById('audio-duration-val').value = 6;
        
        closeAudioLibrary();
        updateTrimmerProgress();
    }

    // --- TRIMMER LOGIC ---
    let isDragging = false;
    let startX = 0;
    let currentX = -50; // Position in %
    const trimmerTrack = document.getElementById('trimmer-track');

    if (trimmerTrack) {
        const handleDragStart = (e) => {
            isDragging = true;
            startX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
            trimmerTrack.style.cursor = 'grabbing';
            e.preventDefault();
        };
        const handleDragEnd = () => {
            isDragging = false;
            trimmerTrack.style.cursor = 'grab';
        };
        const handleDragMove = (e) => {
            if (!isDragging) return;
            const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
            const deltaX = (clientX - startX) / 2;
            currentX += deltaX;
            startX = clientX;
            
            if (currentX > 250) currentX = 250; 
            if (currentX < -250) currentX = -250;
            
            trimmerTrack.style.transform = `translateX(${currentX}px)`;
            const startVal = Math.max(0, Math.min(60, 15 - (currentX / 10)));
            document.getElementById('audio-start-val').value = startVal.toFixed(1);
            updateTrimmerInfo();
        };

        trimmerTrack.addEventListener('mousedown', handleDragStart);
        window.addEventListener('mouseup', handleDragEnd);
        window.addEventListener('mousemove', handleDragMove);
        trimmerTrack.addEventListener('touchstart', handleDragStart, { passive: false });
        window.addEventListener('touchend', handleDragEnd);
        window.addEventListener('touchmove', handleDragMove, { passive: false });
    }

    function updateTrimmerInfo() {
        const start = parseFloat(document.getElementById('audio-start-val').value);
        const duration = parseFloat(document.getElementById('audio-duration-val').value);
        document.getElementById('trim-time-info').textContent = `${start.toFixed(1)}s - ${(start+duration).toFixed(1)}s`;
    }

    function updateTrimmerProgress() {
        const start = parseFloat(document.getElementById('audio-start-val').value);
        currentX = -(start * 10);
        if (trimmerTrack) trimmerTrack.style.transform = `translateX(${currentX}%)`;
        updateTrimmerInfo();
    }

    async function saveAudioConfig() {
        if (!selectedCard) return;
        const btn = document.getElementById('save-audio-btn');
        const oldText = btn.textContent;
        btn.textContent = 'Guardando...';
        btn.disabled = true;

        const start = document.getElementById('audio-start-val').value;
        const duration = document.getElementById('audio-duration-val').value;
        const vVol = document.getElementById('video-vol-input').value;
        const mVol = document.getElementById('music-vol-input').value;
        const audioPath = selectedAudio ? selectedAudio.file_path : selectedCard.audio_url;

        try {
            const resp = await fetch('api/update_card_audio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    card_id: selectedCard.id,
                    audio_url: audioPath,
                    audio_start: start,
                    audio_duration: duration,
                    video_volume: vVol,
                    music_volume: mVol
                })
            });
            const data = await resp.json();
            if (data.success) {
                btn.classList.add('bg-green-500', 'text-white');
                btn.textContent = '¡Guardado!';
                setTimeout(() => {
                    btn.classList.remove('bg-green-500', 'text-white');
                    btn.textContent = oldText;
                    btn.disabled = false;
                    loadCards(1);
                }, 2000);
            } else {
                alert('Error: ' + data.error);
                btn.textContent = oldText;
                btn.disabled = false;
            }
        } catch (e) {
            alert('Error de conexión');
            btn.textContent = oldText;
            btn.disabled = false;
        }
    }

    // --- PREVIEW LOGIC ---
    let isPlaying = false;
    let previewTimer = null;
    const previewPlayer = document.getElementById('preview-player');

    function togglePreview() {
        if (!selectedCard && !selectedAudio) return;
        if (isPlaying) stopPreview(); else startPreview();
    }

    function startPreview() {
        const start = parseFloat(document.getElementById('audio-start-val').value);
        const duration = parseFloat(document.getElementById('audio-duration-val').value);
        const url = selectedAudio ? selectedAudio.file_path : selectedCard.audio_url;
        if (!url) return;
        
        const activeVideo = document.querySelector(`.card-item[data-id="${selectedCard.id}"] video`);
        if (activeVideo) {
            activeVideo.muted = false;
            activeVideo.volume = document.getElementById('video-vol-input').value;
            activeVideo.currentTime = 0;
            activeVideo.play();
        }

        previewPlayer.src = url;
        previewPlayer.currentTime = start;
        previewPlayer.volume = document.getElementById('music-vol-input').value;
        previewPlayer.play();
        
        isPlaying = true;
        document.getElementById('preview-icon').textContent = '⏹️';
        document.getElementById('preview-audio-btn').classList.add('bg-blue-600', 'text-white');
        previewTimer = setTimeout(stopPreview, duration * 1000);
    }

    function stopPreview() {
        previewPlayer.pause();
        if (selectedCard) {
            const activeVideo = document.querySelector(`.card-item[data-id="${selectedCard.id}"] video`);
            if (activeVideo) { activeVideo.muted = true; activeVideo.volume = 0; }
        }
        isPlaying = false;
        document.getElementById('preview-icon').textContent = '▶️';
        document.getElementById('preview-audio-btn').classList.remove('bg-blue-600', 'text-white');
        if (previewTimer) clearTimeout(previewTimer);
    }

    function updateVolumes() {
        const vVol = document.getElementById('video-vol-input').value;
        const mVol = document.getElementById('music-vol-input').value;
        document.getElementById('video-vol-text').textContent = Math.round(vVol * 100) + '%';
        document.getElementById('music-vol-text').textContent = Math.round(mVol * 100) + '%';
        if (isPlaying) {
            previewPlayer.volume = mVol;
            const activeVideo = document.querySelector(`.card-item[data-id="${selectedCard.id}"] video`);
            if (activeVideo) activeVideo.volume = vVol;
        }
    }

    let activeQuickPreview = null;
    function playQuickPreview(btn, url) {
        const icon = btn.querySelector('.preview-icon');
        if (isPlaying) stopPreview();
        if (activeQuickPreview === btn && !previewPlayer.paused) {
            previewPlayer.pause();
            icon.textContent = 'play_arrow';
            return;
        }
        if (activeQuickPreview) activeQuickPreview.querySelector('.preview-icon').textContent = 'play_arrow';
        previewPlayer.src = url;
        previewPlayer.currentTime = 0;
        previewPlayer.volume = 0.8;
        previewPlayer.play();
        activeQuickPreview = btn;
        icon.textContent = 'stop';
        previewPlayer.onended = () => { icon.textContent = 'play_arrow'; activeQuickPreview = null; };
    }

    async function bulkRandomAudio() {
        if (!confirm('¿Estás seguro? Esta acción asignará una canción aleatoria a TODAS las tarjetas de la base de datos.')) return;
        const btn = document.getElementById('bulk-btn');
        btn.disabled = true;
        btn.textContent = 'Procesando...';
        try {
            const resp = await fetch('api/bulk_random_audio.php');
            const data = await resp.json();
            if (data.success) { alert(data.message); loadCards(1); }
            else { alert('Error: ' + data.error); }
        } catch (e) { alert('Error en la conexión.'); }
        finally { btn.disabled = false; btn.textContent = 'Música Aleatoria (Todas)'; }
    }

    // --- HELPERS ---
    function renderPagination(id, current, total, callback) {
        const container = document.getElementById(id);
        if (!container || total <= 1) { if(container) container.innerHTML = total <= 1 ? '<span class="text-gray-600 text-[10px] uppercase tracking-widest">Página única</span>' : ''; return; }
        container.innerHTML = '';
        const maxVisible = 5;
        let start = Math.max(1, current - 2);
        let end = Math.min(total, start + maxVisible - 1);
        if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1);

        if (current > 1) {
            const btn = document.createElement('button');
            btn.className = `px-3 py-1 rounded-lg text-xs font-bold bg-white/5 hover:bg-white/10`;
            btn.textContent = '←';
            btn.onclick = () => callback(current - 1);
            container.appendChild(btn);
        }
        for (let i = start; i <= end; i++) {
            const btn = document.createElement('button');
            btn.className = `px-3 py-1 rounded-lg text-xs font-bold transition-all ${i == current ? 'bg-primary text-black' : 'bg-white/5 hover:bg-white/10'}`;
            btn.textContent = i;
            btn.onclick = () => callback(i);
            container.appendChild(btn);
        }
        if (current < total) {
            const btn = document.createElement('button');
            btn.className = `px-3 py-1 rounded-lg text-xs font-bold bg-white/5 hover:bg-white/10`;
            btn.textContent = '→';
            btn.onclick = () => callback(current + 1);
            container.appendChild(btn);
        }
    }

    // --- INITIALIZATION ---
    document.addEventListener('DOMContentLoaded', () => {
        const ctxViews = document.getElementById('viewsChart');
        if (ctxViews) {
            new Chart(ctxViews.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Vistas',
                        data: <?= json_encode($chart_data) ?>,
                        borderColor: '#D4AF37',
                        backgroundColor: 'rgba(212, 175, 55, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    });

    async function renderVideoAction() {
        if (!selectedCard) {
            alert("Por favor selecciona una tarjeta primero.");
            return;
        }

        const btn = document.getElementById('render-btn');
        const oldHTML = btn.innerHTML;
        
        if (!confirm("Se va a generar una versión oficial del video con Logo, Código y Rareza grabados. ¿Deseas continuar?")) return;

        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span> Procesando...';
        btn.disabled = true;

        try {
            const resp = await fetch(`api/render_video.php?card_id=${selectedCard.id}`);
            const data = await resp.json();

            if (data.status === 'success') {
                window.open(data.url, '_blank');
                alert("¡Video generado con éxito! Se ha abierto en una nueva pestaña.");
            } else {
                alert("Error: " + data.message);
            }
        } catch (e) {
            alert("Error de conexión con el motor de renderizado.");
        } finally {
            btn.innerHTML = oldHTML;
            btn.disabled = false;
        }
    }
</script>
</body>
</html>
