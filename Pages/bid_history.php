<html>

<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
?>


<?php

    $bidder_id = current_user_id(); 
    $auction_id = $_SESSION['auction_id_to_bid'];
    $is_auction_live = true;

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
                $is_auction = false;
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
        <h2> Bid history for: <?php echo $auction_id ?> </h2>

            <?php if ($is_auction_live): ?>
                <form action="create_bid.php" method="POST" style="display:inline;">
                    <input type="hidden" name="auction_id_to_bid" value="<?php echo $auction['auction_id']; ?>">
                    <td><button type="submit_bid" class="btn btn-success">
                        Place bid
                    </button></td>
                </form>
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
                        <th>Type </th>
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
                        <td> <?php 
                            if (($bid['is_proxy'] ?? 0) == 1) {
                                echo '<span style="color: blue;">PROXY</span>';
                            } else {
                                echo 'Standard';
                            }
                        ?></td>
                    </tr>
                        <?php endforeach;?>
                </tbody>
            </table>
    <?php endif; ?></div>
                        </body>
</html>