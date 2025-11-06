<html>

<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
?>

<?php

    $sql_get_auction_list = "SELECT 
    a.auction_id,i.item_id,i.title, u.display_name, a.starting_price, a.reserve_price, a.start_time,a.end_time,a.current_status
    FROM auctions a
    LEFT JOIN items i on a.item_id = i.item_id
    INNER JOIN users u on i.seller_id = u.user_id ";


    $stmt_get_auction_list = $pdo -> prepare($sql_get_auction_list);
    $stmt_get_auction_list -> execute();
    $auctions = $stmt_get_auction_list->fetchAll(PDO::FETCH_ASSOC);

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
                    <th>Reserve Price</th>
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
                    <td>$<?php echo number_format($auction['reserve_price'], 2); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($auction['start_time'])); ?></td>
                    <td>
                        <strong 
                            style="color: <?php echo (strtotime($auction['end_time']) < time() ? 'red' : 'green'); ?>;">
                            <?php echo date('Y-m-d H:i', strtotime($auction['end_time'])); ?>
                        </strong>
                    </td>
                    <td><?php echo htmlspecialchars($auction['current_status']); ?></td>
                    <td><button type="submit" class="btn btn-success">
                                Bid Item
                    </button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="alert alert-info">There are no active auctions right now.</p>
    <?php endif; ?>
</div>

</html>