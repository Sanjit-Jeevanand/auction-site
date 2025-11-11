<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id_to_auction']) && isset($_POST['item_name_to_auction'])) {
    
    $item_id = filter_var($_POST['item_id_to_auction'], FILTER_SANITIZE_NUMBER_INT);
    $item_name = trim($_POST['item_name_to_auction'] ?? '');

    $_SESSION['item_id_to_auction'] = $item_id;
    $_SESSION['item_name_to_auction'] = $item_name;

    header("Location: create_auction.php");
    exit;
} else {

    header("Location: seller_items.php");
    exit;
}
?>