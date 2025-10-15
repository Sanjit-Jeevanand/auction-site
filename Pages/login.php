<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

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
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = :id")->execute(['id' => $user['user_id']]);
            header('Location: profile.php');
            exit;
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h2>Login</h2>
  <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('h',$errors)) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <div class="mb-3"><label>Email</label><input name="email" type="email" class="form-control" required></div>
    <div class="mb-3"><label>Password</label><input name="password" type="password" class="form-control" required></div>
    <button class="btn btn-primary">Login</button>
  </form>
</div>
</body>
</html>