<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../api/db.php';

// Obtener suscriptores
$stmt = $pdo->query("SELECT * FROM suscriptores ORDER BY fecha DESC");
$suscriptores = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Luumen</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0a0a0a; color: #fff; margin: 0; padding: 2rem; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        h1 { color: #00d2ff; }
        .logout { color: #888; text-decoration: none; font-size: 0.9rem; }
        .logout:hover { color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #161616; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
        th, td { padding: 1.25rem; text-align: left; border-bottom: 1px solid #222; }
        th { background: #222; color: #00d2ff; font-weight: 600; }
        tr:hover { background: #1e1e1e; }
        .empty { text-align: center; padding: 3rem; color: #555; }
        .stats { margin-bottom: 1.5rem; font-size: 1.1rem; color: #aaa; }
        .stats span { color: #fff; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Suscriptores</h1>
            <a href="logout.php" class="logout">Cerrar Sesión</a>
        </div>

        <div class="stats">
            Total de suscriptores: <span><?php echo count($suscriptores); ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Correo Electrónico</th>
                    <th>Fecha de Suscripción</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($suscriptores): ?>
                    <?php foreach ($suscriptores as $s): ?>
                        <tr>
                            <td><?php echo $s['id_suscriptor']; ?></td>
                            <td><?php echo htmlspecialchars($s['correo']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($s['fecha'])); ?></td>
                        </tr>
                    <?php
    endforeach; ?>
                <?php
else: ?>
                    <tr>
                        <td colspan="3" class="empty">Aún no hay suscriptores.</td>
                    </tr>
                <?php
endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
