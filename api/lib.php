<?php
/**
 * Shared helpers: run commands as the DDEV owner user.
 *
 * PHP runs as www-data, which has no $HOME and no Docker access, so ddev is run
 * as DDEV_USER via `su`, using the password saved on the Privacy & Security page
 * (kept only in the server-side PHP session). `su` needs a terminal, so it is
 * wrapped in `script` (util-linux) to get a pty.
 */
const DDEV_USER = 'ino-lap-102';

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');

function session_password(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $pw = $_SESSION['ddev_password'] ?? '';
    // Release the session lock so long-running commands don't block other requests
    session_write_close();
    return $pw;
}

/**
 * Run a shell command as DDEV_USER. Returns [output, exitCode].
 */
function run_as_user(string $inner, string $password): array {
    $su    = 'su ' . escapeshellarg(DDEV_USER) . ' -c ' . escapeshellarg($inner);
    $cmd   = 'script -qec ' . escapeshellarg($su) . ' /dev/null';

    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, [
        'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        'TERM' => 'dumb',
    ]);
    if (!is_resource($proc)) return ['Failed to start process', -1];

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $out = '';
    $sent = false;
    $sentAt = 0;
    $promptWait = time();
    while (true) {
        $r = [$pipes[1], $pipes[2]]; $w = null; $e = null;
        if (stream_select($r, $w, $e, 1) > 0) {
            foreach ($r as $p) $out .= (string) fread($p, 65536);
        }
        // Only send the password once su is prompting (echo is off by then)
        if (!$sent && (stripos($out, 'password') !== false || time() - $promptWait > 10)) {
            fwrite($pipes[0], $password . "\n");
            fflush($pipes[0]);
            $sent = true;
            $sentAt = strlen($out);
        }
        $status = proc_get_status($proc);
        if (!$status['running']) break;
    }
    $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    foreach ($pipes as $p) @fclose($p);
    proc_close($proc);
    $code = $status['exitcode'];

    // Never leak the password: if the terminal echoed it, it can only be on the
    // line right after it was sent. Only scrub there, so real output (URLs etc.)
    // is never altered.
    if ($sent) {
        $tail = substr($out, $sentAt);
        $nl   = strpos($tail, "\n");
        $first = $nl === false ? $tail : substr($tail, 0, $nl);
        $rest  = $nl === false ? '' : substr($tail, $nl);
        $out = substr($out, 0, $sentAt) . str_replace($password, '', $first) . $rest;
    }

    // Drop the prompt and terminal noise
    $out = preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $out);
    $out = str_replace("\r", '', $out);
    $out = preg_replace('/^\s*Password:\s*/mi', '', $out);
    if (stripos($out, 'Authentication failure') !== false) {
        $out = 'Wrong password for user ' . DDEV_USER . '.';
    }
    return [trim($out), $code];
}

/**
 * Run `ddev <args>` in $projectPath as DDEV_USER. Returns [output, exitCode].
 */
function run_ddev(string $projectPath, string $args, string $password): array {
    return run_as_user('cd ' . escapeshellarg($projectPath) . ' && /usr/bin/ddev ' . $args, $password);
}
