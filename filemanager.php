<?php
/**
 * File Manager Page
 */

$baseDir = '/var/www/html/';
$currentPath = $_GET['path'] ?? $baseDir;

// Security: resolve and validate path stays within /var/www/html/
$realPath = realpath($currentPath);
$realBase = realpath($baseDir);

if (!$realPath || strpos($realPath, $realBase) !== 0) {
    $realPath = $realBase;
}

// Handle file actions
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fAction = $_POST['faction'] ?? '';

    if ($fAction === 'delete') {
        $target = realpath($_POST['target'] ?? '');
        if ($target && strpos($target, $realBase) === 0 && is_file($target)) {
            if (unlink($target)) {
                $message = 'File deleted: ' . basename($target);
                $msgType = 'success';
            } else {
                $message = 'Could not delete file.';
                $msgType = 'error';
            }
        }
    }

    if ($fAction === 'save') {
        $target  = realpath($_POST['target'] ?? '');
        $content = $_POST['content'] ?? '';
        if ($target && strpos($target, $realBase) === 0 && is_file($target)) {
            if (file_put_contents($target, $content) !== false) {
                $message = 'File saved: ' . basename($target);
                $msgType = 'success';
            } else {
                $message = 'Could not save file.';
                $msgType = 'error';
            }
        }
    }
}

// Scan directory
$items = [];
if (is_dir($realPath)) {
    $entries = scandir($realPath);
    foreach ($entries as $entry) {
        if ($entry === '.') continue;
        $fullPath = $realPath . '/' . $entry;
        $isDir = is_dir($fullPath);
        $items[] = [
            'name'    => $entry,
            'path'    => $fullPath,
            'is_dir'  => $isDir,
            'size'    => $isDir ? 0 : filesize($fullPath),
            'mtime'   => filemtime($fullPath),
            'ext'     => $isDir ? 'dir' : strtolower(pathinfo($entry, PATHINFO_EXTENSION)),
        ];
    }
    // Dirs first, then files, both alphabetically
    usort($items, function($a, $b) {
        if ($a['is_dir'] !== $b['is_dir']) return $b['is_dir'] - $a['is_dir'];
        return strcmp($a['name'], $b['name']);
    });
}

// Breadcrumb
$crumbs = [];
$rel = str_replace($realBase, '', $realPath);
$parts = array_filter(explode('/', $rel));
$crumbs[] = ['label' => 'html', 'path' => $realBase];
$acc = $realBase;
foreach ($parts as $part) {
    $acc .= '/' . $part;
    $crumbs[] = ['label' => $part, 'path' => $acc];
}

function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024, 1) . ' KB';
    return round($bytes/1048576, 1) . ' MB';
}

function getFileIcon($ext, $isDir) {
    if ($isDir) return ['fa-folder', 'fm-icon-dir'];
    $map = [
        'php' => ['fa-file-code', 'fm-icon-php'],
        'sql' => ['fa-database', 'fm-icon-sql'],
        'yaml'=> ['fa-file-code', 'fm-icon-yaml'],
        'yml' => ['fa-file-code', 'fm-icon-yaml'],
        'js'  => ['fa-file-code', 'fm-icon-js'],
        'css' => ['fa-file-code', 'fm-icon-css'],
        'scss'=> ['fa-file-code', 'fm-icon-css'],
        'png' => ['fa-file-image', 'fm-icon-img'],
        'jpg' => ['fa-file-image', 'fm-icon-img'],
        'jpeg'=> ['fa-file-image', 'fm-icon-img'],
        'gif' => ['fa-file-image', 'fm-icon-img'],
        'svg' => ['fa-file-image', 'fm-icon-img'],
        'gz'  => ['fa-file-zipper', 'fm-icon-sql'],
        'zip' => ['fa-file-zipper', 'fm-icon-sql'],
        'md'  => ['fa-file-lines', 'fm-icon-yaml'],
        'json'=> ['fa-file-code', 'fm-icon-js'],
        'txt' => ['fa-file-lines', 'fm-icon-file'],
        'sh'  => ['fa-file-code', 'fm-icon-yaml'],
        'env' => ['fa-file-code', 'fm-icon-yaml'],
    ];
    return $map[$ext] ?? ['fa-file', 'fm-icon-file'];
}

$editContent = '';
$editFile = '';
if (isset($_GET['edit'])) {
    $editTarget = realpath($_GET['edit']);
    if ($editTarget && strpos($editTarget, $realBase) === 0 && is_file($editTarget)) {
        $editContent = file_get_contents($editTarget);
        $editFile = $editTarget;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - DDEV Management</title>
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
        <a href="terminal.php" class="nav-item">
            <i class="fa-solid fa-terminal"></i>
            <span>Terminal</span>
        </a>
        <a href="filemanager.php" class="nav-item active">
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
                <i class="fa-solid fa-folder-open"></i> File Manager
            </h1>
        </div>
        <div class="topbar-right">
            <span class="badge badge-info">
                <i class="fa-solid fa-folder"></i>
                <?= count($items) ?> items
            </span>
            <span class="time-display" id="clock"></span>
        </div>
    </header>

    <?php if ($message): ?>
    <div style="margin:16px 24px 0;padding:10px 16px;border-radius:8px;font-size:13px;
        background:<?= $msgType === 'success' ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)' ?>;
        border:1px solid <?= $msgType === 'success' ? '#22c55e' : '#ef4444' ?>;
        color:<?= $msgType === 'success' ? '#22c55e' : '#ef4444' ?>;">
        <i class="fa-solid fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="fm-toolbar">
        <div class="fm-breadcrumb">
            <?php foreach ($crumbs as $i => $crumb): ?>
                <?php if ($i < count($crumbs) - 1): ?>
                    <a href="?path=<?= urlencode($crumb['path']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                    <span>/</span>
                <?php else: ?>
                    <span style="color:#cbd5e1;font-weight:600;"><?= htmlspecialchars($crumb['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="fm-path-bar">
            <i class="fa-solid fa-map-marker-alt"></i>
            <?= htmlspecialchars($realPath) ?>
        </div>

        <?php if ($realPath !== $realBase): ?>
        <a href="?path=<?= urlencode(dirname($realPath)) ?>" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-arrow-up"></i> Up
        </a>
        <?php endif; ?>
    </div>

    <!-- File Table -->
    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th class="fm-actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item):
                    [$icon, $iconClass] = getFileIcon($item['ext'], $item['is_dir']);
                ?>
                <tr>
                    <td>
                        <div class="fm-name">
                            <i class="fa-solid <?= $icon ?> <?= $iconClass ?>"></i>
                            <?php if ($item['is_dir']): ?>
                                <a href="?path=<?= urlencode($item['path']) ?>" style="color:inherit;text-decoration:none;">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($item['name']) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="fm-size">
                        <?= $item['is_dir'] ? '—' : formatSize($item['size']) ?>
                    </td>
                    <td class="fm-date">
                        <?= date('Y-m-d H:i', $item['mtime']) ?>
                    </td>
                    <td class="fm-actions-col">
                        <?php if (!$item['is_dir']): ?>
                            <?php
                            $isEditable = in_array($item['ext'], ['php','html','css','js','json','yaml','yml','md','txt','sh','env','twig','sql','htaccess','conf','ini','xml']);
                            ?>
                            <?php if ($isEditable): ?>
                            <button onclick="window.location='?path=<?= urlencode($realPath) ?>&edit=<?= urlencode($item['path']) ?>'" title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php endif; ?>
                            <button onclick="downloadFile(<?= htmlspecialchars(json_encode($item['path'])) ?>)" title="Download">
                                <i class="fa-solid fa-download"></i>
                            </button>
                            <button onclick="confirmDelete(<?= htmlspecialchars(json_encode($item['path'])) ?>, <?= htmlspecialchars(json_encode($item['name'])) ?>)" title="Delete" style="color:#ef4444 !important;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- File Editor Modal -->
<?php if ($editFile): ?>
<div class="editor-overlay" id="editor-overlay">
    <div class="editor-modal">
        <div class="editor-header">
            <h3>
                <i class="fa-solid fa-code"></i>
                <?= htmlspecialchars(basename($editFile)) ?>
                <span style="font-size:11px;color:#64748b;font-weight:400;"><?= htmlspecialchars($editFile) ?></span>
            </h3>
            <button onclick="document.getElementById('editor-overlay').remove()" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:18px;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="post" action="?path=<?= urlencode($realPath) ?>" style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
            <input type="hidden" name="faction" value="save">
            <input type="hidden" name="target" value="<?= htmlspecialchars($editFile) ?>">
            <textarea name="content" class="editor-textarea"><?= htmlspecialchars($editContent) ?></textarea>
            <div class="editor-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('editor-overlay').remove()">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Save File
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Delete Form (hidden) -->
<form id="delete-form" method="post" action="?path=<?= urlencode($realPath) ?>" style="display:none;">
    <input type="hidden" name="faction" value="delete">
    <input type="hidden" name="target" id="delete-target">
</form>

<!-- Download Form (hidden) -->
<form id="download-form" method="get" action="api/download.php" style="display:none;">
    <input type="hidden" name="file" id="download-file">
</form>

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

function confirmDelete(path, name) {
    if (confirm('Delete "' + name + '"? This cannot be undone.')) {
        document.getElementById('delete-target').value = path;
        document.getElementById('delete-form').submit();
    }
}

function downloadFile(path) {
    document.getElementById('download-file').value = path;
    document.getElementById('download-form').submit();
}
</script>
</body>
</html>
