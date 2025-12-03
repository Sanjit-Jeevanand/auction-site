<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notify.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = current_user_id();
if (!$user_id) {
    // Not logged in
    header('Location: ../Pages/watchlist.php?watch=login');
    exit;
}

if (empty($_GET['auction_id']) || !ctype_digit($_GET['auction_id'])) {
    header('Location: ../Pages/watchlist.php?watch=invalid');
    exit;
}

$auction_id = (int) $_GET['auction_id'];

// ✅ Check auction exists
$stmt_check = $pdo->prepare("SELECT auction_id FROM auctions WHERE auction_id = ?");
$stmt_check->execute([$auction_id]);
if ($stmt_check->rowCount() === 0) {
    header('Location: ../Pages/watchlist.php?watch=missing');
    exit;
}

// ✅ Check if already in watchlist
$stmt_duplicate = $pdo->prepare(
    "SELECT 1 FROM watchlist WHERE user_id = ? AND auction_id = ?"
);
$stmt_duplicate->execute([$user_id, $auction_id]);

if ($stmt_duplicate->fetchColumn()) {
    // Already there
    header('Location: ../Pages/watchlist.php?watch=exists');
    exit;
}

// ✅ Insert new watchlist row
$stmt_insert = $pdo->prepare("
    INSERT INTO watchlist (user_id, auction_id, created_at)
    VALUES (?, ?, NOW())
");
$stmt_insert->execute([$user_id, $auction_id]);

// ✅ Optional: add self-notification
add_notification(
    $user_id,
    $auction_id,
    'watchlist',
    'You added this auction to your watchlist.'
);

// Go to watchlist page with success banner
header('Location: ../Pages/watchlist.php?watch=success');
exit;
