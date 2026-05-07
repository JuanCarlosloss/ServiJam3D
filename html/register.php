<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: user.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$nombre || !$email || !$password) {
        $error = 'Todos los campos son obligatorios.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Ese email ya está registrado.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO users (nombre, email, password) VALUES (?, ?, ?)');
                $stmt->execute([$nombre, $email, $hash]);
                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Error al registrar.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro — Servijam 3D</title>
    <link rel="icon" type="image/png" href="imgs/logo.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-wrap">
<div class="auth-card">
    <div class="auth-logo"><img src="imgs/logo.png" alt="Servijam 3D" class="logo-img"> Servijam 3D</div>
    <p class="auth-sub">Crear cuenta nueva</p>
    <?php if ($error): ?>
        <div class="alert alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-ok">✅ <?= $success ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="field">
            <label>Nombre</label>
            <input type="text" name="nombre" required autofocus placeholder="Tu nombre">
        </div>
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" required placeholder="tu@email.com">
        </div>
        <div class="field">
            <label>Contraseña</label>
            <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
        </div>
        <div class="field">
            <label>Confirmar contraseña</label>
            <input type="password" name="confirm" required placeholder="Repite la contraseña">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Crear cuenta →</button>
    </form>
    <div class="auth-link">¿Ya tienes cuenta? <a href="index.php">Inicia sesión</a></div>
</div>
</div>
</body>
</html>