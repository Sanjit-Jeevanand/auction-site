<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/logger.php';
// Optional: check if user logged in
$is_logged_in = !empty($_SESSION['user_id']);
$current_role = $_SESSION['role'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Auction Site</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="/auction-site/Pages/profile.php">AuctionSite</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $current_page==='profile.php' ? 'active' : '' ?>" href="/auction-site/Pages/profile.php">Profile</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page==='profile_edit.php' ? 'active' : '' ?>" href="/auction-site/Pages/profile_edit.php">Edit</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page==='register.php' ? 'active' : '' ?>" href="/auction-site/Pages/register.php">Register</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page==='login.php' ? 'active' : '' ?>" href="/auction-site/Pages/login.php">Login</a>
        </li>
        <?php if ($is_logged_in && ($current_role === 'seller' || $current_role === 'both')): ?>
        <li class="nav-item">
          <a class="nav-link" href="/auction-site/Pages/create_auction.php">Create Auction</a>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <?php if ($is_logged_in): ?>
          <li class="nav-item"><a class="nav-link" href="/auction-site/Pages/logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/auction-site/Pages/login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container">