<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/recommend.php';

// ✅ Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Check login
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Please log in to view your watchlist.</div></div>";
    require_once __DIR__ . '/../Includes/footer.php';
    exit;
}

// ✅ Debug user id
// echo "<p>(Debug: user_id = {$user_id})</p>"; // optional

// ✅ Fetch watchlist for logged-in user
$sql = "
SELECT 
    a.auction_id, 
    i.title AS item_title, 
    seller.display_name AS seller, 
    a.starting_price, 
    a.reserve_price, 
    a.end_time, 
    a.current_status
FROM watchlist AS w
JOIN auctions AS a ON w.auction_id = a.auction_id
JOIN items AS i ON a.item_id = i.item_id
JOIN users AS seller ON i.seller_id = seller.user_id
WHERE w.user_id = :user_id
ORDER BY a.auction_id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user_id]);
$watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- 🧾 Watchlist Section -->
<div class="container mt-4">
    <h2>My Watchlist</h2>
        <?php
if (isset($_GET['watch'])) {
    $msg = '';
    $class = 'info';

    switch ($_GET['watch']) {
        case 'success':
            $msg = '✅ Added to your watchlist.';
            $class = 'success';
            break;
        case 'exists':
            $msg = 'ℹ️ This auction is already in your watchlist.';
            $class = 'warning';
            break;
        case 'removed':
            $msg = '❌ Removed from your watchlist.';
            $class = 'secondary';
            break;
        case 'login':
            $msg = 'Please log in to manage your watchlist.';
            $class = 'warning';
            break;
        case 'invalid':
            $msg = 'Invalid request.';
            $class = 'danger';
            break;
        case 'missing':
            $msg = 'That auction no longer exists or is not in your watchlist.';
            $class = 'danger';
            break;
    }

    if ($msg) {
        echo '<div class="alert alert-' . htmlspecialchars($class) . ' mt-3">'
           . htmlspecialchars($msg)
           . '</div>';
    }
}
?>


    <?php if (!empty($watchlist)): ?>
        <table class="table table-striped mt-3">
            <thead>
                <tr>
                    <th>Auction ID</th>
                    <th>Item Title</th>
                    <th>Seller</th>
                    <th>Starting Price</th>
                    <th>Reserve Price</th>
                    <th>Ends On</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($watchlist as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['auction_id']); ?></td>
                    <td><?= htmlspecialchars($row['item_title']); ?></td>
                    <td><?= htmlspecialchars($row['seller']); ?></td>
                    <td>$<?= number_format((float)$row['starting_price'], 2); ?></td>
                    <td>$<?= number_format((float)$row['reserve_price'], 2); ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['end_time']))); ?></td>
                    <td><?= htmlspecialchars($row['current_status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info mt-3">You have no items in your watchlist yet.</div>
    <?php endif; ?>
</div>

<?php
// 🧠 Collaborative Recommendation Section
if (!empty($watchlist)) {
    $recommendations = get_recommendations($user_id, 3);
} else {
    $recommendations = [];
}



?>

<!-- 🎯 Recommendations Section -->
<?php if (!empty($recommendations)): ?>
<div class="container mt-5">
    <h4 class="mb-3" style="font-weight: 600;">Recommended for You</h4>
    <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th scope="col">Item Title</th>
                <th scope="col">Seller</th>
                <th scope="col">Starting Price</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recommendations as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['title']); ?></td>
                <td><?= htmlspecialchars($r['seller']); ?></td>
                <td>$<?= number_format((float)$r['starting_price'], 2); ?></td>
                <td>
                    <a href="seller_auctions.php?id=<?= htmlspecialchars($r['auction_id']); ?>" 
                       class="btn btn-sm btn-outline-primary">
                       View Auction
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="container mt-5 text-center">
    <div class="alert alert-secondary" style="max-width: 600px; margin: 0 auto;">
        No recommendations yet — try adding or bidding on more items!
    </div>
</div>
<?php endif; ?>


<?php require_once __DIR__ . '/../Includes/footer.php'; ?>
