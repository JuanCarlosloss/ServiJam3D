<?php
ob_start();
require_once 'config.php';
requireAdmin();

ob_clean();
header('Content-Type: application/json');

$cmd = $_POST['cmd'] ?? '';

function moonrakerPost($path, $payload = [], $timeout = 10) {
    $url  = MOONRAKER_URL . $path;
    $json = json_encode($payload);
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $res    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // Moonraker devuelve {"result": ...} en respuestas OK
    $decoded = $res ? json_decode($res, true) : null;
    $ok = ($status >= 200 && $status < 300) || isset($decoded['result']);
    return ['body' => $res, 'status' => $status, 'ok' => $ok];
}

function moonrakerGet($path) {
    $url = MOONRAKER_URL . $path;
    $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
    return @file_get_contents($url, false, $ctx);
}

function moonrakerDelete($path) {
    $url = MOONRAKER_URL . $path;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_TIMEOUT        => 5,
    ]);
    $res    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $res, 'status' => $status];
}

switch ($cmd) {
    case 'pause':
        $r = moonrakerPost('/printer/print/pause');
        echo json_encode(['ok' => $r['ok']]);
        break;

    case 'resume':
        $r = moonrakerPost('/printer/print/resume');
        echo json_encode(['ok' => $r['ok']]);
        break;

    case 'clear':
        $r = moonrakerPost('/printer/gcode/script', ['script' => 'SDCARD_RESET_FILE']);
        echo json_encode(['ok' => $r['ok']]);
        break;

    case 'set_temp':
        $target = $_POST['target'] ?? ''; // 'extruder' | 'heater_bed'
        $temp   = (float)($_POST['temp'] ?? 0);
        if (!in_array($target, ['extruder', 'heater_bed'])) {
            echo json_encode(['ok' => false, 'error' => 'Target inválido']);
            break;
        }
        if ($temp < 0 || $temp > 300) {
            echo json_encode(['ok' => false, 'error' => 'Temperatura fuera de rango (0-300)']);
            break;
        }
        $r = moonrakerPost('/printer/gcode/script', [
            'script' => "SET_HEATER_TEMPERATURE HEATER={$target} TARGET={$temp}"
        ]);
        echo json_encode(['ok' => $r['ok']]);
        break;

    case 'list_files':
        $raw  = moonrakerGet('/server/files/list?root=gcodes');
        $data = $raw ? json_decode($raw, true) : null;
        if (!isset($data['result']) || !is_array($data['result'])) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo obtener la lista']);
            break;
        }
        $files = array_map(fn($f) => [
            'name'     => $f['path']     ?? '',
            'size'     => $f['size']     ?? 0,
            'modified' => $f['modified'] ?? 0,
        ], $data['result']);
        usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
        echo json_encode(['ok' => true, 'files' => $files]);
        break;

    case 'delete_file':
        $filename = basename($_POST['filename'] ?? '');
        if (!$filename) {
            echo json_encode(['ok' => false, 'error' => 'Archivo no especificado']);
            break;
        }
        $r = moonrakerDelete('/server/files/gcodes/' . rawurlencode($filename));
        echo json_encode(['ok' => in_array($r['status'], [200, 204])]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Comando desconocido']);
}
