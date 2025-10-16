<?php
// includes/helpers.php
// Secure session helpers, CSRF, role helpers, and timeout logic

// CONFIG: tweak these values if you need different behaviour
define('SESSION_INACTIVITY_TIMEOUT', 30 * 60); // 30 minutes (in seconds)
define('SESSION_ABSOLUTE_TIMEOUT', 24 * 60 * 60); // 24 hours absolute max (optional). Set to 0 to disable.

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Secure cookie params — adjust domain/path as needed
        $cookieParams = session_get_cookie_params();
        $cookieParams['httponly'] = true;
        $cookieParams['secure'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        // Recommended SameSite attribute where supported:
        // Use "Lax" for general usage; "Strict" may break cross-site flows.
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $cookieParams['lifetime'] ?? 0,
                'path'     => $cookieParams['path'] ?? '/',
                'domain'   => $cookieParams['domain'] ?? '',
                'secure'   => $cookieParams['secure'],
                'httponly' => $cookieParams['httponly'],
                'samesite' => 'Lax'
            ]);
        } else {
            // fallback for older PHP: samesite not supported by session_set_cookie_params
            session_set_cookie_params(
                $cookieParams['lifetime'] ?? 0,
                $cookieParams['path'] . '; samesite=Lax',
                $cookieParams['domain'] ?? '',
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }
        session_start();
        // Don't allow session fixation via URL
        ini_set('session.use_only_cookies', 1);
    }
}

// Call on include so pages always run session logic
start_secure_session();

/* -------------------------
   Session management utils
   ------------------------- */

// Regenerate session id on login (and record timestamps)
function login_user(int $user_id, string $role = 'buyer'): void {
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = $role;
    $_SESSION['last_activity'] = time(); // used for inactivity timeout
    $_SESSION['created_at'] = $_SESSION['created_at'] ?? time(); // absolute session creation
}

// Call this at the top of protected pages to enforce timeouts + login
function enforce_session_timeout(): void {
    // if no session values -> nothing to do
    if (empty($_SESSION['user_id'])) return;

    $now = time();

    // Inactivity timeout
    $last = $_SESSION['last_activity'] ?? $now;
    if (SESSION_INACTIVITY_TIMEOUT > 0 && ($now - $last) > SESSION_INACTIVITY_TIMEOUT) {
        // Timeout reached — destroy session and redirect to login
        _destroy_session_and_redirect('Session expired due to inactivity. Please log in again.');
    }

    // Absolute timeout (optional)
    if (SESSION_ABSOLUTE_TIMEOUT > 0) {
        $created = $_SESSION['created_at'] ?? $now;
        if (($now - $created) > SESSION_ABSOLUTE_TIMEOUT) {
            _destroy_session_and_redirect('Session expired. Please log in again.');
        }
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = $now;
}

function _destroy_session_and_redirect(string $flashMessage = ''): void {
    // clear session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
    session_destroy();

    // store one-time flash by starting a fresh session to show message after redirect
    session_start();
    if ($flashMessage !== '') $_SESSION['flash'] = $flashMessage;

    header('Location: /auction-site/Pages/login.php');
    exit;
}

/* -------------------------
   Existing helpers (login_required, role enforcement, etc)
   ------------------------- */

function login_required(): void {
    // Start session already handled in start_secure_session
    if (empty($_SESSION['user_id'])) {
        header('Location: /auction-site/Pages/login.php');
        exit;
    }
    // enforce timeouts for active sessions
    enforce_session_timeout();
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_role(string $role): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /auction-site/Pages/login.php');
        exit;
    }
    // enforce timeout first
    enforce_session_timeout();

    $user_role = $_SESSION['role'] ?? 'buyer';
    if ($user_role !== $role && $user_role !== 'both' && $user_role !== 'admin') {
        http_response_code(403);
        echo "<div style='padding:2rem; font-family:sans-serif;'>
                <h3>Access Denied</h3>
                <p>You must have the <strong>" . htmlspecialchars($role) . "</strong> role to access this page.</p>
                <a href='/auction-site/Pages/profile.php'>Back to profile</a>
              </div>";
        exit;
    }
}

/* -------------------------
   CSRF helpers and sanitiser
   ------------------------- */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}