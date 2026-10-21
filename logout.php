<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

// Remove the login information from the session.
$_SESSION = [];

// Expire the session cookie in the browser as well.
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
header('Location: index.php');
exit;
