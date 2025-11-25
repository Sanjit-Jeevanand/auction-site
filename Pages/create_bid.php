<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/notify.php';
require_once __DIR__ . '/../includes/proxy.php';

?>


<?php

    $price_errors = [];
    $current_bidder_id = current_user_id();
    $proxy_limit = null;
    $increment = null;
    $current_auction_id = $_SESSION['auction_id_to_bid'];

    if (!$current_bidder_id) {
    echo "<p>Please log in to bid.</p>";
    exit;
    }

    // get minimum bid which is the starting price

    $auction_item_name = 'N/A';
    $starting_price = 0.0;

    try{
        $sql_get_item = "SELECT a.starting_price, i.title
        FROM auctions a
        JOIN items i ON a.item_id=i.item_id
        WHERE a.auction_id = ?";

        $stmt_get_item = $pdo->prepare($sql_get_item);
        $stmt_get_item->execute([$current_auction_id]);

        $result = $stmt_get_item->fetch(PDO::FETCH_ASSOC);

        if($result !==false){
            $reserve_price = (float)$result['starting_price'];
            $starting_price = $reserve_price;
            $auction_item_name = htmlspecialchars($result['title']);
        }


    } catch (PDOEXCEPTION $e){
        error_log("Failed to fetch auction details for auction $current_auction_id: "
        .$e->getMessage(),0);
    }


    //making sure bid amount > starting price
    $reserve_price = 0.0;
    try{
        $sql_reserve = "SELECT starting_price FROM auctions WHERE auction_id = ?";
        $stmt_reserve = $pdo->prepare($sql_reserve);
        $stmt_reserve->execute([$current_auction_id]);
        $result = $stmt_reserve->fetchColumn();

        if($result !== false){
            $reserve_price = (float)$result;
        }
    } catch (PDOException $e){
        error_log("failed to fetch reserve price for auction $current_auction_id: ",0);
    }

    //find current highest bid to set minimum required bid
    $current_high_bid = $starting_price;

    try{
        $sql_high_bid = "SELECT MAX(amount) FROM bids WHERE auction_id = ?";
        $stmt_high_bid = $pdo -> prepare($sql_high_bid);
        $stmt_high_bid->execute([$current_auction_id]);
        $high_bid_result = $stmt_high_bid->fetchColumn();

        if ($high_bid_result !== false && (float)$high_bid_result > $starting_price){
            $minimum_required_bid = (float)$high_bid_result + 1.00;
        } else{
            $minimum_required_bid = $starting_price;
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch high bid",0);
        $minimum_required_bid = $starting_price;
    }



    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_bid'])){
        date_default_timezone_set('Europe/London');
        $bid_time = date('Y-m-d H:i:s');

        $amount = trim($_POST['amount'] ?? '');

        if(!is_numeric($amount) || $amount <= 0){
            $price_errors[] = "Bidding amount needs to be a valid number greater than 0";
        }

        if ($reserve_price > 0 && (float)$amount < $reserve_price){
            $price_errors[] = "Your bid amount must be at least the reserve price $".number_format($reserve_price,2);
        }

        if((float)$amount < $minimum_required_bid){
            $price_errors[] = "Your bid must be higher than current bid. Minimum required bid is $".number_format($minimum_required_bid,2);
        }

        $is_proxy = filter_input(INPUT_POST, 'is_proxy', FILTER_VALIDATE_INT);

        if ($is_proxy !== 0 && $is_proxy !== 1) {
            $price_errors[] = "Please select a valid bid type.";
 
        }

        if($is_proxy === 1) {
            $proxy_limit = trim($_POST['proxy_limit']??'');
            $increment = trim($_POST['increment']??'');

            if(!is_numeric($proxy_limit) || $proxy_limit <=0){
                $price_errors[] = "Proxy limit (maximum bid) needs to be a valid number greater than 0";
            }

            if(!is_numeric($increment) || $increment <=0){
                $price_errors[] = "Proxy increment needs to be a valid number greater than 0";
            }

            if (empty($price_errors) && $amount > $proxy_limit){
                $price_errors[] = "The initial bid amount cannot be greater than proxy limit";
            }

            if(empty($price_errors)){
                $proxy_limit = (float)$proxy_limit;
                $increment = (float)$increment;
            }
        } else if ($is_proxy !==0 ){
            $price_errors[] = "Please select a valid bid type";
        }

        if(empty($price_errors)){
            $sql_insert_bid = "INSERT INTO bids
            (auction_id, bidder_id,amount, bid_time, is_proxy, proxy_limit,increment)
            values
            (:auction_id,:bidder_id,:amount, :bid_time, :is_proxy,:proxy_limit,:increment)";

            $stmt_insert_bid = $pdo -> prepare($sql_insert_bid);
            $bid_params = [
                    'auction_id'=> $current_auction_id,
                    'bidder_id' => $current_bidder_id,
                    'amount' => (float)$amount,
                    'bid_time' => $bid_time,
                    'is_proxy' => $is_proxy,
                    'proxy_limit'=>$proxy_limit,
                    'increment'=>$increment
                ];
            
            try {
                $stmt_insert_bid -> execute($bid_params);

                $new_bid_amount = (float)$amount;
                $new_proxy_limit = ($is_proxy === 1) ? (float)$proxy_limit: null;
                $new_increment = ($is_proxy === 1) ? (float)$increment: null;

                process_proxy_bids(
                    $pdo,
                    $current_auction_id,
                    $current_bidder_id,
                    $new_bid_amount,
                    $new_proxy_limit,
                    $new_increment
                );

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
                
                $redirect_url = "bid_history.php?status=success&auction_id=" . $current_auction_id;
                // 📨 Add notifications after bid placement

            } catch (Exception $e) {
                error_log('Notification error: ' . $e->getMessage());
            }
                            header("Location: /auction-site/Pages/bid_history.php");
                            exit; 
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
            .wrapper-main {
                max-width: 600px;
                margin-left:100px;
            }
        </style>
</head>



<body>

    <section class="wrapper-main">

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

            <p class="item_id">Auction item name <?php echo $auction_item_name; ?></p>
            <p class="item_id">Starting price <?php echo $starting_price; ?></p>
            <p class="item_id">Minimum required bid: $<?php echo number_format($minimum_required_bid,2);?></p>

            <label for="amount">Amount</label>
            <br>
            <input type="text"id="amount"name="amount"placeholder="Enter your bidding amount">
            <br><br>

            <label for ="is_proxy">Bid Type </label>
            <br>
            <select id="is_proxy" name="is_proxy" required onchange="toggleProxyFields()">
                <option value = "0" selected> Standard Bid (One-time) </option>
                <option value = "1"> Proxy Bid (Set maximum)</option>
            </select>
            <br><br>

            <div id="proxy_fields" style="display:none;">
                <h4>Proxy Bid Settings</h4>
                <label for="proxy_limit">Proxy limit (maximum bid) </label>
                <input type="text" id="proxy_limit" name="proxy_limit" placeholder="Enter your maximum limit">
                <br><br>

                <label for="increment">Proxy Increment Amount</label>
                <input type="text" id="increment" name="increment" placeholder="Enter auto-bid increment">
                <br>
            </div>


            <button type="submit" name="submit_bid" class="btn btn-primary">
                Place bid
            </button>

        </form>
    </section>

</body>

<script>
    function toggleProxyFields(){
        const select = document.getElementById('is_proxy');
        const proxyFields = document.getElementById('proxy_fields');

        if (select.value === '1'){
            proxyFields.style.display = 'block';
            //visible only when proxy bid is chosen
            document.getElementById9('proxy_limit').required = true;
            document.getElementById('increment').required = true;
        } else {
            proxyFields.style.display = 'none';
            document.getElementById('proxy_limit').required = false;
            document.getElementById('increment').required = false;
        }
    
    }
    document.addEventListener('DOMContentLoaded',toggleProxyFields);

</script>


</html>