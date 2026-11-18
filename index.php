<?php
// Scan for all DDEV projects in /var/www/html/
$baseDir = '/var/www/html/';
$projects = [];

if (is_dir($baseDir)) {
    $dirs = scandir($baseDir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $ddevConfig = $baseDir . $dir . '/.ddev/config.yaml';
        if (is_dir($baseDir . $dir) && file_exists($ddevConfig)) {
            $configContent = file_get_contents($ddevConfig);
            $projectName = $dir;
            $dbType = 'mariadb';
            $dbVersion = '10.4';
            $projectType = 'drupal';

            if (preg_match('/^name:\s*(.+)$/m', $configContent, $m)) {
                $projectName = trim($m[1]);
            }
            if (preg_match('/^\s+type:\s*(.+)$/m', $configContent, $m)) {
                $dbType = trim($m[1]);
            }
            if (preg_match('/^\s+version:\s*["\']?([^"\']+)["\']?$/m', $configContent, $m)) {
                $dbVersion = trim($m[1]);
            }
            if (preg_match('/^type:\s*(.+)$/m', $configContent, $m)) {
                $projectType = trim($m[1]);
            }

            $projects[] = [
                'folder'      => $dir,
                'name'        => $projectName,
                'db_type'     => $dbType,
                'db_version'  => $dbVersion,
                'project_type'=> $projectType,
                'path'        => $baseDir . $dir,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDEV Management</title>
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
        <a href="index.php" class="nav-item active">
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
        <a href="security.php" class="nav-item">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Privacy &amp; Security</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <span><i class="fa-solid fa-circle" style="color:#22c55e;font-size:8px;"></i> DDEV Active</span>
    </div>
</div>

<!-- Main Content -->
<div class="main" id="main">
    <!-- Top Bar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle-top" onclick="toggleSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1 class="page-title">
                <i class="fa-solid fa-database"></i> DDEV Database Update
            </h1>
        </div>
        <div class="topbar-right">
            <span class="badge badge-info">
                <i class="fa-solid fa-server"></i>
                <?= count($projects) ?> Projects Found
            </span>
            <span class="time-display" id="clock"></span>
        </div>
    </header>

    <!-- Page Content -->
    <div class="content">

        <!-- Step 1: Project Type Selection -->
        <div class="card step-card" id="step1">
            <div class="card-header">
                <div class="step-badge">Step 1</div>
                <h2>Select Project Type</h2>
                <p>Choose whether you want to work with an existing project or create a new one.</p>
            </div>
            <div class="project-type-grid">
                <label class="type-card" id="card-existing">
                    <input type="radio" name="project_type" value="existing" onchange="onProjectTypeChange(this)">
                    <div class="type-card-inner">
                        <div class="type-icon existing-icon">
                            <i class="fa-solid fa-folder-open"></i>
                        </div>
                        <h3>Already Has Project</h3>
                        <p>Update database on an existing DDEV project</p>
                        <div class="type-features">
                            <span><i class="fa-solid fa-check"></i> Import SQL</span>
                            <span><i class="fa-solid fa-check"></i> DB Update</span>
                            <span><i class="fa-solid fa-check"></i> Cache Clear</span>
                        </div>
                    </div>
                </label>

                <label class="type-card" id="card-new">
                    <input type="radio" name="project_type" value="new" onchange="onProjectTypeChange(this)">
                    <div class="type-card-inner">
                        <div class="type-icon new-icon">
                            <i class="fa-solid fa-plus-circle"></i>
                        </div>
                        <h3>New Project</h3>
                        <p>Set up a fresh DDEV project environment</p>
                        <div class="type-features">
                            <span><i class="fa-solid fa-check"></i> Configure DDEV</span>
                            <span><i class="fa-solid fa-check"></i> Init DB</span>
                            <span><i class="fa-solid fa-check"></i> Setup</span>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Step 2: Select Existing Project -->
        <div class="card step-card hidden" id="step2-existing">
            <div class="card-header">
                <div class="step-badge">Step 2</div>
                <h2>Select Project</h2>
                <p>Choose the DDEV project you want to update from <code>/var/www/html/</code></p>
            </div>

            <div class="project-selector-wrap">
                <div class="select-group">
                    <label for="project-select">
                        <i class="fa-solid fa-code-branch"></i> Available DDEV Projects
                    </label>
                    <div class="select-wrapper">
                        <select id="project-select" onchange="onProjectSelect(this.value)">
                            <option value="">— Select a project —</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= htmlspecialchars(json_encode($p)) ?>">
                                <?= htmlspecialchars($p['folder']) ?> (<?= htmlspecialchars($p['name']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                </div>

                <!-- Project Info Card -->
                <div class="project-info hidden" id="project-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <i class="fa-solid fa-tag"></i>
                            <div>
                                <label>Project Name</label>
                                <span id="info-name">—</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fa-solid fa-folder"></i>
                            <div>
                                <label>Folder</label>
                                <span id="info-folder">—</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fa-solid fa-database"></i>
                            <div>
                                <label>Database</label>
                                <span id="info-db">—</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fa-solid fa-code"></i>
                            <div>
                                <label>Project Type</label>
                                <span id="info-type">—</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fa-solid fa-map-marker-alt"></i>
                            <div>
                                <label>Path</label>
                                <span id="info-path">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: DB Actions (shown after project selected) -->
        <div class="card step-card hidden" id="step3-actions">
            <div class="card-header">
                <div class="step-badge">Step 3</div>
                <h2>Database Actions</h2>
                <p>Choose what database operation you want to perform on the selected project.</p>
            </div>

            <div class="actions-grid">
                <!-- Import SQL -->
                <div class="action-card" onclick="showAction('import')">
                    <div class="action-icon import-icon">
                        <i class="fa-solid fa-file-import"></i>
                    </div>
                    <h3>Import Database</h3>
                    <p>Upload and import a SQL dump file into the project database</p>
                    <span class="action-tag">ddev import-db</span>
                </div>

                <!-- DB Update (Drupal) -->
                <div class="action-card" onclick="showAction('dbupdate')">
                    <div class="action-icon update-icon">
                        <i class="fa-solid fa-rotate"></i>
                    </div>
                    <h3>Run DB Update</h3>
                    <p>Run Drupal database update hooks (drush updb)</p>
                    <span class="action-tag">drush updb</span>
                </div>

                <!-- Cache Clear -->
                <div class="action-card" onclick="showAction('cache')">
                    <div class="action-icon cache-icon">
                        <i class="fa-solid fa-broom"></i>
                    </div>
                    <h3>Clear Cache</h3>
                    <p>Clear all Drupal caches for the selected project</p>
                    <span class="action-tag">drush cr</span>
                </div>

                <!-- Export DB -->
                <div class="action-card" onclick="showAction('export')">
                    <div class="action-icon export-icon">
                        <i class="fa-solid fa-file-export"></i>
                    </div>
                    <h3>Export Database</h3>
                    <p>Export the current database to a SQL dump file</p>
                    <span class="action-tag">ddev export-db</span>
                </div>

                <!-- DDEV Start -->
                <div class="action-card" onclick="showAction('start')">
                    <div class="action-icon start-icon">
                        <i class="fa-solid fa-play-circle"></i>
                    </div>
                    <h3>Start Project</h3>
                    <p>Start the DDEV project containers</p>
                    <span class="action-tag">ddev start</span>
                </div>

                <!-- DDEV Status -->
                <div class="action-card" onclick="showAction('status')">
                    <div class="action-icon status-icon">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <h3>Project Status</h3>
                    <p>Check the current status of the DDEV project</p>
                    <span class="action-tag">ddev describe</span>
                </div>

                <!-- Launch Site -->
                <div class="action-card" onclick="showAction('launch')">
                    <div class="action-icon launch-icon">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <h3>Launch Site</h3>
                    <p>Open the project website in a new browser tab</p>
                    <span class="action-tag">ddev launch</span>
                </div>
            </div>
        </div>

        <!-- Action Panel -->
        <div class="card step-card hidden" id="action-panel">
            <div class="card-header" id="action-panel-header">
                <div class="step-badge">Step 4</div>
                <h2 id="action-title">Action</h2>
                <p id="action-desc">Configure and run the selected action.</p>
            </div>
            <div id="action-body"></div>
        </div>

        <!-- Terminal Output -->
        <div class="card step-card hidden" id="output-panel">
            <div class="card-header">
                <div class="step-badge" style="background:#1e293b">
                    <i class="fa-solid fa-terminal" style="font-size:12px;"></i>
                </div>
                <h2>Terminal Output</h2>
                <div class="output-controls">
                    <button class="btn btn-sm btn-ghost" onclick="clearOutput()">
                        <i class="fa-solid fa-trash"></i> Clear
                    </button>
                </div>
            </div>
            <div class="terminal-output" id="terminal-output">
                <div class="term-welcome">
                    <span class="term-prompt">$</span> Ready...
                </div>
            </div>
            <div class="output-footer">
                <span id="output-status" class="status-idle">Idle</span>
                <span id="output-time"></span>
            </div>
        </div>

        <!-- New Project Panel -->
        <div class="card step-card hidden" id="step2-new">
            <div class="card-header">
                <div class="step-badge">Step 2</div>
                <h2>New Project</h2>
                <p>This feature is coming soon. Use the terminal to set up a new DDEV project manually.</p>
            </div>
            <div class="coming-soon">
                <i class="fa-solid fa-wrench"></i>
                <h3>Coming Soon</h3>
                <p>New project wizard will be available in the next update.</p>
                <a href="terminal.php" class="btn btn-primary">
                    <i class="fa-solid fa-terminal"></i> Open Terminal Instead
                </a>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- Import Modal -->
<div class="modal-overlay hidden" id="import-modal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-import"></i> Import Database</h3>
            <button onclick="closeModal('import-modal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="import-form" onsubmit="runImport(event)">
                <div class="form-group">
                    <label>SQL File Upload</label>
                    <div class="file-drop-zone" id="drop-zone">
                        <i class="fa-solid fa-cloud-upload-alt"></i>
                        <p>Drop your .sql or .sql.gz file here</p>
                        <span>or</span>
                        <label class="btn btn-outline" for="sql-file">Browse File</label>
                        <input type="file" id="sql-file" accept=".sql,.gz,.sql.gz,.zip" style="display:none" onchange="onFileSelect(this)">
                        <div id="file-name-display" class="file-selected hidden"></div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('import-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="import-btn" disabled>
                        <i class="fa-solid fa-file-import"></i> Import Database
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
