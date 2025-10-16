<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$errors = [];
$success = false;
$token = $_GET['token'] ?? '';

if (!$token) {
    die('<div class="p-4 alert alert-danger">No token provided.</div>');
}

// look up token
$stmt = $pdo->prepare("SELECT user_id, reset_expires FROM users WHERE reset_token = :t LIMIT 1");
$stmt->execute(['t' => $token]);
$user = $stmt->fetch();

if (!$user) {
    die('<div class="p-4 alert alert-danger">Invalid or expired token.</div>');
}

if (strtotime($user['reset_expires']) < time()) {
    die('<div class="p-4 alert alert-danger">Reset link has expired. Please request again.</div>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, reset_token = NULL, reset_expires = NULL WHERE user_id = :id");
        $stmt->execute(['hash' => $hash, 'id' => $user['user_id']]);
        $success = true;
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h2>Reset Password</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">Password reset successful! You can now <a href="login.php">log in</a>.</div>
  <?php else: ?>
    <form method="post">
      <div class="mb-3">
        <label>New Password</label>
        <input name="password" type="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Confirm Password</label>
        <input name="password_confirm" type="password" class="form-control" required>
      </div>
      <button class="btn btn-primary">Reset Password</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>