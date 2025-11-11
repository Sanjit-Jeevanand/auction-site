<html>

<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
?>

<?php

    $bidder_id = current_user_id(); 

    // get list of auctions (buyer view) and current highest bid
    $sql_buyer_auctions = "SELECT 
    a.auction_id,i.item_id,i.title, u.display_name, a.starting_price, 
    (
        SELECT MAX(b.amount)
        FROM bids b
        WHERE b.auction_id = a.auction_id
    ) AS current_high_bid, a.start_time,a.end_time,a.current_status
    FROM auctions a
    LEFT JOIN items i on a.item_id = i.item_id
    INNER JOIN users u on i.seller_id = u.user_id";


    $stmt_buyer_auctions = $pdo -> prepare($sql_buyer_auctions);
    $stmt_buyer_auctions -> execute();
    $auctions = $stmt_buyer_auctions->fetchAll(PDO::FETCH_ASSOC);

    $sql_highest_bid = "SELECT "

?>

<div class="auction-list-container">
    <h2>Active Auctions</h2>
    
    <?php if (count($auctions) > 0): ?>
        <table class="table table-striped auction-table">
            <thead>
                <tr>
                    <th>Auction ID</th>
                    <th>Item ID</th>
                    <th>Item Title</th>
                    <th>Seller</th>
                    <th>Starting Price</th>
                    <th>Highest bid</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auctions as $auction): ?>
                <tr>
                    <td><?php echo htmlspecialchars($auction['auction_id']); ?></td>
                    <td>
                            <?php echo htmlspecialchars($auction['item_id']); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($auction['title']); ?></td>
                    <td><?php echo htmlspecialchars($auction['display_name']); ?></td>
                    <td>$<?php echo number_format($auction['starting_price'], 2); ?></td>

                    <td><?php 
                        if ($auction['current_high_bid']){
                            echo '$' . number_format($auction['current_high_bid'], 2);
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>

                    <td><?php echo date('Y-m-d H:i', strtotime($auction['start_time'])); ?></td>
                    <td>
                        <strong 
                            style="color: <?php echo (strtotime($auction['end_time']) < time() ? 'red' : 'green'); ?>;">
                            <?php echo date('Y-m-d H:i', strtotime($auction['end_time'])); ?>
                        </strong>
                    </td>
                    <td><?php echo htmlspecialchars($auction['current_status']); ?></td>
                    <form action="set_bid_session.php" method="POST" style="display:inline;">
                        <input type="hidden" name="auction_id_to_bid" value="<?php echo $auction['auction_id']; ?>">
                        <td><button type="submit" class="btn btn-success">
                                Check bidding status
                        </button></td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="alert alert-info">There are no active auctions right now.</p>
    <?php endif; ?>
</div>

</html>