<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'user.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nombre']  = $user['nombre'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'user.php'));
            exit;
        } else {
            $error = 'Email o contraseña incorrectos.';
        }
    } catch (Exception $e) {
        $error = 'Error de conexión.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servijam 3D — Login</title>
    <link rel="icon" type="image/png" href="imgs/logo.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-wrap">
<div class="auth-card">
    <div class="auth-logo"><img src="imgs/logo.png" alt="Servijam 3D" class="logo-img"> Servijam 3D</div>
    <p class="auth-sub">Inicia sesión para continuar</p>
    <?php if ($error): ?>
        <div class="alert alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" required autofocus placeholder="tu@email.com">
        </div>
        <div class="field">
            <label>Contraseña</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Entrar →</button>
    </form>
    <div class="auth-link">¿No tienes cuenta? <a href="register.php">Regístrate</a></div>
</div>
</div>
</body>
</html>