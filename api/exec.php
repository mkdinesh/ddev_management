<?php
/**
 * DDEV Management - Command Executor API
 * Handles all DDEV/drush command execution
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Security: only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action      = $_POST['action']       ?? '';
$projectPath = $_POST['project_path'] ?? '';
$projectName = $_POST['project_name'] ?? '';

// Validate project path
$baseDir = '/var/www/html/';
$realPath = realpath($projectPath);
$realBase = realpath($baseDir);

if (!$realPath || strpos($realPath, $realBase) !== 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid project path']);
    exit;
}

if (!is_dir($realPath . '/.ddev')) {
    echo json_encode(['success' => false, 'error' => 'Not a valid DDEV project directory']);
    exit;
}

$output = '';
$error  = '';
$success = false;

require_once __DIR__ . '/lib.php';

const EXPORT_DIR = __DIR__ . '/../exports';

set_time_limit(0);

$password = session_password();
if ($password === '' && $action !== 'list_projects') {
    echo json_encode([
        'success' => false,
        'error'   => 'No password saved. Open "Privacy & Security" in the left menu and save the password for user ' . DDEV_USER . '.',
        'need_password' => true,
    ]);
    exit;
}

switch ($action) {

    // ── DB Update ──────────────────────────────────────
    case 'dbupdate':
        [$output, $code] = run_ddev($realPath, 'drush updb -y', $password);
        $success = ($code === 0);
        if (!$success) $error = "Command exited with code $code";
        break;

    // ── Cache Clear ────────────────────────────────────
    case 'cache':
        [$output, $code] = run_ddev($realPath, 'drush cr', $password);
        $success = ($code === 0);
        if (!$success) $error = "Cache clear failed (exit code: $code)";
        break;

    // ── Import DB ──────────────────────────────────────
    case 'import':
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            exit;
        }

        $file = $_FILES['sql_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['sql', 'gz', 'zip'];

        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Use .sql, .gz, or .zip']);
            exit;
        }

        // Save upload
        // Must be readable by DDEV_USER (not www-data's private /tmp)
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $destPath = $uploadDir . $safeFilename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
            exit;
        }
        chmod($destPath, 0644);

        [$output, $code] = run_ddev($realPath, 'import-db --file=' . escapeshellarg($destPath), $password);
        $success = ($code === 0);

        // Cleanup
        @unlink($destPath);

        if (!$success) $error = "Import failed (exit code: $code)";
        break;

    // ── Export DB ──────────────────────────────────────
    case 'export':
        if (!is_dir(EXPORT_DIR)) mkdir(EXPORT_DIR, 0775, true);
        $filename   = 'backup-' . basename($realPath) . '-' . date('Y-m-d-His') . '.sql.gz';
        $exportPath = realpath(EXPORT_DIR) . '/' . $filename;
        [$output, $code] = run_ddev($realPath, 'export-db --gzip --file=' . escapeshellarg($exportPath), $password);
        $success = ($code === 0 && is_file($exportPath));
        if ($success) {
            $output .= "\nExported " . round(filesize($exportPath) / 1048576, 2) . " MB — starting download...";
            echo json_encode([
                'success'      => true,
                'output'       => $output,
                'error'        => '',
                'download_url' => 'api/download.php?export=' . rawurlencode($filename),
            ]);
            exit;
        }
        $error = "Export failed (exit code: $code)";
        break;

    // ── DDEV Start ─────────────────────────────────────
    case 'start':
        [$output, $code] = run_ddev($realPath, 'start', $password);
        $success = ($code === 0);
        if (!$success) $error = "Start failed (exit code: $code)";
        break;

    // ── Launch Site ────────────────────────────────────
    // `ddev launch` would try to open a browser on the server side, so get the
    // project URL instead and let the user's browser open it.
    case 'launch':
        $describe = function () use ($realPath, $password) {
            [$out, $code] = run_ddev($realPath, 'describe -j', $password);
            $start = strpos($out, '{');
            $json  = $start === false ? null : json_decode(substr($out, $start), true);
            return [$json['raw'] ?? null, $out, $code];
        };

        [$info, $out, $code] = $describe();
        if (!$info) {
            $output = $out;
            $error  = "Could not read project info (exit code: $code)";
            break;
        }

        $lines = [];
        if (($info['status'] ?? '') !== 'running') {
            $lines[] = 'Project is ' . ($info['status'] ?? 'not running') . ' — starting it first...';
            [$startOut, $startCode] = run_ddev($realPath, 'start', $password);
            $lines[] = $startOut;
            if ($startCode !== 0) {
                $output = implode("\n", $lines);
                $error  = "Start failed (exit code: $startCode)";
                break;
            }
            [$info, $out, $code] = $describe();
        }

        $url = $info['primary_url'] ?? ($info['httpsurl'] ?? ($info['httpurl'] ?? ''));
        if (!$url) {
            $output = implode("\n", $lines);
            $error  = 'No site URL found for this project.';
            break;
        }
        $lines[] = 'Site URL: ' . $url;
        echo json_encode(['success' => true, 'output' => implode("\n", $lines), 'error' => '', 'launch_url' => $url]);
        exit;

    // ── DDEV Status ────────────────────────────────────
    case 'status':
        [$output, $code] = run_ddev($realPath, 'describe', $password);
        $success = ($code === 0);
        if (!$success) $error = "Status check failed (exit code: $code)";
        break;

    // ── List DDEV Projects (AJAX) ──────────────────────
    case 'list_projects':
        $base = '/var/www/html/';
        $projects = [];
        foreach (scandir($base) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            if (is_dir($base . $dir) && file_exists($base . $dir . '/.ddev/config.yaml')) {
                $projects[] = $dir;
            }
        }
        echo json_encode(['success' => true, 'projects' => $projects]);
        exit;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
        exit;
}

echo json_encode([
    'success' => $success,
    'output'  => $output,
    'error'   => $error,
]);
