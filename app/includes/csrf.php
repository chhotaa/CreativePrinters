<?php
// CSRF protection. auth.php starts the session and then requires this
// file, so the token exists as soon as any authenticated page loads.
//
// Rules:
//   * Every rendered form on an authenticated page must echo the
//     csrfField() output as a hidden input.
//   * Every POST handler on an authenticated page must call
//     verifyCsrf() before touching the database.
//   * Pre-auth flows (login, forgot_password, reset_password) skip
//     this — CSRF requires an authenticated victim, and reset_password
//     already uses its own single-use URL token.

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfToken(): string {
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function verifyCsrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Security check failed — please refresh the page and try again.');
    }
}

// Rotates the CSRF token. Call after login (so a token leaked from the
// pre-auth session can't be reused post-login).
function rotateCsrfToken(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
