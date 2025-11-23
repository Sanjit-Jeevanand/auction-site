<?php
// --- Forcefully reset any previous session (fixes wrong user auto-login) ---
if (session_status() === PHP_SESSION_ACTIVE) {
    // 1️⃣ Unset all existing session data
    $_SESSION = [];

    // 2️⃣ Destroy the current session
    session_unset();
    session_destroy();

    // 3️⃣ Remove any lingering session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

// 4️⃣ Always start a fresh session
session_start();

// --- Load dependencies ---
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } elseif (!$user['is_email_confirmed']) {
            $errors[] = 'Please confirm your email before logging in.';
        } else {
            // ✅ Fix: Always regenerate session and store user info
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            // Optional: still call your helper if it sets extra values
            if (function_exists('login_user')) {
                login_user((int)$user['user_id'], $user['role']);
            }

            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = :id")
                ->execute(['id' => $user['user_id']]);

            header('Location: profile.php');
            exit;
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h2>Login</h2>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('h', $errors)) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <div class="mb-3">
      <label>Email</label>
      <input name="email" type="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Password</label>
      <input name="password" type="password" class="form-control" required>
    </div>
    <button class="btn btn-primary">Login</button>
    <div class="mt-2">
      <a href="forgot_password.php">Forgot password?</a>
    </div>
  </form>
</div>
</body>
</html>
