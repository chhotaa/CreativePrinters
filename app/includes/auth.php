<?php
// Session lifetime knobs. Both are enforced in requireLogin() below.
// IDLE = auto-logout after N seconds of no protected-page activity.
// ABSOLUTE = force re-login after N seconds since login, no matter what.
const SESSION_IDLE_TIMEOUT_SECS = 1800;      // 30 minutes
const SESSION_ABSOLUTE_LIFETIME_SECS = 28800; // 8 hours

if (session_status() === PHP_SESSION_NONE) {
    // Harden the session cookie: HttpOnly (JS can't read it), SameSite=Lax
    // (blocks cross-site POST/nav-from-others attacks), Secure only when
    // the current request is HTTPS. cookie_lifetime=0 means "session cookie"
    // (browser-lifetime), which is the right default -- we enforce our own
    // idle + absolute lifetimes server-side below.
    $onHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $onHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/csrf.php';

/**
 * Kill the current session completely: server data, PHP's session cookie,
 * and any populated $_SESSION values. Used by logout and by expiry.
 */
function endSession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }
    session_destroy();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /app/login.php');
        exit;
    }
    $now = time();
    $loginTime = $_SESSION['login_time'] ?? $now;
    $lastActivity = $_SESSION['last_activity'] ?? $now;
    // Absolute lifetime: hard cap since login time.
    if ($now - $loginTime > SESSION_ABSOLUTE_LIFETIME_SECS) {
        endSession();
        header('Location: /app/login.php?timeout=absolute');
        exit;
    }
    // Idle timeout: no activity for N seconds.
    if ($now - $lastActivity > SESSION_IDLE_TIMEOUT_SECS) {
        endSession();
        header('Location: /app/login.php?timeout=idle');
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

function currentUser() {
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    if (!isset($_SESSION['user_id'])) {
        return $user = ['id' => null, 'username' => null, 'email' => null, 'role_id' => null, 'role_name' => null];
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT u.id, u.username, u.email, u.role_id, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    return $user = $row ?: ['id' => null, 'username' => null, 'email' => null, 'role_id' => null, 'role_name' => null];
}

function hasPermission($moduleKey, $level = 'view') {
    $user = currentUser();
    if ($user['role_name'] === 'Super Admin') {
        return true;
    }
    if (!$user['role_id']) {
        return false;
    }
    static $cache = [];
    $key = $user['role_id'] . ':' . $moduleKey;
    if (!array_key_exists($key, $cache)) {
        global $pdo;
        $stmt = $pdo->prepare('SELECT access_level FROM role_permissions WHERE role_id = ? AND module_key = ?');
        $stmt->execute([$user['role_id'], $moduleKey]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? $row['access_level'] : 'none';
    }
    $rank = ['none' => 0, 'view' => 1, 'edit' => 2];
    return ($rank[$cache[$key]] ?? 0) >= ($rank[$level] ?? 1);
}

function requirePermission($moduleKey, $level = 'view') {
    requireLogin();
    if (!hasPermission($moduleKey, $level)) {
        http_response_code(403);
        die('Access denied. You do not have permission to access this page.');
    }
}

function requireSuperAdmin() {
    requireLogin();
    if (currentUser()['role_name'] !== 'Super Admin') {
        http_response_code(403);
        die('Access denied. Super Admin login required.');
    }
}

function redirectToDashboard() {
    header('Location: /app/index.php');
    exit;
}
