<?php
// Login rate limiting. Called from login.php around password verification.

const LOGIN_MAX_FAILURES = 5;
const LOGIN_WINDOW_MINUTES = 15;

// Returns true if the username has hit or exceeded the failure threshold
// inside the rolling window. When true, login.php should refuse to check
// the password and MUST NOT call recordLoginAttempt() again for that
// request — recording during lockout would slide the window forward and
// trap the user forever.
function isLoginBlocked(PDO $pdo, string $username): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE username = ?
           AND success = 0
           AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$username, LOGIN_WINDOW_MINUTES]);
    return (int)$stmt->fetchColumn() >= LOGIN_MAX_FAILURES;
}

function recordLoginAttempt(PDO $pdo, string $username, bool $success): void {
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)'
    );
    $stmt->execute([$username, $_SERVER['REMOTE_ADDR'] ?? null, $success ? 1 : 0]);
}
