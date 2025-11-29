<?php
// ---------- DEBUG SETTINGS ----------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ---------- INCLUDES ----------
require_once __DIR__ . '/../includes/db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';   // header once at the top
require_once __DIR__ . '/../includes/proxy.php';    // keep notify.php for other pages if you want
require_once __DIR__ . '/../includes/notify.php';   // but we won’t rely on it here

// ---------- LOCAL HELPER: direct notification insert for bids ----------
function add_bid_notification_direct(PDO $pdo, int $user_id, int $auction_id, string $content): void
{
    // matches table: notifications(user_id, auction_id, type, content, is_read, created_at)
    $sql = "
        INSERT INTO notifications (user_id, auction_id, type, content, is_read)
        VALUES (:user_id, :auction_id, 'bid', :content, 0)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id'   => $user_id,
        ':auction_id'=> $auction_id,
        ':content'   => $content,
    ]);
}

// ---------- INITIAL SETUP ----------
$price_errors        = [];
$current_bidder_id   = current_user_id();
$proxy_limit         = null;
$increment           = null;
$current_auction_id  = $_SESSION['auction_id_to_bid'] ?? null;

if (!$current_bidder_id) {
    echo "<p>Please log in to bid.</p>";
    exit;
}

if (!$current_auction_id) {
    echo "<p>No auction selected to bid on.</p>";
    exit;
}

// ---------- FETCH AUCTION + ITEM ----------
$auction_item_name = 'N/A';
$starting_price    = 0.0;
$reserve_price     = 0.0;

try {
    $sql_get_item = "
        SELECT a.starting_price, i.title
        FROM auctions a
        JOIN items i ON a.item_id = i.item_id
        WHERE a.auction_id = ?
    ";
    $stmt_get_item = $pdo->prepare($sql_get_item);
    $stmt_get_item->execute([$current_auction_id]);
    $result = $stmt_get_item->fetch(PDO::FETCH_ASSOC);

    if ($result !== false) {
        $reserve_price     = (float) $result['starting_price'];
        $starting_price    = $reserve_price;
        $auction_item_name = htmlspecialchars($result['title']);
    }
} catch (PDOException $e) {
    echo "<p>Error loading auction details: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ---------- MINIMUM REQUIRED BID ----------
$minimum_required_bid = $starting_price;

try {
    $sql_high_bid = "SELECT MAX(amount) FROM bids WHERE auction_id = ?";
    $stmt_high_bid = $pdo->prepare($sql_high_bid);
    $stmt_high_bid->execute([$current_auction_id]);
    $high_bid_result = $stmt_high_bid->fetchColumn();

    if ($high_bid_result !== false && (float)$high_bid_result > $starting_price) {
        $minimum_required_bid = (float)$high_bid_result + 1.00;
    }
} catch (PDOException $e) {
    echo "<p>Error loading current highest bid: " . htmlspecialchars($e->getMessage()) . "</p>";
    $minimum_required_bid = $starting_price;
}

// ---------- HANDLE POST (PLACE BID) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bid'])) {

    date_default_timezone_set('Europe/London');
    $bid_time = date('Y-m-d H:i:s');

    $amount = trim($_POST['amount'] ?? '');

    // --- basic validation ---
    if (!is_numeric($amount) || $amount <= 0) {
        $price_errors[] = "Bidding amount needs to be a valid number greater than 0.";
    }

    if ($reserve_price > 0 && (float)$amount < $reserve_price) {
        $price_errors[] = "Your bid amount must be at least the reserve price $"
                          . number_format($reserve_price, 2);
    }

    if ((float)$amount < $minimum_required_bid) {
        $price_errors[] = "Your bid must be higher than current bid. Minimum required bid is $"
                          . number_format($minimum_required_bid, 2);
    }

    $is_proxy = filter_input(INPUT_POST, 'is_proxy', FILTER_VALIDATE_INT);

    if (!in_array($is_proxy, [0, 1], true)) {
        $price_errors[] = "Please select a valid bid type.";
    }

    if ($is_proxy === 1) {
        $proxy_limit = trim($_POST['proxy_limit'] ?? '');
        $increment   = trim($_POST['increment'] ?? '');

        if (!is_numeric($proxy_limit) || $proxy_limit <= 0) {
            $price_errors[] = "Proxy limit (maximum bid) needs to be a valid number greater than 0.";
        }

        if (!is_numeric($increment) || $increment <= 0) {
            $price_errors[] = "Proxy increment needs to be a valid number greater than 0.";
        }

        if (empty($price_errors) && (float)$amount > (float)$proxy_limit) {
            $price_errors[] = "The initial bid amount cannot be greater than proxy limit.";
        }

        if (empty($price_errors)) {
            $proxy_limit = (float)$proxy_limit;
            $increment   = (float)$increment;
        }
    }

    // ---------- IF NO VALIDATION ERRORS: INSERT BID + NOTIFICATIONS ----------
    if (empty($price_errors)) {

        // 1) Insert the bid
        $sql_insert_bid = "INSERT INTO bids
            (auction_id, bidder_id, amount, bid_time, is_proxy, proxy_limit, increment)
            VALUES
            (:auction_id, :bidder_id, :amount, :bid_time, :is_proxy, :proxy_limit, :increment)";

        $stmt_insert_bid = $pdo->prepare($sql_insert_bid);

        $bid_params = [
            'auction_id'  => $current_auction_id,
            'bidder_id'   => $current_bidder_id,
            'amount'      => (float)$amount,
            'bid_time'    => $bid_time,
            'is_proxy'    => $is_proxy,
            'proxy_limit' => $proxy_limit,
            'increment'   => $increment
        ];

        try {
            // 1️⃣ Insert the new bid
            $stmt_insert_bid->execute($bid_params);

  // 2️⃣ Run proxy logic ONCE (but don't let it kill the rest of the flow)
$new_bid_amount  = (float)$amount;
$new_proxy_limit = ($is_proxy === 1) ? (float)$proxy_limit : null;
$new_increment   = ($is_proxy === 1) ? (float)$increment   : null;

try {
    process_proxy_bids(
        $pdo,
        $current_auction_id,
        $current_bidder_id,
        $new_bid_amount,
        $new_proxy_limit,
        $new_increment
    );
} catch (Exception $e_proxy) {
    // Just log to PHP error log; do not spam notifications table
    error_log('process_proxy_bids error: ' . $e_proxy->getMessage());
}



            // 3️⃣ Get seller_id
            $stmt_seller = $pdo->prepare("
                SELECT seller_id
                FROM auctions
                WHERE auction_id = ?
            ");
            $stmt_seller->execute([$current_auction_id]);
            $seller_id = $stmt_seller->fetchColumn();

            // 4️⃣ Notify seller (new bid on your auction)
            if ($seller_id && $seller_id != $current_bidder_id) {
                add_bid_notification_direct(
                    $pdo,
                    (int)$seller_id,
                    (int)$current_auction_id,
                    'A new bid has been placed on your auction.'
                );
            }

            // 5️⃣ Find previous top bidder AFTER this new bid
            $stmt_prev = $pdo->prepare("
                SELECT bidder_id
                FROM bids
                WHERE auction_id = ?
                ORDER BY amount DESC, bid_time DESC
                LIMIT 1 OFFSET 1
            ");
            $stmt_prev->execute([$current_auction_id]);
            $prev_bidder = $stmt_prev->fetchColumn();

            if ($prev_bidder && $prev_bidder != $current_bidder_id) {
                add_bid_notification_direct(
                    $pdo,
                    (int)$prev_bidder,
                    (int)$current_auction_id,
                    'You have been outbid by another user.'
                );
            }

            // 6️⃣ Notify current bidder too
            add_bid_notification_direct(
                $pdo,
                (int)$current_bidder_id,
                (int)$current_auction_id,
                'Your bid has been placed successfully.'
            );

                    } catch (Exception $e) {
            // Log serious errors to the PHP error log
            error_log('Bid/notification error in create_bid.php: ' . $e->getMessage());

            // Optional: show a generic message to the user
            echo "<p style='color:red;'>Bid/notification error. Please try again.</p>";
        }

// 7️⃣ Redirect back to bid history
        header("Location: /auction-site/Pages/bid_history.php?auction_id=" . $current_auction_id);
        exit;
    }
}
?>

<section class="wrapper-main">

    <?php if (!empty($price_errors)): ?>
        <div class="error">
            <?php foreach ($price_errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="create_bid.php" method="post">
        <p class="item_id">Auction item name: <?= $auction_item_name; ?></p>
        <p class="item_id">Starting price: $<?= number_format($starting_price, 2); ?></p>
        <p class="item_id">Minimum required bid: $<?= number_format($minimum_required_bid, 2); ?></p>

        <label for="amount">Amount</label><br>
        <input type="text" id="amount" name="amount" placeholder="Enter your bidding amount">
        <br><br>

        <label for="is_proxy">Bid Type</label><br>
        <select id="is_proxy" name="is_proxy" onchange="toggleProxyFields()">
            <option value="0" selected>Standard Bid (One-time)</option>
            <option value="1">Proxy Bid (Set maximum)</option>
        </select>
        <br><br>

        <div id="proxy_fields" style="display:none;">
            <h4>Proxy Bid Settings</h4>
            <label for="proxy_limit">Proxy limit (maximum bid)</label>
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

<script>
function toggleProxyFields() {
    const select = document.getElementById('is_proxy');
    const proxyFields = document.getElementById('proxy_fields');

    if (select.value === '1') {
        proxyFields.style.display = 'block';
        document.getElementById('proxy_limit').required = true;
        document.getElementById('increment').required = true;
    } else {
        proxyFields.style.display = 'none';
        document.getElementById('proxy_limit').required = false;
        document.getElementById('increment').required = false;
    }
}
document.addEventListener('DOMContentLoaded', toggleProxyFields);
</script>
</body>
</html>
