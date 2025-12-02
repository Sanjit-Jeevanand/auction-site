<html>

<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/recommend.php';
require_once __DIR__ . '/../includes/notify.php';   // 👈 NEW

// 👇 Mark notification as read when coming from notifications page
if (isset($_GET['mark_read']) && ctype_digit($_GET['mark_read'])) {
    mark_notification_read((int)$_GET['mark_read']);
}    
?>
<?php
// ✅ Feedback banner for watchlist actions
if (isset($_GET['watch'])) {
    $msg   = '';
    $class = 'info';

    switch ($_GET['watch']) {
        case 'success':
            $msg   = '✅ Added to your watchlist!';
            $class = 'success';
            break;
        case 'exists':
            $msg   = 'ℹ️ This auction is already in your watchlist.';
            $class = 'warning';
            break;
        case 'removed':
            $msg   = '❌ Removed from your watchlist.';
            $class = 'secondary';
            break;
        case 'login':
            $msg   = 'Please log in to manage your watchlist.';
            $class = 'warning';
            break;
        case 'invalid':
            $msg   = 'Invalid request.';
            $class = 'danger';
            break;
        case 'missing':
            $msg   = 'That auction no longer exists.';
            $class = 'danger';
            break;
    }

    if ($msg) {
        echo '<div class="alert alert-' . htmlspecialchars($class) . '" style="margin:10px 0;">'
           . htmlspecialchars($msg)
           . '</div>';
    }
}
?>


<head>
<style>
.auction-list-container {
    max-width: 1100px; 
    margin-left: auto;
    margin-right: auto;
}
</style>

</head>



<?php

    $bidder_id = current_user_id(); 
    date_default_timezone_set('Europe/London');
    $current_time = date('Y-m-d H:i:s');

    $category_filter = trim($_GET['auction_filter'] ?? 'every_auction');
    $filter_category = trim($_GET['filter_category'] ?? 'every_category');
    $search_q_raw = trim($_GET['q'] ?? '');
    $search_q = $search_q_raw !== '' ? $search_q_raw : '';

    // --- Record search for logged-in buyers/both users ---
    // If the current user is signed in and performed a text search, store it on their profile.
    if ($search_q !== '' && !empty($bidder_id)) {
        // Only record for users whose role is buyer or both.
        $user_role = $_SESSION['role'] ?? null;
        if ($user_role === 'buyer' || $user_role === 'both') {
            try {
                $stmt_rec = $pdo->prepare(
                    "UPDATE users SET last_search = :q, last_search_at = NOW(), total_searches = COALESCE(total_searches,0) + 1 WHERE user_id = :uid AND role IN ('buyer','both')"
                );
                $stmt_rec->execute(['q' => $search_q, 'uid' => $bidder_id]);
            } catch (Exception $e) {
                // fail silently — search should not break auction listing
                error_log('Search history save failed: ' . $e->getMessage());
            }
        }
    }

    // -----------------------------
    // Filter auctions by status
    // -----------------------------
    $conditions = [];

    switch ($category_filter) {
        case 'running':
            $conditions[] = "a.current_status = 'running'";
            break;
        case 'ended':
            $conditions[] = "a.current_status = 'ended'";
            break;
        default:
            $conditions[] = "a.current_status IN ('running','ended')";
            break;
    }

    // -----------------------------
    // Filter auctions by category
    // -----------------------------
    if ($filter_category !== 'every_category') {
        try {
            $stmt_cat = $pdo->prepare("SELECT category_id FROM categories WHERE name = ?");
            $stmt_cat->execute([$filter_category]);
            $cat_id = $stmt_cat->fetchColumn();

            if ($cat_id) {
                // parent_category_id or category_id depending on your schema
                $conditions[] = "c.parent_category_id = " . (int)$cat_id;
            }
        } catch (Exception $e) {
            error_log('Category lookup failed: ' . $e->getMessage());
        }
    }

    // -----------------------------
    // Build final WHERE clause
    // -----------------------------
    $final_where_sql = '';
    if (!empty($conditions)) {
        $final_where_sql = 'WHERE ' . implode(' AND ', $conditions);
    }


    // update ended auctions
    $sql_update_ended = "UPDATE auctions SET current_status = 'ended'
    WHERE end_time < :current_time
    AND current_status IN ('scheduled','running')";

    $stmt_update_ended = $pdo->prepare($sql_update_ended);
    $stmt_update_ended->execute(['current_time' => $current_time]);

    // get list of auctions (buyer view) and include description so we can fuzzy-filter in PHP
    $sql_buyer_auctions = "SELECT 
    a.auction_id, i.item_id, i.title, i.description, u.user_id AS seller_id, u.display_name, a.starting_price, 
    (
        SELECT MAX(b.amount)
        FROM bids b
        WHERE b.auction_id = a.auction_id
    ) AS current_high_bid, a.start_time, a.end_time, a.current_status
    FROM auctions a
    LEFT JOIN items i ON a.item_id = i.item_id
    INNER JOIN users u ON i.seller_id = u.user_id
    INNER JOIN categories c ON i.category_id = c.category_id
    $final_where_sql
    ORDER BY a.end_time DESC";

    $stmt_buyer_auctions = $pdo->prepare($sql_buyer_auctions);
    $stmt_buyer_auctions->execute();
    $auctions = $stmt_buyer_auctions->fetchAll(PDO::FETCH_ASSOC);

    // If a search query exists, perform a safe PHP-side fuzzy filter using character-overlap ratio.
    if ($search_q !== '') {
        // helper: compute character-overlap ratio between needle and haystack
        $compute_overlap_ratio = function(string $needle, string $haystack) {
            // normalize: lowercase, keep letters and digits
            $needle = mb_strtolower($needle, 'UTF-8');
            $haystack = mb_strtolower($haystack, 'UTF-8');
            $needle = preg_replace('/[^a-z0-9]/u', '', $needle);
            $haystack = preg_replace('/[^a-z0-9]/u', '', $haystack);
            if ($needle === '') return 0.0;

            // frequency counts for needle and haystack
            $nlen = mb_strlen($needle, 'UTF-8');
            $needle_counts = [];
            for ($i = 0; $i < $nlen; $i++) {
                $ch = mb_substr($needle, $i, 1, 'UTF-8');
                if (!isset($needle_counts[$ch])) $needle_counts[$ch] = 0;
                $needle_counts[$ch]++;
            }
            $hay_counts = [];
            $hlen = mb_strlen($haystack, 'UTF-8');
            for ($i = 0; $i < $hlen; $i++) {
                $ch = mb_substr($haystack, $i, 1, 'UTF-8');
                if (!isset($hay_counts[$ch])) $hay_counts[$ch] = 0;
                $hay_counts[$ch]++;
            }

            $matches = 0;
            foreach ($needle_counts as $ch => $cnt) {
                if (isset($hay_counts[$ch])) {
                    $matches += min($cnt, $hay_counts[$ch]);
                }
            }

            return $matches / max(1, mb_strlen($needle, 'UTF-8'));
        };

        $filtered = [];
        $threshold = 0.5; // require at least 50% of query characters to match
        foreach ($auctions as $a) {
            $hay = ($a['title'] ?? '') . ' ' . ($a['description'] ?? '');
            $ratio = $compute_overlap_ratio($search_q, $hay);
            if ($ratio >= $threshold) {
                $filtered[] = $a;
            }
        }
        // replace auctions with filtered results
        $auctions = $filtered;
    }

    // Build personalised recommendations for this user (E6 feature)
    $recommended_auctions = get_recommendations($bidder_id, 3);

?>

<div class="auction-list-container">
    <h2>Active Auctions</h2>

    <form action="buyer_auctions.php" method="get" class="mb-3">
        <div class="input-group" style="max-width: 400px;">
            <input 
                type="text" 
                name="q" 
                class="form-control" 
                placeholder="Search items..."
            >
            <button class="btn btn-primary" type="submit">
                Search
            </button>
        </div>
    </form>

    <form action="buyer_auctions.php" method="get">

        <label for="auction_filter">Filter Auctions by status:</label>
        <br>
            <select name="auction_filter" id = "auction_filter" onchange="this.form.submit()">
                <option value="every_auction">Running&Ended</option>
                <option value ="running" <?php if ($category_filter == 'running') echo 'selected'; ?>>Running</option>
                <option value="ended"<?php if ($category_filter == 'ended') echo 'selected'; ?>>Ended</option>
            </select>
        <br><br>
    </form>

    <form action="buyer_auctions.php" method="get">

        <label for="filter_category">Filter Auctions by category:</label>
        <br>
            <select name="filter_category" id = "filter_category" onchange="this.form.submit()">
                <option value="every_category">All categories</option>
                <option value ="Electronics & Technology" <?php if ($filter_category == 'Electronics & Technology') echo 'selected'; ?>>Electronics</option>
                <option value="Fashion & Apparel"<?php if ($filter_category == 'Fashion & Apparel') echo 'selected'; ?>>Fashion</option>
            </select>
        <br><br>
    </form>

    <?php if (count($auctions) > 0): ?>
        <table class="table table-striped auction-table mx-auto d-block">
            <thead>
                <tr>
                    <th>Auction ID</th>
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
                <?php
          // 🔍 Check if this auction is already in the current bidder's watchlist
                    $stmt_w = $pdo->prepare("SELECT 1 FROM watchlist WHERE user_id = ? AND auction_id = ?");
                    $stmt_w->execute([$bidder_id, $auction['auction_id']]);
                    $is_in_watchlist = $stmt_w->fetchColumn();
                   ?>
                <tr>

                    <td><?php echo htmlspecialchars($auction['auction_id']); ?></td>
                    <td><?php echo htmlspecialchars($auction['title']); ?></td>
                    <td>
                        <a href="seller_profile.php?seller_id=<?php echo urlencode($auction['seller_id']); ?>">
                            <?php echo htmlspecialchars($auction['display_name']); ?>
                        </a>
                    </td>
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
                    <td>
    <div class="d-flex align-items-start gap-2">                    
    <!-- Place Bid button only when auction is running -->
     <?php if ($auction['current_status'] === 'running'):  ?>
        <form action="set_bid_session.php" method="POST" style="display:inline-block;">
            <input type="hidden" name="auction_id_to_bid" value="<?php echo $auction['auction_id']; ?>">
            <button type="submit" class="btn btn-success">Place Bid</button>
        </form>
     <?php else: ?>
        <button type="button" class="btn btn-danger" disabled>Bidding closed</button>
     <?php endif; ?>

        <!-- Check bid history button -->
    <form action="set_history_session.php" method="POST" style="display:inline-block;">
        <input type="hidden" name="auction_id_to_bid" value="<?php echo $auction['auction_id']; ?>">
        <button type="submit" class="btn btn-outline-dark">Bid history</button>
    </form>
     </div>


    <!-- ❤️ Watchlist button / label -->
    <?php if (!$is_in_watchlist): ?>
        <!-- Uses the same add_to_watchlist.php include as before -->
        <a href="../Includes/add_to_watchlist.php?auction_id=<?php echo $auction['auction_id']; ?>"
           class="btn btn-outline-danger"
           style="margin-left:6px;">
            ❤️ Add to Watchlist
        </a>
    <?php else: ?>
    <a href="../Includes/remove_from_watchlist.php?auction_id=<?php echo $auction['auction_id']; ?>"
       class="btn btn-outline-secondary"
       style="margin-left:6px;">
        Remove From Watchlist
    </a>
<?php endif; ?>

</td>

</tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="alert alert-info">There are no active auctions right now.</p>
    <?php endif; ?>
    <?php if (!empty($recommended_auctions)): ?>
    <hr>
    <h3>Recommended for you</h3>

    <table class="table table-bordered mt-3">
        <thead>
            <tr>
                <th>Auction ID</th>
                <th>Item Title</th>
                <th>Seller</th>
                <th>Starting Price</th>
                <th>Highest Bid</th>
                <th>Ends</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recommended_auctions as $rec): ?>
            <tr>
                <td><?php echo htmlspecialchars($rec['auction_id']); ?></td>
                <td><?php echo htmlspecialchars($rec['title']); ?></td>
                <td><?php echo htmlspecialchars($rec['seller']); ?></td>
                <td>$<?php echo number_format($rec['starting_price'], 2); ?></td>
                <td>
                    <?php 
                    echo $rec['highest_bid'] 
                        ? '$' . number_format($rec['highest_bid'], 2) 
                        : 'N/A';
                    ?>
                </td>
                <td><?php echo date('Y-m-d H:i', strtotime($rec['end_time'])); ?></td>
                <td>
                    <form action="set_bid_session.php" method="POST" style="display:inline-block;">
                        <input type="hidden" name="auction_id_to_bid" value="<?php echo $rec['auction_id']; ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Place Bid</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</div>

</html>
