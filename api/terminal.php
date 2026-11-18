<?php
/**
 * Terminal API - Secure command execution
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'stderr' => 'Method not allowed']);
    exit;
}

$cmd = $_POST['cmd'] ?? '';
$cwd = $_POST['cwd'] ?? '/var/www/html';

// Sanitize working directory
$realCwd = realpath($cwd);
if (!$realCwd || !is_dir($realCwd)) {
    $realCwd = '/var/www/html';
}

// Block dangerous commands
$blocked = [
    'rm -rf /',
    'mkfs',
    'dd if=/dev/zero',
    ':(){:|:&};:',
    'wget',
    'curl -o',
    'bash -i',
    '/dev/tcp',
];

foreach ($blocked as $b) {
    if (stripos($cmd, $b) !== false) {
        echo json_encode(['success' => false, 'stderr' => 'Command blocked for security reasons.', 'stdout' => '', 'exit_code' => 1]);
        exit;
    }
}

// Build command with timeout
$fullCmd = 'cd ' . escapeshellarg($realCwd) . ' && ' . $cmd;

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$env = array_merge($_ENV, [
    'HOME' => '/root',
    'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games:/snap/bin',
    'TERM' => 'xterm-256color',
]);

$process = proc_open($fullCmd, $descriptors, $pipes, $realCwd, $env);

if (!is_resource($process)) {
    echo json_encode(['success' => false, 'stderr' => 'Failed to start process', 'stdout' => '', 'exit_code' => -1]);
    exit;
}

// Non-blocking reads with timeout
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

fclose($pipes[0]);

$stdout = '';
$stderr = '';
$timeout = 60; // 60 seconds
$start = time();

while (true) {
    $status = proc_get_status($process);

    $out = fread($pipes[1], 8192);
    $err = fread($pipes[2], 8192);

    if ($out) $stdout .= $out;
    if ($err)  $stderr .= $err;

    if (!$status['running']) break;
    if ((time() - $start) > $timeout) {
        proc_terminate($process);
        $stderr .= "\n[Timeout: command killed after {$timeout}s]";
        break;
    }

    usleep(50000); // 50ms
}

// Read remaining output
$stdout .= stream_get_contents($pipes[1]);
$stderr .= stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);

// Try to get new cwd after command
$newCwd = $realCwd;
if (strpos($cmd, 'cd ') === 0) {
    // Handle cd by trying to resolve
    $dir = trim(substr($cmd, 3));
    if ($dir[0] !== '/') {
        $dir = $realCwd . '/' . $dir;
    }
    $real = realpath($dir);
    if ($real && is_dir($real)) {
        $newCwd = $real;
    }
}

// Strip ANSI escape codes for clean output
$stdout = preg_replace('/\033\[[0-9;]*[mGKHF]/u', '', $stdout);
$stderr = preg_replace('/\033\[[0-9;]*[mGKHF]/u', '', $stderr);

echo json_encode([
    'success'   => $exitCode === 0,
    'stdout'    => $stdout,
    'stderr'    => $stderr,
    'exit_code' => $exitCode,
    'cwd'       => $newCwd,
]);
