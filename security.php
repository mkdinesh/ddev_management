<?php
/**
 * Privacy & Security Page - save the system password used to run ddev
 */
require_once __DIR__ . '/api/lib.php';
session_start();
$saved = !empty($_SESSION['ddev_password']);
session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy &amp; Security - DDEV Management</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fa-solid fa-layer-group"></i>
            <span>DDEV Manager</span>
        </div>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
        <a href="terminal.php" class="nav-item">
            <i class="fa-solid fa-terminal"></i>
            <span>Terminal</span>
        </a>
        <a href="filemanager.php" class="nav-item">
            <i class="fa-solid fa-folder-open"></i>
            <span>File Manager</span>
        </a>
        <a href="index.php" class="nav-item">
            <i class="fa-solid fa-database"></i>
            <span>DB Update</span>
        </a>
        <a href="security.php" class="nav-item active">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Privacy &amp; Security</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <span><i class="fa-solid fa-circle" style="color:#22c55e;font-size:8px;"></i> DDEV Active</span>
    </div>
</div>

<div class="main" id="main">
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle-top" onclick="toggleSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1 class="page-title">
                <i class="fa-solid fa-shield-halved"></i> Privacy &amp; Security
            </h1>
        </div>
        <div class="topbar-right">
            <span class="time-display" id="clock"></span>
        </div>
    </header>

    <div class="content">
        <div class="card sec-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-key"></i> System Password</h2>
                <p>
                    DDEV commands (export, import, start, cache clear, updb) run as user
                    <code><?= htmlspecialchars(DDEV_USER) ?></code>. Enter that user's login password.
                    It is checked, then kept only in your server session until you log out of the
                    browser session or click <em>Remove</em>.
                </p>
            </div>

            <div id="sec-status" class="sec-status <?= $saved ? 'ok' : 'missing' ?>">
                <i class="fa-solid <?= $saved ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <span><?= $saved ? 'Password saved — DDEV actions are ready.' : 'No password saved — DDEV actions will not run.' ?></span>
            </div>

            <form id="sec-form" class="sec-form" autocomplete="on" onsubmit="savePassword(event)">
                <input type="text" name="username" value="<?= htmlspecialchars(DDEV_USER) ?>" autocomplete="username" hidden>
                <div class="form-group">
                    <label for="sec-password">Password for <?= htmlspecialchars(DDEV_USER) ?></label>
                    <div class="sec-input">
                        <input type="password" id="sec-password" autocomplete="current-password" placeholder="Enter system password">
                        <button type="button" class="sec-eye" onclick="toggleShow()" title="Show / hide">
                            <i class="fa-solid fa-eye" id="sec-eye-icon"></i>
                        </button>
                    </div>
                </div>
                <div id="sec-msg" class="sec-msg"></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="sec-save">
                        <i class="fa-solid fa-floppy-disk"></i> Verify &amp; Save
                    </button>
                    <button type="button" class="btn sec-remove" id="sec-clear" onclick="clearPassword()" <?= $saved ? '' : 'hidden' ?>>
                        <i class="fa-solid fa-trash"></i> Remove
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main').classList.toggle('sidebar-collapsed');
}
function updateClock() {
    const el = document.getElementById('clock');
    if (el) el.textContent = new Date().toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

function toggleShow() {
    const i = document.getElementById('sec-password');
    const icon = document.getElementById('sec-eye-icon');
    i.type = i.type === 'password' ? 'text' : 'password';
    icon.className = 'fa-solid ' + (i.type === 'password' ? 'fa-eye' : 'fa-eye-slash');
}

function setStatus(saved) {
    const box = document.getElementById('sec-status');
    box.className = 'sec-status ' + (saved ? 'ok' : 'missing');
    box.innerHTML = saved
        ? '<i class="fa-solid fa-circle-check"></i><span>Password saved — DDEV actions are ready.</span>'
        : '<i class="fa-solid fa-circle-exclamation"></i><span>No password saved — DDEV actions will not run.</span>';
    document.getElementById('sec-clear').hidden = !saved;
}

function showMsg(text, ok) {
    const m = document.getElementById('sec-msg');
    m.textContent = text;
    m.className = 'sec-msg ' + (ok ? 'ok' : 'err');
}

async function post(data) {
    const fd = new FormData();
    for (const k in data) fd.append(k, data[k]);
    const res = await fetch('api/security.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    return res.json();
}

async function savePassword(e) {
    e.preventDefault();
    const input = document.getElementById('sec-password');
    const btn = document.getElementById('sec-save');
    if (!input.value) { showMsg('Please enter the password.', false); input.focus(); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
    try {
        const data = await post({ action: 'save', password: input.value });
        if (data.success) {
            input.value = '';
            setStatus(true);
            showMsg(data.message, true);
        } else {
            showMsg(data.error || 'Failed to save.', false);
        }
    } catch (err) {
        showMsg('Request failed: ' + err.message, false);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Verify &amp; Save';
}

async function clearPassword() {
    try {
        const data = await post({ action: 'clear' });
        setStatus(false);
        showMsg(data.message || 'Password removed.', true);
    } catch (err) {
        showMsg('Request failed: ' + err.message, false);
    }
}
</script>
</body>
</html>
