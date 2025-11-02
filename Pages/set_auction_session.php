<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id_to_auction'])) {
    
    $item_id = filter_var($_POST['item_id_to_auction'], FILTER_SANITIZE_NUMBER_INT);

    $_SESSION['item_to_auction_id'] = $item_id;

    header("Location: create_auction.php");
    exit;
} else {

    header("Location: list_of_items.php");
    exit;
}
?>