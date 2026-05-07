<?php
require_once 'config.php';
requireLogin();

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM print_jobs WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusLabel = [
    'pendiente'   => ['label' => 'Pendiente',   'color' => '#f0a500'],
    'aceptado'    => ['label' => 'Aceptado',    'color' => '#00c853'],
    'rechazado'   => ['label' => 'Rechazado',   'color' => '#ff1744'],
    'imprimiendo' => ['label' => 'Imprimiendo', 'color' => '#2979ff'],
    'completado'  => ['label' => 'Completado',  'color' => '#00e676'],
];

$gcodeFiles = getMoonrakerFiles();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel — Servijam 3D</title>
    <link rel="icon" type="image/png" href="imgs/logo.png">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<nav>
    <h1><img src="imgs/logo.png" alt="Servijam 3D" class="logo-img"> Servijam 3D</h1>
    <div class="nav-right">
        <span class="nav-user">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span>
        <a href="logout.php" class="nav-link">Cerrar sesión</a>
    </div>
</nav>
<div class="container">
    <!-- Upload -->
    <div class="upload-card">
        <h2>📤 Subir archivo .gcode</h2>
        <div class="drop-area" id="dropArea">
            <div class="drop-icon">📁</div>
            <p>Arrastra tu archivo .gcode aquí o haz clic para seleccionar</p>
            <input type="file" id="fileInput" accept=".gcode" style="display:none">
            <button class="btn btn-primary" style="margin-top:14px" onclick="document.getElementById('fileInput').click()">Seleccionar archivo</button>
        </div>
        <div id="fileName" style="margin-top:10px;color:#aaa;font-size:13px"></div>
        <div class="color-row" id="colorRowUpload" style="display:none">
            <span class="color-label">🎨 Color del filamento:</span>
            <input type="color" id="colorUpload" value="#7c3aed" title="Elige el color del filamento">
            <span id="colorUploadLabel" style="font-size:13px;color:#aaa">#7c3aed</span>
        </div>
        <div class="progress" id="progress"><div class="progress-bar" id="progressBar"></div></div>
        <div class="msg" id="msg"></div>
        <button class="btn btn-primary" id="uploadBtn" style="display:none;margin-top:12px" onclick="doUpload()">📤 Enviar solicitud</button>
    </div>

    <!-- Archivos disponibles -->
    <div class="upload-card" style="margin-bottom:30px">
        <h2>📂 Usar archivo existente</h2>
        <?php if (empty($gcodeFiles)): ?>
            <div class="empty" style="padding:16px">No hay archivos subidos todavía.</div>
        <?php else: ?>
        <p style="color:#888;font-size:13px;margin-bottom:4px">Selecciona un archivo ya disponible y solicita una nueva impresión.</p>
        <div class="file-grid" id="fileGrid">
            <?php foreach ($gcodeFiles as $f): ?>
            <div class="file-chip" onclick="selectChip(this, '<?= addslashes(htmlspecialchars($f)) ?>')">📄 <?= htmlspecialchars($f) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="color-row" id="reprintColorRow" style="display:none">
            <span class="color-label">🎨 Color del filamento:</span>
            <input type="color" id="reprintColor" value="#7c3aed" title="Elige el color del filamento">
            <span id="reprintColorLabel" style="font-size:13px;color:#aaa">#7c3aed</span>
            <button class="btn btn-primary" style="margin-top:0" onclick="doReprint()">📤 Solicitar impresión</button>
        </div>
        <div class="msg" id="reprintMsg"></div>
        <?php endif; ?>
    </div>

    <!-- Historial -->
    <div class="filter-bar" id="histFilterBar">
        <button class="filter-btn active" data-filter="all" onclick="setHistFilter('all')">Todas</button>
        <button class="filter-btn" data-filter="pendiente" onclick="setHistFilter('pendiente')" style="color:#f0a500;border-color:#f0a500">Pendiente</button>
        <button class="filter-btn" data-filter="aceptado" onclick="setHistFilter('aceptado')" style="color:#00c853;border-color:#00c853">Aceptado</button>
        <button class="filter-btn" data-filter="rechazado" onclick="setHistFilter('rechazado')" style="color:#ff1744;border-color:#ff1744">Rechazado</button>
        <button class="filter-btn" data-filter="completado" onclick="setHistFilter('completado')" style="color:#00e676;border-color:#00e676">Completado</button>
    </div>
    <p class="section-title">📋 Mis solicitudes</p>
    <?php if (empty($jobs)): ?>
        <div class="empty">Aún no tienes solicitudes.</div>
    <?php else: ?>
    <div id="histEmpty" class="empty" style="display:none">No hay solicitudes con este filtro.</div>
    <table id="histTable" class="hist">
        <thead>
            <tr><th>Archivo</th><th>Color</th><th>Estado</th><th>Notas</th><th>Fecha</th></tr>
        </thead>
        <tbody>
        <?php foreach ($jobs as $job): 
            $s = $statusLabel[$job['status']] ?? ['label' => $job['status'], 'color' => '#aaa'];
        ?>
            <tr data-status="<?= htmlspecialchars($job['status']) ?>">
                <td><?= htmlspecialchars($job['filename']) ?></td>
                <td><?php if ($job['color'] && preg_match('/^#[0-9a-fA-F]{6}$/', $job['color'])): ?><span class="color-swatch" style="background:<?= htmlspecialchars($job['color']) ?>" title="<?= htmlspecialchars($job['color']) ?>"></span><?php else: ?><span style="color:#555">—</span><?php endif; ?></td>
                <td><span class="badge" style="background:<?= $s['color'] ?>22;color:<?= $s['color'] ?>;border:1px solid <?= $s['color'] ?>"><?= $s['label'] ?></span></td>
                <td><?= htmlspecialchars($job['notas'] ?? '-') ?></td>
                <td style="color:var(--text-muted)"><?= $job['created_at'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pagination" id="histPagination"></div>
    <?php endif; ?>
</div>
<script>
const dropArea   = document.getElementById('dropArea');
const fileInput  = document.getElementById('fileInput');
const fileNameEl = document.getElementById('fileName');
const uploadBtn  = document.getElementById('uploadBtn');
const msg        = document.getElementById('msg');
let selectedFile = null;

dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('dragover'); });
dropArea.addEventListener('dragleave', () => dropArea.classList.remove('dragover'));
dropArea.addEventListener('drop', e => {
    e.preventDefault(); dropArea.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f) setFile(f);
});
fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });

function setFile(f) {
    if (!f.name.endsWith('.gcode')) { showMsg('Solo se permiten archivos .gcode', false); return; }
    selectedFile = f;
    fileNameEl.textContent = '📄 ' + f.name;
    uploadBtn.style.display = 'inline-block';
    document.getElementById('colorRowUpload').style.display = 'flex';
    msg.textContent = '';
}

document.getElementById('colorUpload').addEventListener('input', function() {
    document.getElementById('colorUploadLabel').textContent = this.value;
});

function doUpload() {
    if (!selectedFile) return;
    const fd = new FormData();
    fd.append('file', selectedFile);
    fd.append('color', document.getElementById('colorUpload').value); // hex e.g. #7c3aed
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload.php');
    document.getElementById('progress').style.display = 'block';
    xhr.upload.onprogress = e => {
        if (e.lengthComputable) document.getElementById('progressBar').style.width = (e.loaded/e.total*100) + '%';
    };
    xhr.onload = () => {
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.ok) {
                Swal.fire({ title: '¡Solicitud enviada!', text: 'Está pendiente de revisión.', icon: 'success', confirmButtonColor: '#7c3aed', background: '#1a1a1a', color: '#fff', timer: 1800, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ title: 'Error', text: res.error, icon: 'error', confirmButtonColor: '#7c3aed', background: '#1a1a1a', color: '#fff' });
            }
        } catch(e) {
            Swal.fire({ title: 'Error', text: 'Respuesta inesperada del servidor.', icon: 'error', confirmButtonColor: '#7c3aed', background: '#1a1a1a', color: '#fff' });
        }
    };
    xhr.send(fd);
}

let selectedChipFile = null;
function selectChip(el, filename) {
    document.querySelectorAll('#fileGrid .file-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    selectedChipFile = filename;
    document.getElementById('reprintColorRow').style.display = 'flex';
    document.getElementById('reprintMsg').textContent = '';
}

document.getElementById('reprintColor').addEventListener('input', function() {
    document.getElementById('reprintColorLabel').textContent = this.value;
});

function doReprint() {
    if (!selectedChipFile) return;
    const color  = document.getElementById('reprintColor').value;
    const msgEl  = document.getElementById('reprintMsg');
    const fd     = new FormData();
    fd.append('filename', selectedChipFile);
    fd.append('color', color);
    fetch('reprint.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                Swal.fire({ title: '¡Solicitud enviada!', text: 'Pendiente de aprobación.', icon: 'success', confirmButtonColor: '#7c3aed', background: '#1a1a1a', color: '#fff', timer: 1800, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ title: 'Error', text: res.error, icon: 'error', confirmButtonColor: '#7c3aed', background: '#1a1a1a', color: '#fff' });
            }
        })
        .catch(() => {
            Swal.fire({ title: 'Error', text: 'Error de servidor al enviar la solicitud.', icon: 'error', confirmButtonColor: '#7c3aed', background: '#1a1a1a', color: '#fff' });
        });
}

function showMsg(text, ok) {
    msg.textContent = text;
    msg.className = 'msg ' + (ok ? 'ok' : 'err');
}

// ---- Historial Filter + Pagination ----
const HIST_PAGE_SIZE = 10;
let histFilter = 'all';
let histPage   = 1;

function setHistFilter(f) {
    histFilter = f;
    histPage   = 1;
    document.querySelectorAll('#histFilterBar .filter-btn').forEach(b => b.classList.toggle('active', b.dataset.filter === f));
    renderHist();
}

function getFilteredRows() {
    const all = Array.from(document.querySelectorAll('#histTable tbody tr'));
    return histFilter === 'all' ? all : all.filter(r => r.dataset.status === histFilter);
}

function renderHist() {
    const rows       = getFilteredRows();
    const total      = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / HIST_PAGE_SIZE));
    if (histPage > totalPages) histPage = totalPages;
    const start = (histPage - 1) * HIST_PAGE_SIZE;
    const end   = start + HIST_PAGE_SIZE;
    document.querySelectorAll('#histTable tbody tr').forEach(r => r.style.display = 'none');
    rows.forEach((r, i) => { r.style.display = (i >= start && i < end) ? '' : 'none'; });
    const emptyEl = document.getElementById('histEmpty');
    const tableEl = document.getElementById('histTable');
    if (emptyEl) emptyEl.style.display = total === 0 ? 'block' : 'none';
    if (tableEl) tableEl.style.display  = total === 0 ? 'none'  : '';
    renderHistPagination(totalPages);
}

function renderHistPagination(totalPages) {
    const el = document.getElementById('histPagination');
    if (!el || totalPages <= 1) { if (el) el.innerHTML = ''; return; }
    let html = `<button class="page-btn" onclick="goHistPage(${histPage-1})" ${histPage===1?'disabled':''}>&#8249;</button>`;
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="page-btn${i===histPage?' active':''}" onclick="goHistPage(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" onclick="goHistPage(${histPage+1})" ${histPage===totalPages?'disabled':''}>&#8250;</button>`;
    el.innerHTML = html;
}

function goHistPage(p) {
    const rows       = getFilteredRows();
    const totalPages = Math.max(1, Math.ceil(rows.length / HIST_PAGE_SIZE));
    if (p < 1 || p > totalPages) return;
    histPage = p;
    renderHist();
}

if (document.getElementById('histTable')) renderHist();
</script>
</body>
</html>