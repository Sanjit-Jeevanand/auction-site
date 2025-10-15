<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
login_required();

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->execute(['id' => current_user_id()]);
$user = $stmt->fetch();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Profile</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h2>Profile</h2>
  <p><strong>Email:</strong> <?= h($user['email']) ?></p>
  <p><strong>Name:</strong> <?= h($user['first_name'].' '.$user['last_name']) ?></p>
  <p><strong>Role:</strong> <?= h($user['role']) ?></p>
  <p><strong>Email confirmed:</strong> <?= $user['is_email_confirmed'] ? 'Yes' : 'No' ?></p>

  <?php if (in_array($user['role'], ['seller','both'])): ?>
    <a href="/auction-site/seller_dashboard.php" class="btn btn-success">Seller Dashboard</a>
  <?php endif; ?>

  <a href="logout.php" class="btn btn-secondary mt-2">Logout</a>
</div>
</body>
</html>