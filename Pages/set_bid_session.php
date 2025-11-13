<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auction_id_to_bid'])) {
    
    $auction_id = filter_var($_POST['auction_id_to_bid'], FILTER_SANITIZE_NUMBER_INT);

    $_SESSION['auction_id_to_bid'] = $auction_id;

    // ✅ Redirect to create_bid.php instead of bid_history.php
    header("Location: create_bid.php");
    exit;
} else {
    header("Location: buyer_auctions.php");
    exit;
}
?>
