<?php
ob_start();
require_once 'config.php';
requireAdmin();

ob_clean();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$job_id = (int)($_POST['job_id'] ?? 0);
$notas  = trim($_POST['notas'] ?? '');

if (!$job_id) {
    echo json_encode(['ok' => false, 'error' => 'Job inválido']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT j.*, u.email, u.nombre FROM print_jobs j JOIN users u ON j.user_id = u.id WHERE j.id = ?');
$stmt->execute([$job_id]);
$job  = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    echo json_encode(['ok' => false, 'error' => 'Job no encontrado']);
    exit;
}

if ($action === 'aceptar') {
    $localPath = UPLOAD_DIR . $job['filename'];

    // Solo subir a Moonraker si el archivo existe localmente (upload nuevo)
    // Si es reprint el archivo ya está en Moonraker, solo hay que iniciar impresión
    if (file_exists($localPath)) {
        if (!uploadToMoonraker($job['filename'], $localPath)) {
            echo json_encode(['ok' => false, 'error' => 'Error al subir a Moonraker']);
            exit;
        }
    }

    if (!startPrint($job['filename'])) {
        echo json_encode(['ok' => false, 'error' => 'Error al iniciar impresión']);
        exit;
    }

    $stmt = $db->prepare('UPDATE print_jobs SET status = "aceptado", notas = ? WHERE id = ?');
    $stmt->execute([$notas, $job_id]);

    $colorInfo = !empty($job['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $job['color'])
        ? "<tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Color</td><td style='padding:6px 0;font-weight:600;'><span style='display:inline-block;width:14px;height:14px;border-radius:50%;background:{$job['color']};vertical-align:middle;border:1px solid #555;margin-right:6px'></span>{$job['color']}</td></tr>"
        : '';

    try { sendMail(
        $job['email'],
        '✅ Tu impresión ha sido aceptada - Servijam 3D',
        mailTemplate(
            'Solicitud aceptada',
            'Tu solicitud de impresión ha sido aceptada.',
            "<p style='margin:0 0 20px;font-size:18px;font-weight:600;color:#00c853;'>¡Solicitud aceptada!</p>
            <p style='margin:0 0 16px;'>Hola <strong>{$job['nombre']}</strong>, tu solicitud ha sido <strong style='color:#00c853;'>aceptada</strong> y el archivo ya está siendo impreso. Te avisaremos cuando esté listo.</p>
            <table cellpadding='0' cellspacing='0' style='width:100%;background:#222;border-radius:8px;padding:16px 20px;margin-bottom:24px;'>
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Archivo</td><td style='padding:6px 0;font-weight:600;'>📄 {$job['filename']}</td></tr>
              {$colorInfo}
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Estado</td><td style='padding:6px 0;'><span style='background:#00c85322;color:#00c853;border:1px solid #00c853;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>Imprimiendo</span></td></tr>
              " . ($notas ? "<tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Nota</td><td style='padding:6px 0;'>{$notas}</td></tr>" : "") . "
            </table>
            <p style='margin:0;font-size:13px;color:#888;'>Gracias por confiar en <strong style='color:#ff6b00;'>Servijam 3D</strong>.</p>"
        )
    ); } catch (\Throwable $e) {}

    echo json_encode(['ok' => true, 'msg' => 'Impresión iniciada']);

} elseif ($action === 'rechazar') {
    $stmt = $db->prepare('UPDATE print_jobs SET status = "rechazado", notas = ? WHERE id = ?');
    $stmt->execute([$notas, $job_id]);

    $colorInfo = !empty($job['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $job['color'])
        ? "<tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Color</td><td style='padding:6px 0;font-weight:600;'><span style='display:inline-block;width:14px;height:14px;border-radius:50%;background:{$job['color']};vertical-align:middle;border:1px solid #555;margin-right:6px'></span>{$job['color']}</td></tr>"
        : '';

    try { sendMail(
        $job['email'],
        '❌ Tu solicitud ha sido rechazada - Servijam 3D',
        mailTemplate(
            'Solicitud rechazada',
            'Tu solicitud de impresión ha sido rechazada.',
            "<p style='margin:0 0 20px;font-size:18px;font-weight:600;color:#ff1744;'>Solicitud rechazada</p>
            <p style='margin:0 0 16px;'>Hola <strong>{$job['nombre']}</strong>, lamentamos informarte que tu solicitud ha sido <strong style='color:#ff1744;'>rechazada</strong>.</p>
            <table cellpadding='0' cellspacing='0' style='width:100%;background:#222;border-radius:8px;padding:16px 20px;margin-bottom:24px;'>
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Archivo</td><td style='padding:6px 0;font-weight:600;'>📄 {$job['filename']}</td></tr>
              {$colorInfo}
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Estado</td><td style='padding:6px 0;'><span style='background:#ff174422;color:#ff1744;border:1px solid #ff1744;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>Rechazado</span></td></tr>
            </table>
            " . ($notas ? "<div style='background:#2a0a0a;border-left:4px solid #ff1744;border-radius:6px;padding:14px 18px;margin-bottom:20px;'><p style='margin:0 0 6px;font-size:12px;color:#ff6666;font-weight:600;text-transform:uppercase;letter-spacing:1px;'>Motivo del rechazo</p><p style='margin:0;color:#ffcccc;font-size:14px;'>{$notas}</p></div>" : "<p style='margin:0 0 16px;color:#aaa;font-size:13px;'>No se ha especificado un motivo. Si tienes dudas, contacta con nosotros.</p>") . "
            <p style='margin:0;font-size:13px;color:#888;'>Puedes enviar un nuevo archivo si lo deseas. Gracias por confiar en <strong style='color:#ff6b00;'>Servijam 3D</strong>.</p>"
        )
    ); } catch (\Throwable $e) {}

    echo json_encode(['ok' => true, 'msg' => 'Solicitud rechazada']);

} elseif ($action === 'reimprimir') {
    if (!startPrint($job['filename'])) {
        echo json_encode(['ok' => false, 'error' => 'Error al iniciar impresión']);
        exit;
    }

    $stmt = $db->prepare('UPDATE print_jobs SET status = "imprimiendo" WHERE id = ?');
    $stmt->execute([$job_id]);

    echo json_encode(['ok' => true, 'msg' => 'Reimpresión iniciada']);

} elseif ($action === 'completar') {
    $stmt = $db->prepare('UPDATE print_jobs SET status = "completado" WHERE id = ?');
    $stmt->execute([$job_id]);

    $colorInfo = !empty($job['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $job['color'])
        ? "<tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Color</td><td style='padding:6px 0;font-weight:600;'><span style='display:inline-block;width:14px;height:14px;border-radius:50%;background:{$job['color']};vertical-align:middle;border:1px solid #555;margin-right:6px'></span>{$job['color']}</td></tr>"
        : '';

    try { sendMail(
        $job['email'],
        '🏁 ¡Tu impresión está lista! - Servijam 3D',
        mailTemplate(
            '¡Lista para recoger!',
            'Tu impresión 3D ha terminado y está lista para recoger.',
            "<p style='margin:0 0 20px;font-size:18px;font-weight:600;color:#00e676;'>¡Tu impresión está lista!</p>
            <p style='margin:0 0 16px;'>Hola <strong>{$job['nombre']}</strong>, ¡buenas noticias! Tu archivo ha terminado de imprimirse y ya puedes pasar a recogerlo.</p>
            <table cellpadding='0' cellspacing='0' style='width:100%;background:#222;border-radius:8px;padding:16px 20px;margin-bottom:24px;'>
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Archivo</td><td style='padding:6px 0;font-weight:600;'>📄 {$job['filename']}</td></tr>
              {$colorInfo}
              <tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Estado</td><td style='padding:6px 0;'><span style='background:#00e67622;color:#00e676;border:1px solid #00e676;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>Completado</span></td></tr>
            </table>
            <p style='margin:0;font-size:13px;color:#888;'>Gracias por confiar en <strong style='color:#ff6b00;'>Servijam 3D</strong>. ¡Esperamos verte pronto!</p>"
        )
    ); } catch (\Throwable $e) {}

    echo json_encode(['ok' => true, 'msg' => 'Marcado como completado']);

} else {
    echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
}