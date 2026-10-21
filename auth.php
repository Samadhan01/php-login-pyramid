<?php
declare(strict_types=1);

// Start a session using safer cookie settings for this small demo application.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);

    session_start();
}

// One demo user is enough for this assignment; no database is required.
const DEMO_USERNAME = 'admin';
const DEMO_PASSWORD_HASH = '$2y$12$CpE8BYeOHmal7JSOuGgQdu359i.FQRhO4hVT0xaNgvN.i4Xu896I2';
const SESSION_TIMEOUT_SECONDS = 900; // 15 minutes

/** Safely escape text before printing it inside HTML. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Return true only when a valid, active login session exists. */
function is_logged_in(): bool
{
    if (!isset($_SESSION['username']) || !is_string($_SESSION['username'])) {
        return false;
    }

    $lastActivity = $_SESSION['last_activity'] ?? null;

    // Expire sessions after 15 minutes without activity.
    if (!is_int($lastActivity) || (time() - $lastActivity) >= SESSION_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/** Redirect guests away from pages that need authentication. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

/** Create a CSRF token for forms and keep it in the session. */
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Check that a submitted form contains the token stored in this session. */
function verify_csrf_token(): bool
{
    $submittedToken = $_POST['csrf_token'] ?? null;
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    return is_string($submittedToken)
        && is_string($sessionToken)
        && hash_equals($sessionToken, $submittedToken);
}
