<html>

<?php 
require_once __DIR__ . '/../Includes/db.php';
require_once __DIR__ . '/../Includes/helpers.php';
require_once __DIR__ . '/../Includes/header.php';

// ✅ Mark notification as read when coming from notifications page
if (isset($_GET['mark_read'])) {
    require_once __DIR__ . '/../Includes/notify.php';
    mark_notification_read((int)$_GET['mark_read']);
}



// ✅ Added feedback banner block (Step 2)
if (isset($_GET['watch'])) {
    $msg = '';
    $class = 'info';

    switch ($_GET['watch']) {
        case 'success':
            $msg = '✅ Added to your watchlist!';
            $class = 'success';
            break;
        case 'exists':
            $msg = 'ℹ️ This auction is already in your watchlist.';
            $class = 'warning';
            break;
        case 'login':
            $msg = 'Please log in to add items to your watchlist.';
            $class = 'warning';
            break;
        case 'invalid':
            $msg = 'Invalid request.';
            $class = 'danger';
            break;
        case 'missing':
            $msg = 'That auction no longer exists.';
            $class = 'danger';
            break;
    }

    if ($msg) {
        echo '<div class="alert alert-' . htmlspecialchars($class) . '" style="margin:10px 0;">'
           . htmlspecialchars($msg)
           . '</div>';
    }
}
// ✅ End of feedback banner block
?>

<?php
    $current_seller_id = current_user_id(); 
    date_default_timezone_set('Europe/London');
    $current_time = date('Y-m-d H:i:s');

    // update ended auctions
    $sql_update_ended = "UPDATE auctions SET current_status = 'ended'
    WHERE end_time < :current_time
    AND current_status IN ('scheduled','running')";

    $stmt_update_ended = $pdo -> prepare($sql_update_ended);
    $stmt_update_ended -> execute([':current_time' => $current_time]);

    //update running auctions
    $sql_update_running = "UPDATE auctions SET current_status = 'running'
    WHERE start_time <= :check_time_start
    AND end_time > :check_time_end
    AND current_status = 'scheduled'";

    $stmt_update_running = $pdo->prepare($sql_update_running);
    $stmt_update_running->execute([':check_time_start' => $current_time,
    ':check_time_end'=>$current_time]);

    // get list of auctions (seller)
    $sql_seller_view = "SELECT 
    a.auction_id,i.item_id,i.title, u.display_name, a.starting_price, a.start_time,a.end_time,a.current_status
    FROM auctions a
    LEFT JOIN items i on a.item_id = i.item_id
    INNER JOIN users u on i.seller_id = u.user_id
    WHERE i.seller_id = :seller_id ";

    $stmt_seller_view = $pdo -> prepare($sql_seller_view);
    $stmt_seller_view -> execute(['seller_id' => $current_seller_id]);
    $auctions = $stmt_seller_view->fetchAll(PDO::FETCH_ASSOC);

?>


<div class="auction-list-container">
    <h2>My Auctions</h2>
    
    <?php if (count($auctions) > 0): ?>
        <table class="table table-striped auction-table">
            <thead>
                <tr>
                    <th>Auction ID</th>
                    <th>Item ID</th>
                    <th>Item Title</th>
                    <th>Seller</th>
                    <th>Starting Price</th>
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

                    <td><?php echo date('Y-m-d H:i', strtotime($auction['start_time'])); ?></td>
                    <td>
                        <strong 
                            style="color: <?php echo (strtotime($auction['end_time']) < time() ? 'red' : 'green'); ?>;">
                            <?php echo date('Y-m-d H:i', strtotime($auction['end_time'])); ?>
                        </strong>
                    </td>
                    <td><?php echo htmlspecialchars($auction['current_status']); ?></td>

                    <td>
                      <a href="../Includes/add_to_watchlist.php?auction_id=<?= (int)$auction['auction_id']; ?>"
                          class="btn btn-outline-primary mt-2">
                          ❤️ Add to Watchlist
                      </a>
                    </td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="alert alert-info">There are no active auctions right now.</p>
    <?php endif; ?>
</div>

</html>