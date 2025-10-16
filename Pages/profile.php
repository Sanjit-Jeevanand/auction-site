<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

ini_set('display_errors',1);
error_reporting(E_ALL);

login_required();

$user_id = current_user_id();

// fetch user row
$stmt = $pdo->prepare("SELECT user_id,email,alt_email,first_name,last_name,display_name,title,date_of_birth,phone,country,language,currency,role,is_email_confirmed,subscribe_updates,created_at FROM users WHERE user_id = :id LIMIT 1");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    // session user not found: logout and redirect to login
    header('Location: ../Pages/logout.php');
    exit;
}

// flash message support
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function format_date($d) {
    if (!$d) return '-';
    return htmlspecialchars(date('Y-m-d', strtotime($d)));
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Your profile — Auction Site</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>.kv{font-weight:600}</style>
</head>
<body class="p-4">
<div class="container">
  <h2>Your profile</h2>

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= h($flash) ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <p><span class="kv">Display name:</span> <?= h($user['display_name'] ?? '-') ?></p>
      <p><span class="kv">Title:</span> <?= h($user['title'] ?? '-') ?></p>
      <p><span class="kv">First name:</span> <?= h($user['first_name'] ?? '-') ?></p>
      <p><span class="kv">Last name:</span> <?= h($user['last_name'] ?? '-') ?></p>
      <p><span class="kv">Primary email:</span> <?= h($user['email']) ?> <?= $user['is_email_confirmed'] ? '<span class="badge bg-success">confirmed</span>' : '<span class="badge bg-warning">unconfirmed</span>' ?></p>
      <p><span class="kv">Alternate email:</span> <?= h($user['alt_email'] ?? '-') ?></p>
      <p><span class="kv">Date of birth:</span> <?= format_date($user['date_of_birth']) ?></p>
      <p><span class="kv">Phone:</span> <?= h($user['phone'] ?? '-') ?></p>
      <p><span class="kv">Country:</span> <?= h($user['country'] ?? '-') ?></p>
      <p><span class="kv">Language:</span> <?= h($user['language'] ?? '-') ?></p>
      <p><span class="kv">Currency:</span> <?= h($user['currency'] ?? '-') ?></p>
      <p><span class="kv">Role:</span> <?= h($user['role']) ?></p>
      <p><span class="kv">Subscribed:</span> <?= $user['subscribe_updates'] ? 'Yes' : 'No' ?></p>
      <p><span class="kv">Account created:</span> <?= h($user['created_at']) ?></p>
    </div>
  </div>

  <div class="mb-3">
    <a href="profile_edit.php" class="btn btn-primary">Edit profile</a>
    <a href="logout.php" class="btn btn-secondary">Logout</a>
  </div>
</div>
</body>
</html>