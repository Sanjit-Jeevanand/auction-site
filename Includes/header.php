<?php
// ✅ Always start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// User status
$is_logged_in = !empty($_SESSION['user_id']);
$current_role = $_SESSION['role'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);

// ✅ Get unread notification count *inside PHP*
$unread_count = 0;
if ($is_logged_in) {
    try {
        $stmt_n = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt_n->execute([$_SESSION['user_id']]);
        $unread_count = $stmt_n->fetchColumn();
    } catch (Exception $e) {
        $unread_count = 0; // fail-safe
    }
}
?>


<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Auction Site</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php if (isset($_SESSION['user_id'])): ?>
    <small style="color:red; font-weight:bold;">
        
    </small>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="/auction-site/Pages/profile.php">AuctionSite</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'profile.php' ? 'active' : '' ?>" href="/auction-site/Pages/profile.php">Profile</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'profile_edit.php' ? 'active' : '' ?>" href="/auction-site/Pages/profile_edit.php">Edit</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'register.php' ? 'active' : '' ?>" href="/auction-site/Pages/register.php">Register</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'login.php' ? 'active' : '' ?>" href="/auction-site/Pages/login.php">Login</a>
        </li>

        <?php if ($is_logged_in && ($current_role === 'seller' || $current_role === 'both')): ?>
        <li class="nav-item">

          <a class="nav-link" href="/auction-site/Pages/seller_items.php">Your items</a>
        </li>
        <?php endif; ?>
        <?php if ($is_logged_in && ($current_role === 'seller')): ?>
        <li class="nav-item">
          <a class="nav-link" href="/auction-site/Pages/seller_auctions.php">Seller auction</a>
        </li>
        <?php endif; ?>
        <?php if ($is_logged_in && $current_role === 'buyer'): ?>
    <!-- Buyer auction link -->
    <li class="nav-item">
        <a class="nav-link <?= $current_page === 'buyer_auctions.php' ? 'active' : '' ?>"
           href="/auction-site/Pages/buyer_auctions.php">
            Buyer auction
        </a>
    </li>

    <!-- Create Auction link -->
    <li class="nav-item">
        <a class="nav-link <?= $current_page === 'create_auction.php' ? 'active' : '' ?>"
           href="/auction-site/Pages/create_auction.php">
            Create Auction
        </a>
    </li>
<?php endif; ?>


        <?php if ($is_logged_in): ?>
    <?php
        $auction_list_page = ($current_role === 'seller' || $current_role === 'both')
            ? 'seller_auctions.php'
            : 'buyer_auctions.php';
    ?>
    <li class="nav-item">
      <a class="nav-link <?= $current_page === $auction_list_page ? 'active' : '' ?>"
         href="/auction-site/Pages/<?= $auction_list_page ?>">
         List of auction
      </a>
    </li>
<?php endif; ?>

        <?php if ($is_logged_in): ?>
<li class="nav-item">
  <a class="nav-link <?= $current_page === 'notifications.php' ? 'active' : '' ?>"
     href="/auction-site/Pages/notifications.php">
      🔔 Notifications
      <?php if ($unread_count > 0): ?>
          <span class="badge bg-danger ms-1"><?= $unread_count ?></span>
      <?php endif; ?>
  </a>
</li>
<?php endif; ?>

      </ul>

      <?php if ($is_logged_in): ?>
        <a href="/auction-site/Pages/logout.php" class="btn btn-outline-light">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
