<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
?>

<?php

$seller_id = current_user_id();
$item_id = $_SESSION['item_id_to_auction'];
$item_name = $_SESSION['item_name_to_auction'];

$all_errors = [];


if (isset($_POST['submit_auction'])) {

    $session_errors = [];
    $time_errors = [];
    $price_errors = [];
    $status_errors = [];

    if (!$item_id || !$seller_id) {
        $session_errors[] = "Missing required auction data. (Item ID or seller ID)";
    }
    
    //handle start price and reserve price validation
    $starting_price = trim($_POST['startprice'] ?? '');
    $reserve_price = trim($_POST['reserveprice'] ?? '');

    if(!is_numeric($starting_price) || $starting_price <= 0){
        $price_errors[] = "Starting price needs to be a valid number greater than 0";
    }

    if (!is_numeric($reserve_price) || $reserve_price <=0) {
        $price_errors[] = "Reserve price must be a valid number greater than 0";
    }

    if (empty($price_errors)) {
        $start_float = floatval($starting_price);
        $reserve_float = floatval($reserve_price);

        if($start_float >= $reserve_float) {
            $price_errors[] = "Starting price must be less than reserve price";
        }
    }

    $current_date = date('Y-m-d');

    // handle start time and end time validation
    $starttime = trim($_POST['starttime'] ?? '');
    $endtime = trim($_POST['endtime'] ?? '');

    $start_time = htmlspecialchars($starttime, ENT_QUOTES, 'UTF-8');
    $end_time = htmlspecialchars($endtime, ENT_QUOTES, 'UTF-8');

    if (empty($start_time) || empty ($end_time)){
        $time_errors[] = "Both start time and end time dates are required. ";
    }

    if (empty($time_errors)){
        try {
            $start_date = new DateTime($start_time);
            $end_date = new DateTime($end_time);

            if($start_date < $current_date){
                $time_errors[] = "Start time cannot be in the past";
            }

            if ($start_date >= $end_date){
                $time_errors[] = "Start time must be before the end time date";
            }
            else {
                $time_success = "Date range is valid";
            }
        }
        catch (Exception $e) {
            $time_errors[] = "One or both dates are in an invalid format. Please use the YYYY-MM-DD format";
        }
        
    }

        // handle auction status
    $auction_status = trim($_POST['auction_status'] ?? 'scheduled');

    if (empty($auction_status)){
        $status_errors[] = "Auction status is required. Please select 'Scheduled' or 'Cancelled'";
    }
    
    // handle auction timestamp
    date_default_timezone_set('Europe/London');
    $auction_timestamp = date('Y-m-d H:i:s');


    $all_errors = array_merge($session_errors,$time_errors,$price_errors,$status_errors);

    if (empty($all_errors)) {

        unset($_SESSION['item_to_auction_id']); 
        unset($_SESSION['item_name_to_auction']);

        $sql_insert_auction = "INSERT INTO auctions 
        (item_id, seller_id,starting_price,reserve_price, start_time,end_time, current_status, created_at)
        values
        (:item_id,:seller_id,:starting_price,:reserve_price,:start_time,:end_time,:current_status,:created_at)";

        $stmt_insert_auction = $pdo -> prepare($sql_insert_auction);
        $auction_params = [
            'item_id'=> $item_id,
            'seller_id' => $seller_id,
            'starting_price' => $start_float,
            'reserve_price' => $reserve_float,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'current_status' => $auction_status,
            'created_at' => $auction_timestamp 
        ];

        try {
                $stmt_insert_auction->execute($auction_params);
                $new_auction_id = $pdo->lastInsertId();

                $redirect_url = "seller_auctions.php?status=success&auction_id=" . $new_auction_id;
                header("Location: /auction-site/Pages/seller_auctions.php");
                exit; 
                
            } catch (PDOException $e) {
                $general_db_error = "Database Error: Auction creation failed. Please check your SQL log.";
                $all_errors[] = $general_db_error;
            }
        } 
}

if (!$item_id || !$seller_id) {
    if (!isset($_POST['submit_auction'])) { 
        header("Location: seller_auctions.php"); 
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
        <style>
            .error { color: red; font-weight: bold; }
            .success { color: green; font-weight: bold; }
            .wrapper-main{
                max-width: 500px;
                margin-left:100px;
            }
        </style>
</head>



<body>

    <section class="wrapper-main">

        <?php if (!empty($all_errors)): ?>
            <?php foreach ($all_errors as $error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endforeach; ?>
        <?php endif; ?>


        <form action="create_auction.php" method="post">

            <p class="item_name">Auction item name <?php echo $item_name; ?></p>
            <!-- <p class="seller_id">Seller for auctioned item <?php echo $seller_id; ?></p> -->
  
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

            <input type="radio",id="cancelled"name="auction_status"value="running">
            <label for="message">Running</label>
            <br></br>

            <button type="submit" name="submit_auction" class="btn btn-primary">
                Submit Item to Auction
            </button>

        </form>
    </section>

</body>

</html>