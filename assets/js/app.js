// =====================================================
//  DDEV Management - Main JS
// =====================================================

let selectedProject = null;
let currentAction = null;

// ── Clock ──────────────────────────────────────────
function updateClock() {
    const el = document.getElementById('clock');
    if (el) el.textContent = new Date().toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

// ── Sidebar Toggle ─────────────────────────────────
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main').classList.toggle('sidebar-collapsed');
}

// ── Project Type Selection ─────────────────────────
function onProjectTypeChange(radio) {
    const val = radio.value;

    // Visual selection
    document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
    radio.closest('.type-card').classList.add('selected');

    // Show/hide sections
    const existingStep = document.getElementById('step2-existing');
    const newStep      = document.getElementById('step2-new');
    const actionsStep  = document.getElementById('step3-actions');
    const actionPanel  = document.getElementById('action-panel');
    const outputPanel  = document.getElementById('output-panel');

    hideEl(existingStep);
    hideEl(newStep);
    hideEl(actionsStep);
    hideEl(actionPanel);
    hideEl(outputPanel);

    // Reset project
    selectedProject = null;
    document.getElementById('project-info').classList.add('hidden');
    document.getElementById('project-select').value = '';

    if (val === 'existing') {
        showEl(existingStep);
    } else if (val === 'new') {
        showEl(newStep);
    }
}

// ── Project Select ─────────────────────────────────
function onProjectSelect(val) {
    if (!val) {
        hideEl(document.getElementById('project-info'));
        hideEl(document.getElementById('step3-actions'));
        hideEl(document.getElementById('action-panel'));
        hideEl(document.getElementById('output-panel'));
        selectedProject = null;
        return;
    }

    selectedProject = JSON.parse(val);

    // Fill info
    document.getElementById('info-name').textContent   = selectedProject.name;
    document.getElementById('info-folder').textContent = selectedProject.folder;
    document.getElementById('info-db').textContent     = selectedProject.db_type + ' ' + selectedProject.db_version;
    document.getElementById('info-type').textContent   = selectedProject.project_type;
    document.getElementById('info-path').textContent   = selectedProject.path;

    showEl(document.getElementById('project-info'));
    showEl(document.getElementById('step3-actions'));
    hideEl(document.getElementById('action-panel'));
    hideEl(document.getElementById('output-panel'));
}

// ── Show Action Panel ──────────────────────────────
const actionConfig = {
    import: {
        title: 'Import Database',
        desc:  'Upload a SQL file and import it into the project database.',
        icon:  'fa-file-import',
        color: '#22c55e',
        buildHTML: () => `
            <div class="run-box">
                <div class="cmd-preview">
                    <span>$ cd</span> ${selectedProject.path}
                    <br><span>$ ddev import-db --file=</span>[your-file.sql.gz]
                </div>
            </div>
            <div class="file-drop-zone" id="drop-zone2" onclick="document.getElementById('sql-file2').click()">
                <i class="fa-solid fa-cloud-upload-alt" style="font-size:36px;margin-bottom:12px;display:block;color:#22c55e;"></i>
                <p>Drop your .sql or .sql.gz file here</p>
                <span style="font-size:12px;color:#64748b;">or click to browse</span>
                <input type="file" id="sql-file2" accept=".sql,.gz,.sql.gz,.zip" style="display:none" onchange="onFileSelect2(this)">
                <div id="file-name-display2" class="file-selected hidden" style="margin-top:12px;"></div>
            </div>
            <div class="form-actions" style="margin-top:16px;">
                <button class="btn btn-success" id="run-import-btn" onclick="runAction('import')" disabled>
                    <i class="fa-solid fa-file-import"></i> Import Database
                </button>
            </div>`,
    },
    dbupdate: {
        title: 'Run DB Update',
        desc:  'Run Drupal database update hooks via drush updb.',
        icon:  'fa-rotate',
        color: '#6366f1',
        buildHTML: () => `
            <div class="run-box">
                <div class="cmd-preview">
                    <span>$ cd</span> ${selectedProject.path}
                    <br><span>$ ddev drush updb -y</span>
                </div>
                <p style="font-size:12px;color:#94a3b8;margin-top:8px;">
                    <i class="fa-solid fa-info-circle"></i>
                    This runs all pending Drupal database update hooks.
                </p>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" onclick="runAction('dbupdate')">
                    <i class="fa-solid fa-rotate"></i> Run DB Update
                </button>
            </div>`,
    },
    cache: {
        title: 'Clear Cache',
        desc:  'Clear all Drupal caches for the selected project.',
        icon:  'fa-broom',
        color: '#f59e0b',
        buildHTML: () => `
            <div class="run-box">
                <div class="cmd-preview">
                    <span>$ cd</span> ${selectedProject.path}
                    <br><span>$ ddev drush cr</span>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" style="background:#f59e0b;" onclick="runAction('cache')">
                    <i class="fa-solid fa-broom"></i> Clear Cache
                </button>
            </div>`,
    },
    export: {
        title: 'Export Database',
        desc:  'Export the current database to a SQL dump file.',
        icon:  'fa-file-export',
        color: '#38bdf8',
        buildHTML: () => `
            <div class="run-box">
                <div class="cmd-preview">
                    <span>$ cd</span> ${selectedProject.path}
                    <br><span>$ ddev export-db --gzip --file=</span>backup-${selectedProject.folder}-${new Date().toISOString().slice(0,10)}.sql.gz
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" style="background:#38bdf8;color:#0f172a;" onclick="runAction('export')">
                    <i class="fa-solid fa-file-export"></i> Export Database
                </button>
            </div>`,
    },
    start: {
        title: 'Start Project',
        desc:  'Start the DDEV project containers.',
        icon:  'fa-play-circle',
        color: '#22c55e',
        buildHTML: () => `
            <div class="run-box">
                <div class="cmd-preview">
                    <span>$ cd</span> ${selectedProject.path}
                    <br><span>$ ddev start</span>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-success" onclick="runAction('start')">
                    <i class="fa-solid fa-play-circle"></i> Start Project
                </button>
            </div>`,
    },
    status: {
        title: 'Project Status',
        desc:  'Get detailed status info about the DDEV project.',
        icon:  'fa-circle-info',
        color: '#94a3b8',
        buildHTML: () => `
            <div class="run-box">
                <div class="cmd-preview">
                    <span>$ cd</span> ${selectedProject.path}
                    <br><span>$ ddev describe</span>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-ghost" onclick="runAction('status')">
                    <i class="fa-solid fa-circle-info"></i> Get Status
                </button>
            </div>`,
    },
    launch: {
        title: 'Launch Site',
        desc:  'Open the project website in a new browser tab (starts the project if it is stopped).',
        icon:  'fa-rocket',
        color: '#ec4899',
        buildHTML: () => `
            <div class="run-box">
                <div class="cmd-preview">
                    <span>$ cd</span> ${selectedProject.path}
                    <br><span>$ ddev launch</span>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" style="background:#ec4899;" onclick="runAction('launch')">
                    <i class="fa-solid fa-rocket"></i> Launch Site
                </button>
            </div>`,
    },
};

let launchWin = null;

function showAction(action) {
    if (!selectedProject) return;
    currentAction = action;
    const cfg = actionConfig[action];
    if (!cfg) return;

    document.getElementById('action-title').textContent = cfg.title;
    document.getElementById('action-desc').textContent  = cfg.desc;
    document.getElementById('action-body').innerHTML    = cfg.buildHTML();

    // Setup drag-drop for import
    if (action === 'import') {
        setupDropZone();
    }

    showEl(document.getElementById('action-panel'));
    showEl(document.getElementById('output-panel'));
    clearOutput();

    document.getElementById('action-panel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── File Upload / Drop ─────────────────────────────
let selectedFile = null;

function onFileSelect2(input) {
    if (input.files.length) {
        selectedFile = input.files[0];
        const display = document.getElementById('file-name-display2');
        display.innerHTML = `<i class="fa-solid fa-file-zipper"></i> ${selectedFile.name} (${formatBytes(selectedFile.size)})`;
        display.classList.remove('hidden');
        const btn = document.getElementById('run-import-btn');
        if (btn) btn.disabled = false;
    }
}

function setupDropZone() {
    const zone = document.getElementById('drop-zone2');
    if (!zone) return;

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length) {
            const fakeInput = { files: files };
            onFileSelect2({ files: files });
        }
    });
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

// ── Run Action ─────────────────────────────────────
async function runAction(action) {
    if (!selectedProject) return;

    const output = document.getElementById('terminal-output');
    const statusEl = document.getElementById('output-status');
    const timeEl = document.getElementById('output-time');

    // Open the tab now, while we still have the click, so popup blockers allow it
    if (action === 'launch') {
        launchWin = window.open('', '_blank');
        if (launchWin) launchWin.document.write('<p style="font-family:sans-serif">Launching site&hellip;</p>');
    }

    setStatus('running', 'Running...');
    clearOutput();

    appendToOutput('cmd', `cd ${selectedProject.path} && ddev ${getCommand(action)}`);

    // For import, upload file first
    if (action === 'import') {
        if (!selectedFile) {
            appendToOutput('err', 'No file selected. Please choose a SQL file.');
            setStatus('error', 'Error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'import');
        formData.append('project_path', selectedProject.path);
        formData.append('project_name', selectedProject.name);
        formData.append('sql_file', selectedFile);

        appendToOutput('info', `Uploading ${selectedFile.name}...`);

        try {
            const res = await fetch('api/exec.php', { method: 'POST', body: formData });
            const data = await res.json();
            handleResponse(data);
        } catch (e) {
            appendToOutput('err', 'Upload failed: ' + e.message);
            setStatus('error', 'Error');
        }
        return;
    }

    // Other actions
    const formData = new FormData();
    formData.append('action', action);
    formData.append('project_path', selectedProject.path);
    formData.append('project_name', selectedProject.name);

    try {
        const res = await fetch('api/exec.php', { method: 'POST', body: formData });
        const data = await res.json();
        handleResponse(data);
    } catch (e) {
        appendToOutput('err', 'Request failed: ' + e.message);
        setStatus('error', 'Error');
    }
}

function getCommand(action) {
    const cmds = {
        dbupdate: 'drush updb -y',
        cache:    'drush cr',
        export:   'export-db --gzip',
        start:    'start',
        status:   'describe',
        import:   'import-db',
        launch:   'launch',
    };
    return cmds[action] || action;
}

function handleResponse(data) {
    if (data.output) {
        data.output.split('\n').forEach(line => {
            if (line.trim()) appendToOutput('out', line);
        });
    }
    if (data.error) {
        data.error.split('\n').forEach(line => {
            if (line.trim()) appendToOutput('err', line);
        });
    }
    if (data.need_password) {
        appendToOutput('info', 'Go to Privacy & Security (left menu) → security.php');
        setTimeout(() => { if (confirm('No password saved. Open Privacy & Security now?')) location.href = 'security.php'; }, 50);
    }
    if (data.success) {
        appendToOutput('success', '✓ Command completed successfully.');
        setStatus('success', 'Completed');
        if (data.launch_url) {
            if (launchWin && !launchWin.closed) launchWin.location.href = data.launch_url;
            else window.open(data.launch_url, '_blank');
            const line = document.createElement('div');
            line.className = 'term-line';
            const a = document.createElement('a');
            a.href = data.launch_url; a.target = '_blank'; a.rel = 'noopener';
            a.className = 'launch-link'; a.textContent = 'Open ' + data.launch_url;
            line.appendChild(a);
            document.getElementById('terminal-output').appendChild(line);
        }
        if (data.download_url) {
            // Browser saves it to the user's Downloads folder
            const a = document.createElement('a');
            a.href = data.download_url;
            a.download = '';
            document.body.appendChild(a);
            a.click();
            a.remove();
            appendToOutput('info', 'Download started — check your Downloads folder.');
        }
    } else {
        if (launchWin && !launchWin.closed) launchWin.close();
        setStatus('error', 'Error occurred');
    }
    document.getElementById('output-time').textContent = new Date().toLocaleTimeString();
}

// ── Terminal Output Helpers ────────────────────────
function appendToOutput(type, text) {
    const output = document.getElementById('terminal-output');
    const line = document.createElement('div');
    line.className = 'term-line';

    const escaped = escapeHtml(text);

    if (type === 'cmd') {
        line.innerHTML = `<span class="term-prompt">$</span> <span class="term-cmd">${escaped}</span>`;
    } else if (type === 'err') {
        line.innerHTML = `<span class="term-err">${escaped}</span>`;
    } else if (type === 'success') {
        line.innerHTML = `<span class="term-success">${escaped}</span>`;
    } else if (type === 'info') {
        line.innerHTML = `<span style="color:#94a3b8;font-style:italic;">${escaped}</span>`;
    } else {
        line.innerHTML = `<span class="term-out">${escaped}</span>`;
    }

    output.appendChild(line);
    output.scrollTop = output.scrollHeight;
}

function clearOutput() {
    const output = document.getElementById('terminal-output');
    output.innerHTML = '';
    setStatus('idle', 'Idle');
    document.getElementById('output-time').textContent = '';
}

function setStatus(type, text) {
    const el = document.getElementById('output-status');
    el.className = '';
    el.className = `status-${type}`;
    el.textContent = text;
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Modal Helpers ──────────────────────────────────
function showModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// ── Show/Hide helpers ──────────────────────────────
function showEl(el) { if (el) el.classList.remove('hidden'); }
function hideEl(el) { if (el) el.classList.add('hidden'); }
