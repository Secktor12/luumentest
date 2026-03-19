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

// Manejo de Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($usuario && $password) {
        $stmt = $pdo->prepare("SELECT * FROM admninistrador WHERE usuario = ? AND contraseña = ?");
        $stmt->execute([$usuario, $password]);
        $admin = $stmt->fetch();

        if ($admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $admin['usuario'];
            header('Location: reporte.php');
            exit;
        }
        else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
    else {
        $error = 'Por favor, completa todos los campos.';
    }
}

// Verificar si está logueado
$isLogged = isset($_SESSION['admin_logged_in']);

// Si está logueado, obtener suscriptores con paginación
$suscriptores = [];
$totalSuscriptores = 0;
$limit = 50;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

if ($isLogged) {
    // Contar total
    $totalSuscriptores = $pdo->query("SELECT COUNT(*) FROM suscriptores")->fetchColumn();
    $totalPages = ceil($totalSuscriptores / $limit);

    // Obtener página
    $stmt = $pdo->prepare("SELECT * FROM suscriptores ORDER BY fecha DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $suscriptores = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Suscriptores - Luumen</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0a0a0a; color: #fff; margin: 0; padding: 20px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .card { background: #161616; padding: 2rem; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.5); width: 100%; max-width: 400px; margin-top: 50px; }
        h1, h2 { color: #00d2ff; text-align: center; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #ccc; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #333; background: #222; color: white; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%); border: none; color: white; font-weight: bold; border-radius: 6px; cursor: pointer; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        .error { color: #ff4d4d; margin-bottom: 1rem; text-align: center; }
        
        /* Estilos Tabla */
        .dashboard { width: 100%; max-width: 1000px; margin-top: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: #161616; border-radius: 12px; overflow: hidden; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #222; }
        th { background: #222; color: #00d2ff; }
        tr:hover { background: #1e1e1e; }
        .pagination { margin-top: 20px; display: flex; gap: 10px; justify-content: center; }
        .page-link { padding: 8px 12px; background: #222; color: white; text-decoration: none; border-radius: 4px; }
        .page-link.active { background: #00d2ff; color: black; font-weight: bold; }
        .logout-btn { color: #888; text-decoration: none; font-size: 0.9rem; border: 1px solid #333; padding: 5px 15px; border-radius: 20px; }
        .logout-btn:hover { background: #333; color: #fff; }
    </style>
</head>
<body>

<?php if (!$isLogged): ?>
    <div class="card">
        <h2>Luumen Admin</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php
    endif; ?>
        <form method="POST">
            <input type="hidden" name="login" value="1">
            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="usuario" required autofocus>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
    </div>
<?php
else: ?>
    <div class="dashboard">
        <div class="header">
            <h1>Suscriptores (Total: <?php echo $totalSuscriptores; ?>)</h1>
            <a href="?logout=1" class="logout-btn">Cerrar Sesión</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Correo Electrónico</th>
                    <th>Fecha (CDMX)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($suscriptores): ?>
                    <?php foreach ($suscriptores as $s): ?>
                        <tr>
                            <td><?php echo $s['id_suscriptor']; ?></td>
                            <td><?php echo htmlspecialchars($s['correo']); ?></td>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($s['fecha'])); ?></td>
                        </tr>
                    <?php
        endforeach; ?>
                <?php
    else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding: 30px; color: #555;">No hay registros aún.</td>
                    </tr>
                <?php
    endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?p=<?php echo $i; ?>" class="page-link <?php echo($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php
        endfor; ?>
            </div>
        <?php
    endif; ?>
    </div>
<?php
endif; ?>

</body>
</html>
