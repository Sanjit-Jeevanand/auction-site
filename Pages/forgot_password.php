<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$errors = [];
$success = false;
$link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        // check user exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = 'No account found with that email.';
        } else {
            // generate token and expiry
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_expires = :exp WHERE user_id = :id");
            $stmt->execute(['token' => $token, 'exp' => $expires, 'id' => $user['user_id']]);

            $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                  . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'])
                  . '/reset_password.php?token=' . urlencode($token);

            $success = true;
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Forgot Password</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h2>Forgot Password</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">A reset link has been generated.</div>
    <div class="alert alert-info">
      <strong>Demo link:</strong>
      <a href="<?= htmlspecialchars($link) ?>"><?= htmlspecialchars($link) ?></a>
    </div>
  <?php else: ?>
    <form method="post" class="mt-3">
      <div class="mb-3">
        <label>Email address</label>
        <input name="email" type="email" class="form-control" required>
      </div>
      <button class="btn btn-primary">Send reset link</button>
      <a href="login.php" class="btn btn-link">Back to login</a>
    </form>
  <?php endif; ?>
</div>
</body>
</html>