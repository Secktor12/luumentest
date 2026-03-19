<?php
session_start();
require_once '../api/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($usuario && $password) {
        $stmt = $pdo->prepare("SELECT * FROM admninistrador WHERE usuario = ? AND contraseña = ?");
        $stmt->execute([$usuario, $password]);
        $admin = $stmt->fetch();

        if ($admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $admin['usuario'];
            header('Location: dashboard.php');
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Luumen</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f0f0f; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: #1a1a1a; padding: 2rem; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.5); width: 100%; max-width: 400px; }
        h2 { text-align: center; margin-bottom: 2rem; color: #00d2ff; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #ccc; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #333; background: #222; color: white; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%); border: none; color: white; font-weight: bold; border-radius: 6px; cursor: pointer; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        .error { color: #ff4d4d; margin-bottom: 1rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Luumen Admin</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php
endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="usuario" required>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
