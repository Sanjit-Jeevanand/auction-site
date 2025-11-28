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
    header('Location: ../Pages/buyer_auctions.php?watch=login');
    exit;
}

if (empty($_GET['auction_id']) || !ctype_digit($_GET['auction_id'])) {
    header('Location: ../Pages/buyer_auctions.php?watch=invalid');
    exit;
}

$auction_id = (int) $_GET['auction_id'];

// Delete from watchlist
$stmt = $pdo->prepare(
    "DELETE FROM watchlist WHERE user_id = :uid AND auction_id = :aid"
);
$stmt->execute([
    'uid' => $user_id,
    'aid' => $auction_id,
]);

// ✅ Only add notification if a row was actually deleted
if ($stmt->rowCount() > 0) {
    add_notification(
        $user_id,
        $auction_id,
        'watchlist',
        'You removed this auction from your watchlist.'
    );

    // Removed successfully
    header('Location: ../Pages/buyer_auctions.php?watch=removed');
    exit;
} else {
    // Nothing to delete – it wasn’t in the watchlist
    header('Location: ../Pages/buyer_auctions.php?watch=missing');
    exit;
}

