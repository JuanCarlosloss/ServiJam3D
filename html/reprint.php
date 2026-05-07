<?php
ob_start();
require_once 'config.php';
requireLogin();

ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$filename = trim($_POST['filename'] ?? '');
$color    = trim($_POST['color'] ?? '');

// Sanitize: only allow alphanumeric, dash, underscore, dot, forward slash (for subdirs)
if (!$filename || !preg_match('/^[\w\-. \/]+\.gcode$/i', $filename)) {
    echo json_encode(['ok' => false, 'error' => 'Nombre de archivo inválido']);
    exit;
}
// Prevent path traversal
if (strpos($filename, '..') !== false) {
    echo json_encode(['ok' => false, 'error' => 'Nombre de archivo inválido']);
    exit;
}

// Validate hex color
if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    $color = '';
}

// Verify the file exists in Moonraker
$moonrakerFiles = getMoonrakerFiles();
if (!in_array($filename, $moonrakerFiles, true)) {
    echo json_encode(['ok' => false, 'error' => 'El archivo no existe en Moonraker']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare('INSERT INTO print_jobs (user_id, filename, status, color) VALUES (?, ?, "pendiente", ?)');
    $stmt->execute([$_SESSION['user_id'], $filename, $color]);

    // Fetch user info
    $uStmt = $db->prepare('SELECT nombre, email FROM users WHERE id = ?');
    $uStmt->execute([$_SESSION['user_id']]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);

    $colorInfo = $color ? "<tr><td style='padding:6px 0;color:#aaa;font-size:13px'>Color del filamento</td><td style='padding:6px 0;font-weight:600;'><span style='display:inline-block;width:14px;height:14px;border-radius:50%;background:{$color};vertical-align:middle;border:1px solid #555;margin-right:6px'></span>{$color}</td></tr>" : '';

    // Email al usuario: confirmacion
    try { sendMail(
        $user['email'],
        '📬 Solicitud recibida - Servijam 3D',
        mailTemplate(
            'Solicitud recibida',
            'Hemos recibido tu solicitud de impresión 3D.',
            "<p style='margin:0 0 20px;font-size:18px;font-weight:600;color:#ff6b00;'>¡Hola, {$user['nombre']}!</p>
            <p style='margin:0 0 16px;'>Hemos recibido tu solicitud de impresión y está <strong style='color:#f0a500;'>pendiente de revisión</strong>. Te notificaremos en cuanto sea aceptada o rechazada.</p>
            <table cellpadding='0' cellspacing='0' style='width:100%;background:#222;border-radius:8px;padding:16px 20px;margin-bottom:24px;'>
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Archivo</td><td style='padding:6px 0;font-weight:600;'>📄 {$filename}</td></tr>
              {$colorInfo}
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Estado</td><td style='padding:6px 0;'><span style='background:#f0a50022;color:#f0a500;border:1px solid #f0a500;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>Pendiente</span></td></tr>
            </table>
            <p style='margin:0;font-size:13px;color:#888;'>Si tienes alguna duda, contacta con el equipo de Servijam 3D.</p>"
        )
    ); } catch (\Throwable $e) {}

    // Email al admin
    try { sendMail(
        ADMIN_EMAIL,
        '🔔 Nueva solicitud de impresión - ' . $user['nombre'],
        mailTemplate(
            'Nueva solicitud',
            'Ha llegado una nueva solicitud de impresión.',
            "<p style='margin:0 0 20px;font-size:18px;font-weight:600;color:#ff6b00;'>Nueva solicitud de impresión</p>
            <p style='margin:0 0 16px;'>El usuario <strong>{$user['nombre']}</strong> ha enviado una nueva solicitud.</p>
            <table cellpadding='0' cellspacing='0' style='width:100%;background:#222;border-radius:8px;padding:16px 20px;margin-bottom:24px;'>
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Usuario</td><td style='padding:6px 0;font-weight:600;'>{$user['nombre']}</td></tr>
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Email</td><td style='padding:6px 0;'>{$user['email']}</td></tr>
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Archivo</td><td style='padding:6px 0;font-weight:600;'>📄 {$filename}</td></tr>
              {$colorInfo}
            </table>
            <p style='margin:0;'>Accede al panel de administración para revisar y aceptar o rechazar la solicitud.</p>"
        )
    ); } catch (\Throwable $e) {}

    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Error al crear solicitud']);
}
