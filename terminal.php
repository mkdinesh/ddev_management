<?php
/**
 * Terminal Page - Web-based terminal via AJAX
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal - DDEV Management</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
    <link rel="stylesheet" href="assets/css/pages.css?v=<?= filemtime(__DIR__ . '/assets/css/pages.css') ?>">
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
        <a href="terminal.php" class="nav-item active">
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
        <a href="security.php" class="nav-item">
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
                <i class="fa-solid fa-terminal"></i> Web Terminal
            </h1>
        </div>
        <div class="topbar-right">
            <span class="badge badge-info">
                <i class="fa-solid fa-server"></i> Interactive Shell
            </span>
            <span class="time-display" id="clock"></span>
        </div>
    </header>

    <div class="terminal-page-wrap">

        <!-- Quick DDEV Commands -->
        <div class="card" style="padding:14px 20px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <i class="fa-solid fa-bolt" style="color:#f59e0b;"></i>
                <span style="font-weight:600;font-size:13px;">Quick Commands</span>
                <span style="font-size:12px;color:#64748b;">Click to run</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php
                $quickCmds = [
                    ['ddev list', 'List all projects'],
                    ['ddev status', 'Status'],
                    ['ddev poweroff', 'Power Off All'],
                    ['ls /var/www/html/', 'List Projects'],
                    ['ddev version', 'DDEV Version'],
                    ['php --version', 'PHP Version'],
                    ['composer --version', 'Composer Version'],
                ];
                foreach ($quickCmds as $qc): ?>
                <button class="btn btn-ghost btn-sm" onclick="runQuickCmd(<?= htmlspecialchars(json_encode($qc[0])) ?>)" title="<?= htmlspecialchars($qc[1]) ?>">
                    <i class="fa-solid fa-play" style="font-size:10px;"></i>
                    <?= htmlspecialchars($qc[0]) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Terminal -->
        <div class="terminal-container">
            <div class="terminal-toolbar">
                <div class="terminal-dots">
                    <span class="dot-red"></span>
                    <span class="dot-yellow"></span>
                    <span class="dot-green"></span>
                </div>
                <div class="terminal-title">
                    <i class="fa-solid fa-terminal" style="margin-right:6px;"></i>
                    bash — /var/www/html
                </div>
                <button class="btn btn-sm btn-ghost" onclick="clearTerminal()" style="font-size:11px;">
                    <i class="fa-solid fa-trash"></i> Clear
                </button>
            </div>

            <div class="terminal-output-area" id="terminal-output-area">
                <div style="color:#3fb950;font-weight:bold;margin-bottom:8px;">
                    ┌──────────────────────────────────────────────────────┐<br>
                    │  DDEV Management — Web Terminal                      │<br>
                    │  Type commands below and press Enter                 │<br>
                    └──────────────────────────────────────────────────────┘
                </div>
                <div class="term-info-line">Connected. Working directory: /var/www/html</div>
                <br>
            </div>

            <div class="terminal-input-row">
                <span class="term-cwd" id="term-cwd">/var/www/html</span>
                <span class="term-dollar">$</span>
                <input
                    type="text"
                    class="terminal-input"
                    id="term-input"
                    placeholder="Type a command and press Enter..."
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    onkeydown="handleTermInput(event)"
                >
            </div>
        </div>

    </div>
</div>

<script>
let cmdHistory = [];
let historyIdx = -1;
let currentDir = '/var/www/html';

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

const termOut = document.getElementById('terminal-output-area');
const termIn  = document.getElementById('term-input');
const cwdEl   = document.getElementById('term-cwd');

function appendLine(text, cls) {
    const div = document.createElement('div');
    div.className = cls || 'term-out-line';
    div.style.whiteSpace = 'pre-wrap';
    div.style.wordBreak = 'break-all';
    div.textContent = text;
    termOut.appendChild(div);
    termOut.scrollTop = termOut.scrollHeight;
}

function appendHTML(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    termOut.appendChild(div);
    termOut.scrollTop = termOut.scrollHeight;
}

function clearTerminal() {
    termOut.innerHTML = '';
}

async function runQuickCmd(cmd) {
    termIn.value = cmd;
    await execCommand(cmd);
    termIn.value = '';
    termIn.focus();
}

async function handleTermInput(e) {
    if (e.key === 'Enter') {
        const cmd = termIn.value.trim();
        if (!cmd) return;

        cmdHistory.unshift(cmd);
        historyIdx = -1;

        await execCommand(cmd);
        termIn.value = '';

    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (historyIdx < cmdHistory.length - 1) {
            historyIdx++;
            termIn.value = cmdHistory[historyIdx] || '';
        }
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (historyIdx > 0) {
            historyIdx--;
            termIn.value = cmdHistory[historyIdx] || '';
        } else {
            historyIdx = -1;
            termIn.value = '';
        }
    } else if (e.key === 'l' && e.ctrlKey) {
        e.preventDefault();
        clearTerminal();
    }
}

async function execCommand(cmd) {
    // Echo command
    appendHTML(`<div style="margin-top:4px;"><span style="color:#58a6ff;font-family:monospace;">${escH(currentDir)} $</span> <span style="color:#c9d1d9;">${escH(cmd)}</span></div>`);

    // Client-side cd handling
    if (cmd.startsWith('cd ')) {
        const dir = cmd.substring(3).trim();
        const fd = new FormData();
        fd.append('cmd', 'cd ' + dir + ' && pwd');
        fd.append('cwd', currentDir);
        try {
            const r = await fetch('api/terminal.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success && d.stdout.trim()) {
                currentDir = d.stdout.trim();
                cwdEl.textContent = currentDir;
            } else {
                appendLine(d.stderr || 'No such directory', 'term-err-line');
            }
        } catch (e) {
            appendLine('Error: ' + e.message, 'term-err-line');
        }
        return;
    }

    const fd = new FormData();
    fd.append('cmd', cmd);
    fd.append('cwd', currentDir);

    try {
        const r = await fetch('api/terminal.php', { method: 'POST', body: fd });
        const d = await r.json();

        if (d.stdout) {
            d.stdout.split('\n').forEach(line => {
                if (line !== '') appendLine(line, 'term-out-line');
            });
        }
        if (d.stderr) {
            d.stderr.split('\n').forEach(line => {
                if (line !== '') appendLine(line, 'term-err-line');
            });
        }
        if (d.cwd) {
            currentDir = d.cwd;
            cwdEl.textContent = currentDir;
        }
        if (!d.success && !d.stderr) {
            appendLine('Command exited with code ' + (d.exit_code || '?'), 'term-err-line');
        }
    } catch (e) {
        appendLine('Request error: ' + e.message, 'term-err-line');
    }

    termOut.scrollTop = termOut.scrollHeight;
}

function escH(t) {
    return String(t)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Auto-focus terminal input
termIn.focus();
document.addEventListener('click', () => termIn.focus());
</script>
</body>
</html>
