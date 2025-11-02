<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
?>

<?php

// session_start();
$seller_id = current_user_id();
$item_id = $_SESSION['item_id_to_auction'];
$item_name = $_SESSION['item_name_to_auction'];

if (isset($_POST['submit_auction'])) {
    
    $starting_price = trim($_POST['startprice'] ?? '');
    $reserve_price = trim($_POST['reserveprice'] ?? '');
    $start_time = trim($_POST['starttime'] ?? '');
    $end_time = trim($_POST['endtime'] ?? '');
    $auction_status = trim($_POST['auction_status'] ?? '');
    date_default_timezone_set('Europe/London');
    $auction_timestamp = date('Y-m-d H:i:s');

    unset($_SESSION['item_to_auction_id']); 
    unset($_SESSION['item_name_to_auction']);

    if (!$item_id || !$seller_id || !is_numeric($starting_price) || empty($end_time)) {
        $error = "Missing required auction data.";
    }

    if (!isset($error)) {

        $sql_insert_auction = "INSERT INTO auctions 
        (item_id, seller_id,starting_price,reserve_price, start_time,end_time, current_status, created_at)
        values
        (:item_id,:seller_id,:starting_price,:reserve_price,:start_time,:end_time,:current_status,:created_at)";

        $stmt_insert_auction = $pdo -> prepare($sql_insert_auction);
        $auction_params = [
            'item_id'=> $item_id,
            'seller_id' => $seller_id,
            'starting_price' => $starting_price,
            'reserve_price' => $reserve_price,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'current_status' => $auction_status,
            'created_at' => $auction_timestamp 
        ];

        try {
                $stmt_insert_auction->execute($auction_params);
                $new_auction_id = $pdo->lastInsertId();

                $redirect_url = "list_of_auctions.php?status=success&auction_id=" . $new_auction_id;
                header("Location: /auction-site/Pages/list_of_auctions.php");
                exit; 
                
            } catch (PDOException $e) {
                $error = "Database Error: Auction creation failed. Please check your SQL log.";
            }
    } 
else {
        echo "<p>Error: Could not determine which item to auction. Please select an item from the listings page.</p>";
        header("Location: list_of_items.php"); 
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width-device-width,initial-scale=1.0">
        <title>Auction your item></title>
        <!--link to our stylesheet-->
        <!-- <link rel="stylesheet" href="css/main.css"> -->
</head>

<body>

    <section class"wrapper-main">
        <form action="create_auction.php" method="post">

            <p class="item_id">Auction item id <?php echo $item_id; ?></p>
            <p class="item_name">Auction item name <?php echo $item_name; ?></p>
            <p class="seller_id">Seller for auctioned item <?php echo $seller_id; ?></p>
  
            <label for="startingprice">Starting price</label>
            <br>
            <input type="text"id="startprice"name="startprice"placeholder="Enter your starting price">
            <br><br>

            <label for="reserveprice">Reserve price</label>
            <br>
            <input type="text"id="reserveprice"name="reserveprice"placeholder="Enter your reserve price">
            <br><br>

            <label for="starttime">Start time:</label>
            <br>
            <input type="date" id="starttime" name="starttime">
            <br><br>

            <label for="endtime">End time:</label>
            <br>
            <input type="date" id="endtime" name="endtime">
            <br><br>

            <label>Current status</label>
            <br><br>
            <input type="radio",id="scheduled"name="auction_status"value="scheduled">
            <label for="message">Scheduled</label>
            <input type="radio",id="running"name="auction_status"value="running">
            <label for="message">Running</label>
            <input type="radio",id="ended"name="auction_status"value="ended">
            <label for="message">Ended</label>
            <input type="radio",id="cancelled"name="auction_status"value="cancelled">
            <label for="message">Cancelled</label>
            <br></br>

            <button type="submit" name="submit_auction" class="btn btn-primary">
                Submit Item to Auction
            </button>

        </form>
    </section>

</body>

</html>