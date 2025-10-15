<?php
require_once __DIR__ . '/includes/db.php';

$token = $_GET['token'] ?? '';
$message = '';

if ($token) {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email_confirmation_token = :token");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("UPDATE users SET is_email_confirmed = 1, email_confirmation_token = NULL WHERE user_id = :id")
            ->execute(['id' => $user['user_id']]);
        $message = '✅ Email successfully confirmed. You can now log in.';
    } else {
        $message = '❌ Invalid or expired confirmation link.';
    }
} else {
    $message = 'No confirmation token provided.';
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Email Confirmation</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <div class="alert alert-info"><?= h($message) ?></div>
  <a href="login.php" class="btn btn-primary">Go to Login</a>
</div>
</body>
</html>