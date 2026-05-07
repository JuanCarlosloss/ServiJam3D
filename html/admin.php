<?php
require_once 'config.php';
requireAdmin();

$db = getDB();

// Gestión de usuarios
$userErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['user_action'] ?? '';

    if ($act === 'add_user') {
        $nombre   = trim($_POST['u_nombre'] ?? '');
        $email    = trim($_POST['u_email'] ?? '');
        $password = $_POST['u_password'] ?? '';
        $role     = in_array($_POST['u_role'] ?? '', ['admin', 'user']) ? $_POST['u_role'] : 'user';

        if (!$nombre || !$email || !$password) {
            $userErr = 'Todos los campos son obligatorios.';
        } elseif (strlen($password) < 6) {
            $userErr = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            try {
                $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $userErr = 'Ese email ya está registrado.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('INSERT INTO users (nombre, email, password, role) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$nombre, $email, $hash, $role]);
                    header('Location: admin.php#usuarios');
                    exit;
                }
            } catch (Exception $e) {
                $userErr = 'Error al crear usuario.';
            }
        }
    } elseif ($act === 'change_role') {
        $uid  = (int)($_POST['uid'] ?? 0);
        $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
        if ($uid && $uid !== (int)$_SESSION['user_id']) {
            $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$role, $uid]);
            header('Location: admin.php#usuarios');
            exit;
        } else {
            $userErr = 'No puedes cambiar tu propio rol.';
        }
    }
}

$stmt  = $db->query('SELECT j.*, u.nombre, u.email FROM print_jobs j JOIN users u ON j.user_id = u.id ORDER BY j.created_at DESC');
$jobs  = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt  = $db->query('SELECT id, nombre, email, role FROM users ORDER BY nombre ASC');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusLabel = [
    'pendiente'   => ['label' => 'Pendiente',   'color' => '#f0a500'],
    'aceptado'    => ['label' => 'Aceptado',    'color' => '#00c853'],
    'rechazado'   => ['label' => 'Rechazado',   'color' => '#ff1744'],
    'imprimiendo' => ['label' => 'Imprimiendo', 'color' => '#2979ff'],
    'cancelado'   => ['label' => 'Cancelado',   'color' => '#ff9800'],
    'completado'  => ['label' => 'Completado',  'color' => '#00e676'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Servijam 3D</title>
    <link rel="icon" type="image/png" href="imgs/logo.png">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<nav>
    <h1><img src="imgs/logo.png" alt="Servijam 3D" class="logo-img"> Servijam 3D</h1>
    <div class="nav-right">
        <span class="nav-user">👤 Admin</span>
        <a href="logout.php" class="nav-link">Cerrar sesión</a>
    </div>
</nav>
<div class="tab-bar">
    <button class="tab-btn" data-tab="solicitudes" onclick="switchTab('solicitudes')">📋 Solicitudes</button>
    <button class="tab-btn" data-tab="usuarios" onclick="switchTab('usuarios')">👥 Usuarios</button>
    <button class="tab-btn" data-tab="impresora" onclick="switchTab('impresora')">🖨️ Impresora</button>
</div>
<div class="container">

<!-- ======= TAB: SOLICITUDES ======= -->
<div class="tab-panel" id="tab-solicitudes">
    <!-- Stats -->
    <?php
    $counts = array_fill_keys(array_keys($statusLabel), 0);
    foreach ($jobs as $j) { if (isset($counts[$j['status']])) $counts[$j['status']]++; }
    ?>
    <div class="stats">
        <?php foreach ($statusLabel as $key => $s): ?>
        <div class="stat">
            <div class="num" style="color:<?= $s['color'] ?>"><?= $counts[$key] ?></div>
            <div class="lbl"><?= $s['label'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="filter-bar">
        <button class="filter-btn active" data-filter="all" onclick="setFilter('all')">Todas</button>
        <button class="filter-btn" data-filter="pendiente" onclick="setFilter('pendiente')" style="color:#f0a500;border-color:#f0a500">Pendiente</button>
        <button class="filter-btn" data-filter="aceptado" onclick="setFilter('aceptado')" style="color:#00c853;border-color:#00c853">Aceptado</button>
        <button class="filter-btn" data-filter="imprimiendo" onclick="setFilter('imprimiendo')" style="color:#2979ff;border-color:#2979ff">Imprimiendo</button>
        <button class="filter-btn" data-filter="rechazado" onclick="setFilter('rechazado')" style="color:#ff1744;border-color:#ff1744">Rechazado</button>
        <button class="filter-btn" data-filter="completado" onclick="setFilter('completado')" style="color:#00e676;border-color:#00e676">Completado</button>
        <!-- Estado de impresora inline -->
        <div id="printer-status-bar" style="display:none;margin-left:auto;background:#13131a;border:1px solid #2979ff44;border-radius:10px;padding:8px 16px;min-width:220px;max-width:360px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                <span id="psb-label" style="font-size:12px;font-weight:600;color:#2979ff">🖨️ Imprimiendo…</span>
                <span id="psb-pct" style="font-size:12px;color:#aaa"></span>
            </div>
            <div style="background:#1e1e2e;border-radius:6px;height:5px;overflow:hidden">
                <div id="psb-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#7c3aed,#2979ff);border-radius:6px;transition:width .6s ease"></div>
            </div>
        </div>
    </div>

    <div id="emptyMsg" class="empty" style="display:none">No hay solicitudes con este filtro.</div>

    <?php foreach ($jobs as $job):
        $s = $statusLabel[$job['status']] ?? ['label' => $job['status'], 'color' => '#aaa'];
    ?>
    <div class="job-card" id="job-<?= $job['id'] ?>" data-status="<?= htmlspecialchars($job['status']) ?>" data-filename="<?= htmlspecialchars($job['filename']) ?>">
        <div class="job-header">
            <div>
                <div class="job-filename" style="display:flex;align-items:center;gap:10px">
                    <?php if (!empty($job['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $job['color'])): ?>
                    <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:<?= htmlspecialchars($job['color']) ?>;border:2px solid #555;flex-shrink:0" title="Color: <?= htmlspecialchars($job['color']) ?>"></span>
                    <?php endif; ?>
                    📄 <?= htmlspecialchars($job['filename']) ?>
                </div>
                <div class="job-meta">
                    👤 <?= htmlspecialchars($job['nombre']) ?> (<?= htmlspecialchars($job['email']) ?>)
                    &nbsp;·&nbsp; 🕐 <?= $job['created_at'] ?>
                    <?php if (!empty($job['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $job['color'])): ?>
                    &nbsp;·&nbsp; <span style="display:inline-flex;align-items:center;gap:5px">🎨 <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= htmlspecialchars($job['color']) ?>;border:1px solid #555"></span> <?= htmlspecialchars($job['color']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($job['notas']): ?>
                <div class="job-meta" style="margin-top:4px">📝 <?= htmlspecialchars($job['notas']) ?></div>
                <?php endif; ?>
            </div>
            <span class="badge" style="background:<?= $s['color'] ?>22;color:<?= $s['color'] ?>;border:1px solid <?= $s['color'] ?>"><?= $s['label'] ?></span>
        </div>

        <?php if ($job['status'] === 'pendiente'): ?>
        <div class="actions">
            <textarea id="notas-<?= $job['id'] ?>" placeholder="Notas opcionales..."></textarea>
            <button class="btn btn-ok"  onclick="doAction(<?= $job['id'] ?>, 'aceptar')">✅ Aceptar</button>
            <button class="btn btn-err" onclick="doAction(<?= $job['id'] ?>, 'rechazar')">❌ Rechazar</button>
        </div>
        <?php elseif ($job['status'] === 'imprimiendo'): ?>
        <div style="margin-top:14px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:12px;font-weight:600;color:#2979ff">🖨️ En impresión</span>
                <span class="card-pct" style="font-size:12px;color:#aaa"></span>
            </div>
            <div style="background:#1e1e2e;border-radius:6px;height:6px;overflow:hidden">
                <div class="card-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#7c3aed,#2979ff);border-radius:6px;transition:width .6s ease"></div>
            </div>
        </div>
        <div class="actions" style="margin-top:14px">
            <button class="btn btn-info" onclick="doAction(<?= $job['id'] ?>, 'completar')">🏁 Marcar completado</button>
        </div>
        <?php elseif ($job['status'] === 'cancelado'): ?>
        <div class="actions" style="margin-top:14px">
            <button class="btn btn-ok"  onclick="doAction(<?= $job['id'] ?>, 'reimprimir')">&#128260; Reimprimir</button>
            <button class="btn btn-info" onclick="printerCmd('clear')">&#10005; Despejar</button>
        </div>
        <?php elseif ($job['status'] === 'aceptado'): ?>
        <div id="aceptado-print-<?= $job['id'] ?>" style="display:none;margin-top:14px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:12px;font-weight:600;color:#2979ff">🖨️ En impresión</span>
                <span class="card-pct" style="font-size:12px;color:#aaa"></span>
            </div>
            <div style="background:#1e1e2e;border-radius:6px;height:6px;overflow:hidden">
                <div class="card-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#7c3aed,#2979ff);border-radius:6px;transition:width .6s ease"></div>
            </div>
        </div>
        <div class="actions" style="margin-top:14px">
            <button class="btn btn-info" onclick="doAction(<?= $job['id'] ?>, 'completar')">🏁 Marcar completado</button>
        </div>
        <?php endif; ?>

        <div class="result-msg" id="msg-<?= $job['id'] ?>"></div>
    </div>
    <?php endforeach; ?>

    <div class="pagination" id="pagination"></div>
</div><!-- /tab-solicitudes -->

<!-- ======= TAB: IMPRESORA ======= -->
<div class="tab-panel" id="tab-impresora">
    <p class="section-title">🖨️ Control de impresora</p>

    <!-- Estado + controles de impresión -->
    <div class="job-card" style="margin-bottom:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
            <div>
                <div style="font-size:13px;color:#aaa;margin-bottom:4px">Estado</div>
                <span id="pr-state-badge" style="font-size:14px;font-weight:700;color:#888">⏳ Conectando…</span>
            </div>
            <div style="flex:1;min-width:160px">
                <div style="font-size:11px;color:#555;margin-bottom:4px" id="pr-filename"></div>
                <div style="background:#1e1e2e;border-radius:6px;height:8px;overflow:hidden">
                    <div id="pr-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#7c3aed,#2979ff);border-radius:6px;transition:width .8s ease"></div>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button id="btn-pause"  class="btn btn-info"  onclick="printerCmd('pause')">⏸️ Pausar</button>
                <button id="btn-resume" class="btn btn-ok"    onclick="printerCmd('resume')" style="display:none">▶️ Reanudar</button>
            </div>
        </div>
    </div>

    <!-- Temperaturas -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px">
        <!-- Extrusor -->
        <div class="job-card">
            <div style="font-size:13px;color:#aaa;margin-bottom:10px;font-weight:600">🔥 Extrusor</div>
            <div style="display:flex;align-items:flex-end;gap:6px;margin-bottom:14px">
                <span id="ext-actual" style="font-size:36px;font-weight:700;color:#ff6b35;line-height:1">—</span>
                <span style="font-size:16px;color:#555;margin-bottom:4px">°C</span>
                <span style="font-size:13px;color:#555;margin-bottom:5px">/ <span id="ext-target">—</span>°C</span>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <input id="ext-input" type="number" min="0" max="300" placeholder="ej. 200" style="width:90px;background:#0a0a0f;border:1px solid #2a2a3d;border-radius:8px;color:#fff;padding:7px 10px;font-size:13px">
                <button class="btn btn-primary" style="padding:7px 16px;font-size:12px" onclick="setTemp('extruder', document.getElementById('ext-input').value)">Aplicar</button>
                <button class="btn" style="padding:7px 12px;font-size:12px;border-color:#555;color:#aaa" onclick="setTemp('extruder',0)">OFF</button>
            </div>
        </div>
        <!-- Cama -->
        <div class="job-card">
            <div style="font-size:13px;color:#aaa;margin-bottom:10px;font-weight:600">🛏️ Cama</div>
            <div style="display:flex;align-items:flex-end;gap:6px;margin-bottom:14px">
                <span id="bed-actual" style="font-size:36px;font-weight:700;color:#2979ff;line-height:1">—</span>
                <span style="font-size:16px;color:#555;margin-bottom:4px">°C</span>
                <span style="font-size:13px;color:#555;margin-bottom:5px">/ <span id="bed-target">—</span>°C</span>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <input id="bed-input" type="number" min="0" max="120" placeholder="ej. 60" style="width:90px;background:#0a0a0f;border:1px solid #2a2a3d;border-radius:8px;color:#fff;padding:7px 10px;font-size:13px">
                <button class="btn btn-primary" style="padding:7px 16px;font-size:12px" onclick="setTemp('heater_bed', document.getElementById('bed-input').value)">Aplicar</button>
                <button class="btn" style="padding:7px 12px;font-size:12px;border-color:#555;color:#aaa" onclick="setTemp('heater_bed',0)">OFF</button>
            </div>
        </div>
    </div>

    <!-- Presets rápidos -->
    <div class="job-card">
        <div style="font-size:13px;color:#aaa;margin-bottom:12px;font-weight:600">⚡ Presets rápidos</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn btn-info" style="font-size:12px" onclick="applyPreset(200,60)">PLA (200/60)</button>
            <button class="btn btn-info" style="font-size:12px" onclick="applyPreset(240,80)">PETG (240/80)</button>
            <button class="btn btn-info" style="font-size:12px" onclick="applyPreset(250,100)">ABS (250/100)</button>
            <button class="btn" style="font-size:12px;border-color:#555;color:#aaa" onclick="applyPreset(0,0)">🧊 Todo OFF</button>
        </div>
    </div>
</div><!-- /tab-impresora -->

<!-- ======= TAB: USUARIOS ======= -->
<div class="tab-panel" id="tab-usuarios">
    <p class="section-title">👥 Gestión de usuarios</p>

    <?php if ($userErr): ?>
        <div class="u-msg u-msg-err"><?= htmlspecialchars($userErr) ?></div>
    <?php endif; ?>

    <!-- Tabla de usuarios -->
    <div class="job-card" style="overflow-x:auto">
            <table class="u-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td colspan="2">
                            <form method="POST" style="display:flex;align-items:center;gap:8px">
                                <input type="hidden" name="user_action" value="change_role">
                                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                <select name="role" class="u-select" <?= $u['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                    <option value="user"  <?= $u['role'] === 'user'  ? 'selected' : '' ?>>Usuario</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <button type="submit" class="btn btn-info" style="padding:5px 14px;font-size:12px">Guardar</button>
                                <?php else: ?>
                                <span style="font-size:12px;color:#555">(tú)</span>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Agregar usuario -->
        <div class="job-card" style="margin-top:16px">
            <p style="font-size:14px;color:#aaa;font-weight:600;margin-bottom:16px">➕ Agregar usuario</p>
            <form method="POST">
                <input type="hidden" name="user_action" value="add_user">
                <div class="u-grid">
                    <div>
                        <label class="u-label">Nombre</label>
                        <input type="text" name="u_nombre" class="u-input" required>
                    </div>
                    <div>
                        <label class="u-label">Email</label>
                        <input type="email" name="u_email" class="u-input" required>
                    </div>
                    <div>
                        <label class="u-label">Contraseña</label>
                        <input type="password" name="u_password" class="u-input" required minlength="6">
                    </div>
                    <div>
                        <label class="u-label">Rol</label>
                        <select name="u_role" class="u-select" style="width:100%">
                            <option value="user">Usuario</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:16px">➕ Crear usuario</button>
            </form>
        </div>

</div><!-- /tab-usuarios -->

</div><!-- /container -->

<script>
function switchTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    document.querySelector('[data-tab="' + name + '"]').classList.add('active');
    location.hash = name;
}

// Restore tab from hash, default to solicitudes
(function() {
    const hash = location.hash.replace('#', '');
    switchTab(['solicitudes','usuarios'].includes(hash) ? hash : 'solicitudes');
})();

function doAction(id, action) {
    const notas = document.getElementById('notas-' + id)?.value || '';

    const labels = { aceptar: 'Aceptar', rechazar: 'Rechazar', completar: 'Completar' };
    const icons  = { aceptar: 'question', rechazar: 'warning', completar: 'question' };
    const colors = { aceptar: '#7c3aed', rechazar: '#dc2626', completar: '#7c3aed' };

    Swal.fire({
        title: '¿' + labels[action] + ' esta solicitud?',
        icon: icons[action],
        showCancelButton: true,
        confirmButtonText: labels[action],
        cancelButtonText: 'Cancelar',
        confirmButtonColor: colors[action],
        cancelButtonColor: '#374151',
        background: '#1a1a1a',
        color: '#fff',
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', action);
        fd.append('job_id', id);
        fd.append('notas', notas);
        fetch('action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                Swal.fire({
                    title: res.ok ? '¡Listo!' : 'Error',
                    text: res.msg || res.error,
                    icon: res.ok ? 'success' : 'error',
                    confirmButtonColor: '#7c3aed',
                    background: '#1a1a1a',
                    color: '#fff',
                    timer: res.ok ? 1500 : undefined,
                    showConfirmButton: !res.ok,
                }).then(() => { if (res.ok) location.reload(); });
            });
    });
}

// ---- Filter + Pagination ----
const PAGE_SIZE   = 10;
let currentFilter = 'all';
let currentPage   = 1;

function setFilter(f) {
    currentFilter = f;
    currentPage   = 1;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.toggle('active', b.dataset.filter === f));
    renderJobs();
}

function getFilteredCards() {
    const all = Array.from(document.querySelectorAll('#tab-solicitudes .job-card'));
    return currentFilter === 'all' ? all : all.filter(c => c.dataset.status === currentFilter);
}

function renderJobs() {
    const cards      = getFilteredCards();
    const total      = cards.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * PAGE_SIZE;
    const end   = start + PAGE_SIZE;
    document.querySelectorAll('#tab-solicitudes .job-card').forEach(c => c.style.display = 'none');
    cards.forEach((c, i) => { c.style.display = (i >= start && i < end) ? '' : 'none'; });
    document.getElementById('emptyMsg').style.display = total === 0 ? 'block' : 'none';
    renderPagination(totalPages);
}

function renderPagination(totalPages) {
    const el = document.getElementById('pagination');
    if (totalPages <= 1) { el.innerHTML = ''; return; }
    let html = `<button class="page-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}>&#8249;</button>`;
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="page-btn${i===currentPage?' active':''}" onclick="goPage(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" onclick="goPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}>&#8250;</button>`;
    el.innerHTML = html;
}

function goPage(p) {
    const cards      = getFilteredCards();
    const totalPages = Math.max(1, Math.ceil(cards.length / PAGE_SIZE));
    if (p < 1 || p > totalPages) return;
    currentPage = p;
    renderJobs();
}

renderJobs();

// ---- Printer status + temps polling ----
const stateLabels = {
    printing:   { text: '🖨️ Imprimiendo', color: '#2979ff' },
    paused:     { text: '⏸️ Pausado',      color: '#f0a500' },
    complete:   { text: '✅ Completado',   color: '#00e676' },
    cancelled:  { text: '⚠️ Cancelado',   color: '#ff9800' },
    error:      { text: '❌ Error',        color: '#ff1744' },
    standby:    { text: '💤 En espera',   color: '#888'    },
    offline:    { text: '🔌 Offline',     color: '#555'    },
};

async function pollPrinter() {
    try {
        const res = await fetch('printer_status.php').then(r => r.json());
        const s    = res.state || 'offline';
        const info = stateLabels[s] || { text: s, color: '#888' };
        const pct  = res.progress ?? 0;

        // --- barra inline en filtros ---
        const psb     = document.getElementById('printer-status-bar');
        const psbLbl  = document.getElementById('psb-label');
        const psbPct  = document.getElementById('psb-pct');
        const psbBar  = document.getElementById('psb-bar');
        const active  = (s === 'printing' || s === 'paused');
        psb.style.display = active ? '' : 'none';
        if (active) {
            psb.style.borderColor = s === 'paused' ? '#f0a50044' : '#2979ff44';
            psbLbl.style.color    = s === 'paused' ? '#f0a500'   : '#2979ff';
            psbLbl.textContent    = (s === 'paused' ? '⏸️ Pausado' : '🖨️ Imprimiendo') + (res.filename ? ' — ' + res.filename : '');
            psbPct.textContent    = pct + '%';
            psbBar.style.width    = pct + '%';
        }

        // --- barras en tarjetas imprimiendo y aceptado con ese archivo ---
        document.querySelectorAll('.job-card[data-status="imprimiendo"], .job-card[data-status="aceptado"]').forEach(card => {
            const isThisFile = card.dataset.filename === res.filename;
            const bar = card.querySelector('.card-bar');
            const lbl = card.querySelector('.card-pct');
            // mostrar barra de progreso en tarjetas aceptado si Moonraker está imprimiendo ese archivo
            if (card.dataset.status === 'aceptado') {
                const printBlock = document.getElementById('aceptado-print-' + card.id.replace('job-',''));
                if (printBlock) printBlock.style.display = (isThisFile && (s === 'printing' || s === 'paused')) ? '' : 'none';
            }
            if (bar && (card.dataset.status === 'imprimiendo' || isThisFile)) bar.style.width  = pct + '%';
            if (lbl && (card.dataset.status === 'imprimiendo' || isThisFile)) lbl.textContent  = pct + '%';
        });

        // --- panel impresora: estado ---
        const prBadge = document.getElementById('pr-state-badge');
        if (prBadge) {
            prBadge.textContent  = info.text + (s === 'printing' ? ' ' + pct + '%' : '');
            prBadge.style.color  = info.color;
        }
        const prBar  = document.getElementById('pr-bar');
        const prFile = document.getElementById('pr-filename');
        if (prBar)  prBar.style.width  = pct + '%';
        if (prFile) prFile.textContent = res.filename || '';

        // botones pause/resume
        const btnPause  = document.getElementById('btn-pause');
        const btnResume = document.getElementById('btn-resume');
        if (btnPause && btnResume) {
            const printing = s === 'printing';
            const paused   = s === 'paused';
            btnPause.style.display  = printing ? '' : 'none';
            btnResume.style.display = paused   ? '' : 'none';
        }

        // --- temperaturas ---
        const t = res.temps || {};
        const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val !== null ? val : '—'; };
        setEl('ext-actual', t.extruder_actual);
        setEl('ext-target', t.extruder_target);
        setEl('bed-actual', t.bed_actual);
        setEl('bed-target', t.bed_target);

        // color de temperatura
        const extEl = document.getElementById('ext-actual');
        if (extEl && t.extruder_actual !== null) {
            extEl.style.color = t.extruder_actual > 50 ? '#ff6b35' : '#aaa';
        }
        const bedEl = document.getElementById('bed-actual');
        if (bedEl && t.bed_actual !== null) {
            bedEl.style.color = t.bed_actual > 30 ? '#2979ff' : '#aaa';
        }

        if (res.updated) {
            Swal.fire({
                title: '✅ Impresión completada',
                text: 'El archivo ' + res.filename + ' ha terminado. Se ha notificado al usuario.',
                icon: 'success',
                confirmButtonColor: '#7c3aed',
                background: '#1a1a1a',
                color: '#fff',
            }).then(() => location.reload());
        }
        if (res.cancelled) {
            Swal.fire({
                title: '⚠️ Impresión cancelada',
                text: 'El archivo ' + res.filename + ' ha sido cancelado. Se ha notificado al usuario.',
                icon: 'warning',
                confirmButtonColor: '#7c3aed',
                background: '#1a1a1a',
                color: '#fff',
            }).then(() => location.reload());
        }
    } catch (e) {
        console.warn('Moonraker offline', e);
    }
}

// ---- Printer controls ----
async function printerCmd(cmd) {
    const res = await fetch('printer_control.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cmd=' + cmd,
    }).then(r => r.json());
    if (!res.ok) {
        Swal.fire({ title: 'Error', text: res.error || 'No se pudo ejecutar', icon: 'error', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#7c3aed' });
    } else {
        pollPrinter();
        if (cmd === 'cancel') setTimeout(pollPrinter, 4000);
    }
}

function printerCmdConfirm(cmd, title, text) {
    Swal.fire({
        title, text, icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'No',
        confirmButtonColor: '#ff1744',
        cancelButtonColor: '#555',
        background: '#1a1a1a', color: '#fff',
    }).then(r => { if (r.isConfirmed) printerCmd(cmd); });
}

async function setTemp(target, temp) {
    temp = parseFloat(temp);
    if (isNaN(temp)) return;
    const res = await fetch('printer_control.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cmd=set_temp&target=' + encodeURIComponent(target) + '&temp=' + temp,
    }).then(r => r.json());
    if (!res.ok) Swal.fire({ title: 'Error', text: res.error || 'No se pudo aplicar', icon: 'error', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#7c3aed' });
    else pollPrinter();
}

function applyPreset(extTemp, bedTemp) {
    setTemp('extruder',   extTemp);
    setTemp('heater_bed', bedTemp);
}

pollPrinter();
setInterval(pollPrinter, 5000);

</script>
</body>
</html>