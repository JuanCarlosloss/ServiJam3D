<?php
session_start();

define('DB_HOST', 'db');
define('DB_NAME', 'print3d');
define('DB_USER', 'print3d_user');
define('DB_PASS', 'print3d_pass');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MOONRAKER_URL', getenv('MOONRAKER_URL') ?: 'http://host.docker.internal:7125');

// SMTP Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'servijam3d@gmail.com');
define('SMTP_PASS', 'TU_APP_PASSWORD_AQUI');
define('SMTP_FROM_NAME', 'Servijam 3D Print');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: user.php');
        exit;
    }
}

function sendMail($to, $subject, $body) {
    require_once __DIR__ . '/vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function uploadToMoonraker($filename, $localPath) {
    $logFile = __DIR__ . '/upload_debug.log';
    $url = MOONRAKER_URL . '/server/files/upload?root=gcodes';

    if (!file_exists($localPath)) {
        file_put_contents($logFile, "❌ No existe: $localPath\n", FILE_APPEND);
        return false;
    }

    $cmd = "curl -s -X POST -F \"file=@$localPath\" \"$url\"";
    file_put_contents($logFile, "[CMD] $cmd\n", FILE_APPEND);
    exec($cmd . " 2>&1", $output, $status);
    file_put_contents($logFile, "[OUTPUT] " . implode("\n", $output) . "\n[STATUS] $status\n", FILE_APPEND);
    return $status === 0;
}

function startPrint($filename) {
    $url     = MOONRAKER_URL . '/printer/print/start';
    $payload = json_encode(['filename' => $filename]);
    $cmd     = "curl -s -X POST -H \"Content-Type: application/json\" -d '$payload' \"$url\"";
    exec($cmd . " 2>&1", $output, $status);
    return $status === 0;
}