<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
if (file_exists(__DIR__ . '/../includes/logger.php')) {
    require_once __DIR__ . '/../includes/logger.php';
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$token = trim($_GET['token'] ?? '');
$errors = [];
$success = false;

if (!$token) {
    $errors[] = 'No confirmation token provided.';
} else {
    $stmt = $pdo->prepare("SELECT user_id, is_email_confirmed FROM users WHERE email_confirmation_token = :token LIMIT 1");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        $errors[] = 'Invalid or expired confirmation link.';
        if (function_exists('log_event')) {
            log_event('warning', 'user.email_confirm_failed', ['token_hash' => hash('sha256', $token)]);
        }
    } elseif ($user['is_email_confirmed']) {
        $errors[] = 'Your email is already confirmed.';
    } else {
        $update = $pdo->prepare("UPDATE users SET is_email_confirmed = 1, email_confirmation_token = NULL, updated_at = NOW() WHERE user_id = :id");
        $update->execute(['id' => $user['user_id']]);
        $success = true;
        if (function_exists('log_event')) {
            log_event('info', 'user.email_confirmed', ['user_id' => $user['user_id']]);
        }
    }
}
?>

<div class="container py-4">
  <h2>Email Confirmation</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>' . h($e) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <p>Your email has been successfully confirmed!</p>
      <a href="login.php" class="btn btn-success mt-2">Proceed to Login</a>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>