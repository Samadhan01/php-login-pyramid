<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

// AJAX requests expect JSON; ordinary form submissions keep a PHP-rendered fallback.
$isAjaxRequest = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (($_POST['ajax'] ?? '') === '1'
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');

// Check login before processing any pyramid request.
$hadUserSession = isset($_SESSION['username']) && is_string($_SESSION['username']);
if (!is_logged_in()) {
    if ($isAjaxRequest) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Your session has expired. Please log in again.',
        ]);
        exit;
    }

    header('Location: index.php' . ($hadUserSession ? '?timeout=1' : ''));
    exit;
}

/** Build each shape using loops, without str_repeat(). */
function build_pyramid_lines(int $rows, string $shape): array
{
    $lines = [];
    $totalRows = $shape === 'diamond' ? (2 * $rows) - 1 : $rows;

    for ($lineNumber = 1; $lineNumber <= $totalRows; $lineNumber++) {
        if ($shape === 'inverted') {
            $rowWidth = $rows - $lineNumber + 1;
        } elseif ($shape === 'diamond' && $lineNumber > $rows) {
            $rowWidth = (2 * $rows) - $lineNumber;
        } else {
            $rowWidth = $lineNumber;
        }

        $line = '';
        $sideSpaces = $rows - $rowWidth;

        // Left padding centres this row relative to the widest row.
        for ($space = 0; $space < $sideSpaces; $space++) {
            $line .= ' ';
        }

        // Row 1 has 1 star, row 2 has 3, row 3 has 5, and so on.
        $starsInThisRow = (2 * $rowWidth) - 1;
        for ($star = 0; $star < $starsInThisRow; $star++) {
            $line .= '*';
        }

        // Right padding keeps each line the same total width.
        for ($space = 0; $space < $sideSpaces; $space++) {
            $line .= ' ';
        }

        $lines[] = $line;
    }

    return $lines;
}

function shape_label(string $shape): string
{
    if ($shape === 'inverted') {
        return 'Inverted pyramid';
    }
    if ($shape === 'diamond') {
        return 'Diamond';
    }
    return 'Star pyramid';
}

$error = '';
$csrfValid = true;
$rowsInput = '';
$rowsCount = null;
$shape = 'pyramid';
$pyramidLines = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedRows = $_POST['rows'] ?? '';
    $submittedShape = $_POST['shape'] ?? 'pyramid';
    $rowsInput = is_string($submittedRows) ? $submittedRows : '';

    $csrfValid = verify_csrf_token();
    if (!$csrfValid) {
        $error = 'Your form expired. Please refresh the page and try again.';
    } elseif (!is_string($submittedRows)) {
        $error = 'Please enter a whole number from 1 to 50.';
    } elseif (!is_string($submittedShape)
        || !in_array($submittedShape, ['pyramid', 'inverted', 'diamond'], true)) {
        $error = 'Please choose a valid pyramid shape.';
    } else {
        $shape = $submittedShape;
        $cleanRows = trim($submittedRows);

        // ctype_digit rejects empty text, negative numbers, decimals, and letters.
        if ($cleanRows === '' || !ctype_digit($cleanRows)) {
            $error = 'Please enter a whole number from 1 to 50.';
        } else {
            $candidate = (int) $cleanRows;

            if ($candidate < 1 || $candidate > 50) {
                $error = 'The number must be between 1 and 50.';
            } else {
                $rowsCount = $candidate;
                $pyramidLines = build_pyramid_lines($rowsCount, $shape);
            }
        }
    }

    // The JavaScript submits AJAX requests, so the page can update without reloading.
    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$csrfValid) {
            http_response_code(403);
        } elseif ($error !== '') {
            http_response_code(422);
        }

        echo json_encode([
            'success' => $error === '',
            'message' => $error,
            'rows' => $rowsCount,
            'shape' => $shape,
            'label' => shape_label($shape),
            'lines' => $pyramidLines,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$formToken = csrf_token();
$initialLabel = shape_label($shape);
$totalRows = $rowsCount !== null && $shape === 'diamond' ? (2 * $rowsCount) - 1 : $rowsCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Star Pyramid</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="page-shell dashboard-shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">PHP practical assignment</p>
                <h1>Star Pyramid</h1>
                <p class="welcome-message">Welcome, <strong><?= e($_SESSION['username']) ?></strong>!</p>
            </div>
            <div class="user-area">
                <a class="button button-light" href="logout.php">Logout</a>
            </div>
        </header>

        <section class="panel generator-panel" aria-labelledby="generator-title">
            <div class="section-heading">
                <div class="icon-circle" aria-hidden="true">✳</div>
                <div>
                    <h2 id="generator-title">Generate a shape</h2>
                    <p class="muted">Enter a whole number from 1 to 50. Press Enter or select Draw shape.</p>
                </div>
            </div>

            <p class="message error-message" id="form-error" role="alert" hidden></p>
            <?php if ($error !== ''): ?>
                <p class="message error-message" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post" action="pyramid.php" class="generator-form" id="pyramid-form">
                <input type="hidden" name="csrf_token" value="<?= e($formToken) ?>">
                <div class="form-controls">
                    <div class="form-field grow-field">
                        <label for="rows">Number of rows (N)</label>
                        <input
                            type="number"
                            id="rows"
                            name="rows"
                            min="1"
                            max="50"
                            step="1"
                            value="<?= e($rowsInput) ?>"
                            placeholder="For example, 5"
                            aria-describedby="rows-help"
                            required
                        >
                    </div>
                    <div class="form-field shape-field">
                        <label for="shape">Shape</label>
                        <select id="shape" name="shape">
                            <option value="pyramid" <?= $shape === 'pyramid' ? 'selected' : '' ?>>Star pyramid</option>
                            <option value="inverted" <?= $shape === 'inverted' ? 'selected' : '' ?>>Inverted pyramid</option>
                            <option value="diamond" <?= $shape === 'diamond' ? 'selected' : '' ?>>Diamond</option>
                        </select>
                    </div>
                    <button type="submit" class="button button-primary" id="draw-button">Draw shape</button>
                </div>
                <small class="input-help" id="rows-help">Valid values: 1–50. Decimals and negative numbers are not accepted.</small>
            </form>
        </section>

        <section class="panel result-panel" id="result-panel" aria-labelledby="result-title" <?= $rowsCount === null ? 'hidden' : '' ?>>
            <div class="result-heading">
                <div>
                    <p class="eyebrow">Your result</p>
                    <h2 id="result-title"><?= e($initialLabel) ?><?= $rowsCount !== null ? ' with N = ' . e((string) $rowsCount) : '' ?></h2>
                    <p class="muted result-detail" id="result-detail"><?= $totalRows !== null ? e((string) $totalRows) . ' total rows' : '' ?></p>
                </div>
                <span class="row-badge" id="row-badge"><?= $rowsCount !== null ? 'N = ' . e((string) $rowsCount) : '' ?></span>
            </div>
            <pre class="pyramid-output" id="pyramid-output" aria-live="polite"><?php foreach ($pyramidLines as $line): ?><?= e($line) . "\n" ?><?php endforeach; ?></pre>
        </section>

        <section class="empty-state" id="empty-state" <?= $rowsCount !== null ? 'hidden' : '' ?>>
            <div class="empty-star" aria-hidden="true">✧</div>
            <h2>Your shape will appear here</h2>
            <p class="muted">Enter a number above and choose a shape to get started.</p>
        </section>

        <footer class="page-footer">Your session automatically expires after 15 minutes of inactivity.</footer>
    </main>

    <script>
        const form = document.getElementById('pyramid-form');
        const drawButton = document.getElementById('draw-button');
        const formError = document.getElementById('form-error');
        const resultPanel = document.getElementById('result-panel');
        const emptyState = document.getElementById('empty-state');
        const resultTitle = document.getElementById('result-title');
        const resultDetail = document.getElementById('result-detail');
        const rowBadge = document.getElementById('row-badge');
        const pyramidOutput = document.getElementById('pyramid-output');

        // Use fetch so changing the input redraws the shape without a page reload.
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            formError.hidden = true;
            formError.textContent = '';
            drawButton.disabled = true;
            drawButton.textContent = 'Drawing...';

            const formData = new FormData(form);
            formData.append('ajax', '1');

            try {
                const response = await fetch('pyramid.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                if (response.status === 401) {
                    window.location.replace('index.php?timeout=1');
                    return;
                }

                if (!response.ok || !data.success) {
                    formError.textContent = data.message || 'Unable to draw this shape. Please try again.';
                    formError.hidden = false;
                    return;
                }

                const allRows = data.lines.length;
                resultTitle.textContent = data.label + ' with N = ' + data.rows;
                resultDetail.textContent = allRows + ' total row' + (allRows === 1 ? '' : 's');
                rowBadge.textContent = 'N = ' + data.rows;
                pyramidOutput.textContent = data.lines.join('\n') + '\n';
                resultPanel.hidden = false;
                emptyState.hidden = true;
            } catch (error) {
                formError.textContent = 'Could not reach the PHP server. Check that it is still running and try again.';
                formError.hidden = false;
            } finally {
                drawButton.disabled = false;
                drawButton.textContent = 'Draw shape';
            }
        });

        // Client-side convenience timer; PHP also checks the real session timeout.
        const SESSION_TIMEOUT_MS = 15 * 60 * 1000;
        let inactivityTimer;

        function resetInactivityTimer() {
            window.clearTimeout(inactivityTimer);
            inactivityTimer = window.setTimeout(function () {
                window.location.replace('index.php?timeout=1');
            }, SESSION_TIMEOUT_MS);
        }

        ['click', 'keydown', 'input', 'scroll', 'touchstart'].forEach(function (eventName) {
            document.addEventListener(eventName, resetInactivityTimer, { passive: true });
        });
        resetInactivityTimer();
    </script>
</body>
</html>
