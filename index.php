<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

// A user who is already logged in should go straight to the pyramid page.
if (is_logged_in()) {
    header('Location: pyramid.php');
    exit;
}

$error = '';
$timeoutNotice = isset($_GET['timeout']) && $_GET['timeout'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Your form expired. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $validUsername = is_string($username)
            && hash_equals(DEMO_USERNAME, trim($username));
        $validPassword = is_string($password)
            && password_verify($password, DEMO_PASSWORD_HASH);

        if ($validUsername && $validPassword) {
            // Give the successful login a fresh session ID to help prevent session fixation.
            session_regenerate_id(true);
            $_SESSION['username'] = DEMO_USERNAME;
            $_SESSION['last_activity'] = time();
            unset($_SESSION['csrf_token']);

            header('Location: pyramid.php');
            exit;
        }

        // Do not tell the user which part of the credentials was wrong.
        $error = 'Incorrect username or password. Please try again.';
    }
}

$formToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Star Pyramid</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="page-shell login-shell">
        <section class="panel login-panel" aria-labelledby="page-title">
            <div class="brand-mark" aria-hidden="true">✦</div>
            <p class="eyebrow">PHP practical assignment</p>
            <h1 id="page-title">Welcome back</h1>
            <p class="muted">Sign in to open the star pyramid generator.</p>

            <?php if ($timeoutNotice): ?>
                <p class="message error-message" role="status">Your session expired after 15 minutes of inactivity. Please log in again.</p>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <p class="message error-message" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post" action="index.php" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e($formToken) ?>">

                <div class="form-field">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        autocomplete="username"
                        placeholder="Enter your username"
                        required
                    >
                </div>

                <div class="form-field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button type="submit" class="button button-primary">Login</button>
            </form>

            <p class="footnote">Demo account credentials are documented in README.md.</p>
        </section>
    </main>
</body>
</html>
