<?php
ob_start();
require_once 'config.php';
requireAdmin();

ob_clean();
header('Content-Type: application/json');

// Consulta Moonraker: estado de impresión + temperaturas
$url = MOONRAKER_URL . '/printer/objects/query?print_stats&display_status&extruder&heater_bed';
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_HTTPGET        => true,
]);
$raw    = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$raw || $status < 200 || $status >= 300) {
    echo json_encode(['ok' => false, 'state' => 'offline', 'error' => 'Moonraker no responde']);
    exit;
}

$data = json_decode($raw, true);
if (!isset($data['result']['status']['print_stats'])) {
    echo json_encode(['ok' => false, 'state' => 'offline', 'error' => 'Respuesta inesperada de Moonraker']);
    exit;
}

$ps       = $data['result']['status']['print_stats'];
$state    = $ps['state']    ?? 'standby';
$filename = $ps['filename'] ?? '';
$progress = isset($data['result']['status']['display_status']['progress'])
              ? round($data['result']['status']['display_status']['progress'] * 100, 1)
              : null;

// Temperaturas
$ext = $data['result']['status']['extruder']   ?? [];
$bed = $data['result']['status']['heater_bed'] ?? [];
$temps = [
    'extruder_actual'  => isset($ext['temperature']) ? round($ext['temperature'], 1) : null,
    'extruder_target'  => isset($ext['target'])       ? round($ext['target'], 1)       : null,
    'bed_actual'       => isset($bed['temperature'])  ? round($bed['temperature'], 1)  : null,
    'bed_target'       => isset($bed['target'])        ? round($bed['target'], 1)        : null,
];

// Si la impresora termina (complete) o cancela (cancelled), buscar el job activo
$updated   = false;
$cancelled = false;

// Si Moonraker está imprimiendo, actualizar el job "aceptado" coincidente a "imprimiendo"
if ($state === 'printing' && $filename !== '') {
    $db2  = getDB();
    $chk  = $db2->prepare('SELECT id FROM print_jobs WHERE status = "aceptado" AND filename = ? LIMIT 1');
    $chk->execute([$filename]);
    if ($row = $chk->fetch(PDO::FETCH_ASSOC)) {
        $db2->prepare('UPDATE print_jobs SET status = "imprimiendo" WHERE id = ?')
            ->execute([$row['id']]);
    }
}

if (in_array($state, ['complete', 'cancelled']) && $filename !== '') {
    $db       = getDB();
    $newStatus = $state === 'complete' ? 'completado' : 'cancelado';
    $stmt = $db->prepare(
        'SELECT j.*, u.email, u.nombre FROM print_jobs j JOIN users u ON j.user_id = u.id
         WHERE j.status = "imprimiendo" AND j.filename = ? ORDER BY j.created_at DESC LIMIT 1'
    );
    $stmt->execute([$filename]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($job) {
        $stmt2 = $db->prepare('UPDATE print_jobs SET status = ? WHERE id = ?');
        $stmt2->execute([$newStatus, $job['id']]);

        $colorInfo = !empty($job['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $job['color'])
            ? "<tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Color</td><td style='padding:6px 0;font-weight:600;'><span style='display:inline-block;width:14px;height:14px;border-radius:50%;background:{$job['color']};vertical-align:middle;border:1px solid #555;margin-right:6px'></span>{$job['color']}</td></tr>"
            : '';

        if ($state === 'complete') {
            $updated = true;
            try {
                sendMail(
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
                        <p style='margin:0;font-size:13px;color:#888;'>Gracias por confiar en <strong style='color:#7c3aed;'>Servijam 3D</strong>.</p>"
                    )
                );
            } catch (\Throwable $e) {}
            // Aviso al admin
            try {
                sendMail(
                    ADMIN_EMAIL,
                    '🏁 Impresión completada: ' . $job['filename'] . ' - Servijam 3D',
                    mailTemplate(
                        'Impresión completada',
                        'Una impresión ha terminado correctamente.',
                        "<p style='margin:0 0 20px;font-size:18px;font-weight:600;color:#00e676;'>Impresión completada</p>
                        <p style='margin:0 0 16px;'>El archivo de <strong>{$job['nombre']}</strong> ({$job['email']}) ha terminado de imprimirse.</p>
                        <table cellpadding='0' cellspacing='0' style='width:100%;background:#222;border-radius:8px;padding:16px 20px;margin-bottom:24px;'>
                          <tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Archivo</td><td style='padding:6px 0;font-weight:600;'>📄 {$job['filename']}</td></tr>
                          {$colorInfo}
                          <tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Usuario</td><td style='padding:6px 0;'>{$job['nombre']} ({$job['email']})</td></tr>
                        </table>"
                    )
                );
            } catch (\Throwable $e) {}
        } else {
            $cancelled = true;
            try {
                sendMail(
                    $job['email'],
                    '⚠️ Tu impresión ha sido cancelada - Servijam 3D',
                    mailTemplate(
                        'Impresión cancelada',
                        'Tu impresión ha sido cancelada mientras estaba en proceso.',
                        "<p style='margin:0 0 20px;font-size:18px;font-weight:600;color:#ff9800;'>Impresión cancelada</p>
                        <p style='margin:0 0 16px;'>Hola <strong>{$job['nombre']}</strong>, lamentamos informarte que tu impresión ha sido <strong style='color:#ff9800;'>cancelada</strong> mientras estaba en proceso.</p>
                        <table cellpadding='0' cellspacing='0' style='width:100%;background:#222;border-radius:8px;padding:16px 20px;margin-bottom:24px;'>
                          <tr><td style='padding:6px 0;color:#aaa;font-size:13px;width:140px;'>Archivo</td><td style='padding:6px 0;font-weight:600;'>📄 {$job['filename']}</td></tr>
                          {$colorInfo}
                          <tr><td style='padding:6px 0;color:#aaa;font-size:13px;'>Estado</td><td style='padding:6px 0;'><span style='background:#ff980022;color:#ff9800;border:1px solid #ff9800;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>Cancelado</span></td></tr>
                        </table>
                        <p style='margin:0;font-size:13px;color:#888;'>Si lo deseas puedes volver a enviar el archivo. Gracias por confiar en <strong style='color:#7c3aed;'>Servijam 3D</strong>.</p>"
                    )
                );
            } catch (\Throwable $e) {}
        } // end else cancelled

    } // end if $job

    // Siempre enviar SDCARD_RESET_FILE cuando Moonraker reporta cancelled para volver a standby
    if ($state === 'cancelled') {
        $ch = curl_init(MOONRAKER_URL . '/printer/gcode/script');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['script' => 'SDCARD_RESET_FILE']),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 5,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
} // end if state complete/cancelled

echo json_encode([
    'ok'        => true,
    'state'     => $state,
    'filename'  => $filename,
    'progress'  => $progress,
    'temps'     => $temps,
    'updated'   => $updated,
    'cancelled' => $cancelled,
]);
