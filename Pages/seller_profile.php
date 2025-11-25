<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

ini_set('display_errors',1);
error_reporting(E_ALL);

// Require login for rating actions but allow anonymous view

$seller_id = $_GET['seller_id'] ?? null;
if (!$seller_id || !ctype_digit($seller_id)) {
    die("<h3>Invalid seller ID</h3>");
}

// handle rating submission
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    // basic CSRF check if available
    if (!function_exists('csrf_check') || csrf_check($_POST['csrf_token'] ?? '')) {
        $rater_id = current_user_id();
        if (!$rater_id) {
            $flash = ['type'=>'danger','text'=>'You must be logged in to rate the seller.'];
        } else if ((string)$rater_id === (string)$seller_id) {
            $flash = ['type'=>'danger','text'=>'You cannot rate yourself.'];
        } else {
            $rating = (int)$_POST['rating'];
            if ($rating < 1 || $rating > 5) {
                $flash = ['type'=>'danger','text'=>'Invalid rating value.'];
            } else {
                try {
                    // Begin transaction
                    $pdo->beginTransaction();

                    // fetch current seller aggregates
                    $stmt = $pdo->prepare("SELECT seller_rating_avg, seller_rating_count FROM users WHERE user_id = :id FOR UPDATE");
                    $stmt->execute(['id' => $seller_id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        throw new Exception('Seller not found.');
                    }

                    $cur_avg = (float)($row['seller_rating_avg'] ?? 0.0);
                    $cur_count = (int)($row['seller_rating_count'] ?? 0);

                    $new_count = $cur_count + 1;
                    $new_avg = ($cur_avg * $cur_count + $rating) / $new_count;
                    // format to 2 decimals
                    $new_avg = round($new_avg, 2);

                    // update seller aggregates
                    $upd = $pdo->prepare("UPDATE users SET seller_rating_avg = :avg, seller_rating_count = :count WHERE user_id = :id");
                    $upd->execute(['avg' => $new_avg, 'count' => $new_count, 'id' => $seller_id]);

                    // increment rater's counter
                    $upd2 = $pdo->prepare("UPDATE users SET total_ratings_given = COALESCE(total_ratings_given,0) + 1 WHERE user_id = :rater");
                    $upd2->execute(['rater' => $rater_id]);

                    $pdo->commit();

                    $flash = ['type'=>'success','text'=>'Thank you — your rating has been recorded.'];
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $flash = ['type'=>'danger','text'=>'Could not save rating: ' . h($e->getMessage())];
                }
            }
        }
    } else {
        $flash = ['type'=>'danger','text'=>'Invalid form submission (CSRF).'];
    }
}

// Fetch seller data (including rating fields and alt_email)
$stmt = $pdo->prepare("SELECT user_id, display_name, email, first_name, last_name, seller_rating_avg, seller_rating_count, alt_email FROM users WHERE user_id = :id LIMIT 1");
$stmt->execute(['id' => $seller_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    die("<h3>Seller not found.</h3>");
}

// format rating display
$rating_text = ($seller['seller_rating_count'] > 0)
    ? number_format($seller['seller_rating_avg'], 2) . "⭐ (" . $seller['seller_rating_count'] . " ratings)"
    : "No ratings yet";

// prepare CSRF token for form
$csrf = function_exists('csrf_token') ? csrf_token() : '';

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Seller Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-card { max-width: 700px; margin: auto; }
        .star-rating { direction: rtl; font-size: 1.8rem; }
        .star-rating input { display: none; }
        .star-rating label { color: #ddd; cursor: pointer; }
        .star-rating input:checked ~ label { color: #ffc107; }
        .star-rating label:hover, .star-rating label:hover ~ label { color: #ffdb70; }
    </style>
</head>

<body class="p-4">
<div class="container">

    <h2>Seller Profile</h2>

    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['text']) ?></div>
    <?php endif; ?>

    <div class="card profile-card shadow-sm mt-4">
        <div class="card-body">

            <h4 class="mb-3"><?= h($seller['display_name']) ?></h4>

            <p><strong>Rating:</strong> <?= h($rating_text) ?></p>

            <?php
            // Determine which address to use: alt_email preferred
            $contact_email = $seller['alt_email'] ?: $seller['email'];
            ?>

            <p><strong>Contact Seller:</strong><br><em>Email hidden for privacy</em></p>

            <a href="mailto:<?= h($contact_email) ?>" class="btn btn-primary mt-2">📩 Contact Seller</a>

            <hr>

            <p><strong>Name:</strong><br><?= h(trim(($seller['first_name'] ?? '') . ' ' . ($seller['last_name'] ?? ''))) ?></p>

            <?php if (current_user_id() && (string)current_user_id() !== (string)$seller['user_id']): ?>

                <hr>
                <h5>Rate this seller</h5>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5"><label for="star5">★</label>
                        <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                        <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                        <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                        <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-success">Submit Rating</button>
                    </div>
                </form>

            <?php elseif (!current_user_id()): ?>
                <p class="text-muted">Log in to rate this seller.</p>
            <?php endif; ?>

        </div>
    </div>

    <div class="mt-3">
        <a href="buyer_auctions.php" class="btn btn-secondary">Back to Auctions</a>
    </div>

</div>
</body>
</html>