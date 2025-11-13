<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🧠 Debug line — see who’s currently logged in
echo "<p style='color:red; font-weight:bold;'>Current session user_id: " . 
     ($_SESSION['user_id'] ?? 'None') . "</p>";


// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/seller_auctions.php?watch=login");
    exit;
}

$user_id = $_SESSION['user_id'];
$auction_id = isset($_GET['auction_id']) ? (int) $_GET['auction_id'] : 0;

// Validate auction ID
if ($auction_id <= 0) {
    header("Location: ../Pages/seller_auctions.php?watch=invalid");
    exit;
}

// ✅ Check if auction exists
$stmt_check = $pdo->prepare("SELECT auction_id FROM auctions WHERE auction_id = ?");
$stmt_check->execute([$auction_id]);
if ($stmt_check->rowCount() === 0) {
    header("Location: ../Pages/seller_auctions.php?watch=missing");
    exit;
}

// ✅ Check for duplicates
$stmt_duplicate = $pdo->prepare("SELECT * FROM watchlist WHERE user_id = ? AND auction_id = ?");
$stmt_duplicate->execute([$user_id, $auction_id]);

if ($stmt_duplicate->rowCount() > 0) {
    // Already exists — no insertion
    header("Location: ../Pages/seller_auctions.php?watch=exists");
    exit;
}

// ✅ Insert new record
$stmt_insert = $pdo->prepare("INSERT INTO watchlist (user_id, auction_id, created_at) VALUES (?, ?, NOW())");
$stmt_insert->execute([$user_id, $auction_id]);

// ✅ Add notification after successful watchlist insert
require_once __DIR__ . '/notify.php';
add_notification($user_id, $auction_id, 'watchlist', 'You added this auction to your watchlist.');

// Redirect back with success message
header("Location: ../Pages/seller_auctions.php?watch=success");
exit;
?>
