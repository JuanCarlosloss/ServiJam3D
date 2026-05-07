<?php
session_start();

define('DB_HOST', 'db');
define('DB_NAME', 'print3d');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MOONRAKER_URL', getenv('MOONRAKER_URL') ?: 'http://host.docker.internal:7125');

// SMTP Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_FROM_NAME', 'Servijam 3D Print');
define('ADMIN_EMAIL', 'your_email@gmail.com');

function mailTemplate(string $title, string $preheader, string $bodyHtml): string {
    return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:\'Segoe UI\',Arial,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#f4f4f4;">' . htmlspecialchars($preheader) . '</div>
<!-- wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 0;">
<tr><td align="center">
  <!-- card -->
  <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#1a1a1a;border-radius:12px;overflow:hidden;">
    <!-- header -->
    <tr>
      <td style="background:#7c3aed;padding:28px 40px;text-align:center;">
        <p style="margin:0;font-size:28px;font-weight:700;color:#fff;letter-spacing:1px;">🖨️ Servijam 3D</p>
        <p style="margin:6px 0 0;font-size:13px;color:#e9d5ff;letter-spacing:2px;text-transform:uppercase;">Impresión 3D Profesional</p>
      </td>
    </tr>
    <!-- body -->
    <tr>
      <td style="padding:36px 40px;color:#e0e0e0;font-size:15px;line-height:1.7;">
        ' . $bodyHtml . '
      </td>
    </tr>
    <!-- footer -->
    <tr>
      <td style="background:#111;padding:20px 40px;text-align:center;border-top:1px solid #2a2a2a;">
        <p style="margin:0;font-size:12px;color:#555;">Este correo fue enviado automáticamente por <strong style="color:#7c3aed;">Servijam 3D</strong>. Por favor no respondas a este mensaje.</p>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body>
</html>';
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("ALTER TABLE print_jobs ADD COLUMN IF NOT EXISTS color VARCHAR(7) NOT NULL DEFAULT ''");
    }
    return $pdo;
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}
