<html>

<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/active_proxy.php';
?>


<?php

    $bidder_id = current_user_id(); 
    $auction_id = $_SESSION['auction_id_to_bid'];
    $current_role = $_SESSION['role'] ?? null;
    $is_bidder = (isset($current_role) && ($current_role === 'buyer' || $current_role === 'both'));
    $is_auction_live = true;

    // get minimum bid which is the starting price

    $auction_item_name = 'N/A';
    $starting_price = 0.0;

    // get auction item name
    try{
        $sql_get_item = "SELECT a.starting_price, i.title
        FROM auctions a
        JOIN items i ON a.item_id=i.item_id
        WHERE a.auction_id = ?";

        $stmt_get_item = $pdo->prepare($sql_get_item);
        $stmt_get_item->execute([$auction_id]);

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


    // condition to check auction end date
    try{
        $sql_auction_end = "SELECT end_time FROM AUCTIONS 
        WHERE auction_id = :auction_id";

        $stmt_auction_end = $pdo->prepare($sql_auction_end);
        $stmt_auction_end->execute(['auction_id' => $auction_id]);
        $auction_status = $stmt_auction_end->fetch(PDO::FETCH_ASSOC);

        if($auction_status){

            $auction_end_time = new DateTime($auction_status['end_time']);
            $current_time = new DateTime();
            if($current_time > $auction_end_time){
                $is_auction_live = false;
            }
        }

    } catch(PDOException $e){
        error_log("Auction end time error");

    }

    // get list of bids for a particular auction (buyer view)
    try{
        $sql_get_bids = "SELECT 
        u.display_name, b.amount, b.bid_time, b.is_proxy
        FROM bids b
        INNER JOIN users u on b.bidder_id = u.user_id
        WHERE b.auction_id = :auction_id
        ORDER BY b.amount DESC, b.bid_time DESC";

        $stmt_get_bids = $pdo -> prepare($sql_get_bids);
        $stmt_get_bids -> execute (['auction_id' => $auction_id]);
        $bids = $stmt_get_bids->fetchAll(PDO::FETCH_ASSOC);

    }
    catch(PDOException $e) {
        echo "An error occured while retrieving bid data";    
    }
?>

<head>
    <style>
        table {
            border-collapse: collapse; 
            width: 100%; 
        }
        
        th, td {
            
            padding: 12px 20px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .winning-row td { 
            background-color: #d4edda; 
        }
    </style>
</head>

<body>
    <div class="container">
        <h2> Bid history for item: <?php echo $auction_item_name; ?> </h2>
            <?php if ($is_auction_live): ?>
                <?php if ($current_role === 'buyer' || $current_role === 'both'): ?>
                <form action="create_bid.php" method="POST" style="display:inline;">
                    <input type="hidden" name="auction_id_to_bid" value="<?php echo $auction['auction_id']; ?>">
                    <td><button type="submit_bid" class="btn btn-success">
                        Place bid
                    </button></td>
                </form>
                <?php elseif ($current_role === 'seller'): ?>
                    <p style="font-weight: bold;color:blue;">
                        You cannot place a bid on this item 
                    </p>
                <?php endif; ?>
                <?php else: ?>
                    <p style="font-weight: bold; color: darkred;">
                    Bidding is closed for this auction.
                    </p>
            <?php endif; ?>
            
        <?php if (empty($bids)):?>
            <p> Currently there are no bids available for this auction</p>
        <?php else: ?>
            <table class="table table-striped bid-table">
                <thead>
                    <tr>
                        <th>Bidder </th>
                        <th>Amount </th>
                        <th>Time placed </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        foreach ($bids as $index => $bid):
                            $row_class = ($index === 0) ? 'class="winning-row"' : '';
                            $username = htmlspecialchars($bid['display_name']);
                            $hidden_name = substr($username, 0,3) . '***' . substr($username, -3);

                            if ($index === 0) {
                                $display_name = "Current winner - " . $username;
                            }
                            else{
                                $display_name = $hidden_name;
                            }
                    ?>
                    <tr <?php echo $row_class; ?>>
                        <td><?php echo $display_name; ?></td>
                        <td><?php echo $bid['amount']?></td>
                        <td><?php echo $bid['bid_time']?></td>
                    </tr>
                        <?php endforeach;?>
                </tbody>
            </table>
    <?php endif; ?></div>
                        </body>
</html>