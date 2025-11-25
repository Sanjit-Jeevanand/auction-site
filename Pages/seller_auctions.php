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

    // filter auctions by current_status, default showing all auctions ended and running

    $category_filter = trim($_GET['auction_filter'] ?? 'every_auction');
    $where_clause = '';

    switch($category_filter) {
        case 'running':
            $where_clause = "a.current_status = 'running'";
            break;
        case 'ended':
            $where_clause = "a.current_status = 'ended'";
            break;
        case 'scheduled':
            $where_clause = "a.current_status = 'scheduled'";
            break;
        case 'every_auction':
            $where_clause = "a.current_status IN ('running','ended','scheduled')";
            break;
    }

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
    a.auction_id,i.item_id,i.title, u.display_name, a.starting_price, a.reserve_price, a.start_time,a.end_time,a.current_status
    FROM auctions a
    LEFT JOIN items i on a.item_id = i.item_id
    INNER JOIN users u on i.seller_id = u.user_id
    WHERE i.seller_id = :seller_id
    " . (empty($where_clause) ? "" : "AND ") . $where_clause . "
    ORDER BY a.end_time DESC";

    $stmt_seller_view = $pdo -> prepare($sql_seller_view);
    $stmt_seller_view -> execute(['seller_id' => $current_seller_id]);
    $auctions = $stmt_seller_view->fetchAll(PDO::FETCH_ASSOC);

?>

<head>
<style>
    .auction-list-container{
        max-width: 1000px;
        margin-left: 100px;
    }
</style>

</head>


<div class="auction-list-container">
    <h2>My Auctions</h2>

    <form action="seller_auctions.php" method="get">

        <label for="auction_filter">Filter Auctions by status:</label>
        <br>
            <select name="auction_filter" id = "auction_filter" onchange="this.form.submit()">
                <option value="every_auction">Scheduled&Running&Ended</option>
                <option value ="scheduled" <?php if ($category_filter == 'scheduled') echo 'selected'; ?>>Scheduled </option>
                <option value ="running" <?php if ($category_filter == 'running') echo 'selected'; ?>>Running</option>
                <option value="ended"<?php if ($category_filter == 'ended') echo 'selected'; ?>>Ended</option>
            </select>
        <br><br>
    </form>
    
    <?php if (count($auctions) > 0): ?>
        <table class="table table-striped auction-table">
            <thead>
                <tr>
                    <th>Auction ID</th>
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
                    <td><?php echo htmlspecialchars($auction['title']); ?></td>
                    <td><?php echo htmlspecialchars($auction['display_name']); ?></td>
                    <td><?php echo number_format($auction['starting_price'], 2); ?></td>
                    <td><?php echo number_format($auction['reserve_price'], 2); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($auction['start_time'])); ?></td>
                    <td>
                        <strong 
                            style="color: <?php echo (strtotime($auction['end_time']) < time() ? 'red' : 'green'); ?>;">
                            <?php echo date('Y-m-d H:i', strtotime($auction['end_time'])); ?>
                        </strong>
                    </td>
                    <td><?php echo htmlspecialchars($auction['current_status']); ?></td>

                    <td>
                        <form action="set_seller_history_session.php" method="POST" style="display:inline-block;">
                            <input type="hidden" name="auction_id_to_bid" value="<?php echo $auction['auction_id']; ?>">
                            <button type="submit" class="btn btn-outline-dark">Bid history</button>
                        </form> 
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