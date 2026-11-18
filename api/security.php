<?php
/**
 * Privacy & Security API
 *  POST action=save   password=...  → verifies it with `su`, then stores it in the PHP session
 *  POST action=clear                → removes it from the session
 *  POST action=status               → { saved: bool }
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save':
        $password = $_POST['password'] ?? '';
        if ($password === '') {
            echo json_encode(['success' => false, 'error' => 'Please enter the password.']);
            exit;
        }
        // Don't hold the session lock while su runs
        session_write_close();
        [$out, $code] = run_as_user('true', $password);
        if ($code !== 0) {
            echo json_encode(['success' => false, 'error' => $out ?: 'Password check failed.']);
            exit;
        }
        session_start();
        session_regenerate_id(true);
        $_SESSION['ddev_password'] = $password;
        echo json_encode(['success' => true, 'saved' => true, 'message' => 'Password verified and saved for this browser session.']);
        break;

    case 'clear':
        unset($_SESSION['ddev_password']);
        echo json_encode(['success' => true, 'saved' => false, 'message' => 'Password removed.']);
        break;

    case 'status':
        echo json_encode(['success' => true, 'saved' => !empty($_SESSION['ddev_password'])]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
