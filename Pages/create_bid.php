<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/notify.php';

?>



<?php

    $price_errors = [];
    $current_bidder_id = current_user_id();
    $current_auction_id = $_SESSION['auction_id_to_bid'];

    if (!$current_bidder_id) {
    echo "<p>Please log in to bid.</p>";
    exit;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_bid'])){
        date_default_timezone_set('Europe/London');
        $bid_time = date('Y-m-d H:i:s');

        $amount = trim($_POST['amount'] ?? '');

        if(!is_numeric($amount) || $amount <= 0){
            $price_errors[] = "Bidding amount needs to be a valid number greater than 0";
        }

        $is_proxy = filter_input(INPUT_POST, 'is_proxy', FILTER_VALIDATE_INT);

        if ($is_proxy !== 0 && $is_proxy !== 1) {
            $price_errors[] = "Please select a valid bid type.";
 
        }

        if(empty($price_errors)){
            $sql_insert_bid = "INSERT INTO bids
            (auction_id, bidder_id,amount, bid_time, is_proxy)
            values
            (:auction_id,:bidder_id,:amount, :bid_time, :is_proxy)";

            $stmt_insert_bid = $pdo -> prepare($sql_insert_bid);
            $bid_params = [
                    'auction_id'=> $current_auction_id,
                    'bidder_id' => $current_bidder_id,
                    'amount' => $amount,
                    'bid_time' => $bid_time,
                    'is_proxy' => $is_proxy
                ];
            
            try {
                $stmt_insert_bid -> execute($bid_params);

                $redirect_url = "bid_history.php?status=success&auction_id=" . $current_auction_id;
                // 📨 Add notifications after bid placement
try {
    // Get seller ID for this auction
    $stmt_seller = $pdo->prepare("SELECT seller_id FROM auctions WHERE auction_id = ?");
    $stmt_seller->execute([$current_auction_id]);
    $seller_id = $stmt_seller->fetchColumn();

    // Notify seller that a bid was placed
    if ($seller_id && $seller_id != $current_bidder_id) {
        add_notification(
            $seller_id,
            $current_auction_id,
            'bid',
            'A new bid has been placed on your auction.'
        );
    }

    // Notify the previous top bidder they’ve been outbid
    $stmt_prev = $pdo->prepare("
        SELECT bidder_id FROM bids
        WHERE auction_id = ?
        ORDER BY amount DESC, bid_time ASC LIMIT 1 OFFSET 1
    ");
    $stmt_prev->execute([$current_auction_id]);
    $prev_bidder = $stmt_prev->fetchColumn();

    if ($prev_bidder && $prev_bidder != $current_bidder_id) {
        add_notification(
            $prev_bidder,
            $current_auction_id,
            'bid',
            'You have been outbid by another user.'
        );
    }
} catch (Exception $e) {
    error_log('Notification error: ' . $e->getMessage());
}

                header("Location: /auction-site/Pages/bid_history.php");
                exit; 
                        
            } catch (PDOException $e) {
                $price_errors[]= "Database Error: Auction creation failed. Please check your SQL log.";
            }
        }



    }

?>

<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width-device-width,initial-scale=1.0">
        <title>Place your bid></title>
        <!--link to our stylesheet-->
        <!-- <link rel="stylesheet" href="css/main.css"> -->
         <style>
            .error { color: red; font-weight: bold; }
            .success { color: green; font-weight: bold; }
        </style>
</head>



<body>

    <section class"wrapper-main">

                <?php 
        // Display errors if validation failed
        if (!empty($price_errors)) {
            echo '<div class="error">';
            foreach ($price_errors as $error) {
                echo "<p>$error</p>";
            }
            echo '</div>';
        }
        ?>

        <form action="create_bid.php" method="post">

            <p class="item_id">Auction item id <?php echo $current_auction_id; ?></p>
            <p class="item_id">Bidder id <?php echo $current_bidder_id; ?></p>

            <label for="amount">Amount</label>
            <br>
            <input type="text"id="amount"name="amount"placeholder="Enter your bidding amount">
            <br><br>
            <select id="is_proxy" name="is_proxy" required>
                <option value="0" selected>Standard Bid (One-time)</option>
                <option value="1">Proxy Bid (Set Maximum)</option>
            </select>
            <br><br>

            <button type="submit" name="submit_bid" class="btn btn-primary">
                Place bid
            </button>

        </form>
    </section>

</body>

</html>