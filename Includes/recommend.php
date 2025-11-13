<?php
require_once __DIR__ . '/db.php';

/**
 * Get recommended auctions for a given user (based on others' similar activity)
 */
function get_recommendations($user_id, $limit = 3) {
    global $pdo;

    // Step 1: Find auctions user has watched or bid on
    $sql_user_items = "
        SELECT DISTINCT auction_id 
        FROM watchlist WHERE user_id = ?
        UNION
        SELECT DISTINCT auction_id 
        FROM bids WHERE bidder_id = ?
    ";
    $stmt = $pdo->prepare($sql_user_items);
    $stmt->execute([$user_id, $user_id]);
    $user_items = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($user_items)) {
        return get_random_recommendations($pdo, $user_id, $limit);
    }

    // Step 2: Find other users who interacted with same auctions
    $in_user_items = implode(',', array_fill(0, count($user_items), '?'));

    $sql_similar_users = "
        SELECT DISTINCT w.user_id
        FROM watchlist w
        WHERE w.auction_id IN ($in_user_items)
          AND w.user_id != ?
    ";
    $stmt = $pdo->prepare($sql_similar_users);
    // [...$user_items, $user_id] = spread user_items + current user at the end
    $stmt->execute(array_merge($user_items, [$user_id]));
    $similar_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($similar_users)) {
        return get_random_recommendations($pdo, $user_id, $limit);
    }

    // Step 3: Recommend items those similar users watched but current user hasn't
    $in_similar    = implode(',', array_fill(0, count($similar_users), '?'));
    $in_user_items = implode(',', array_fill(0, count($user_items), '?'));

    $sql_recommend = "
        SELECT DISTINCT a.auction_id, i.title, u.display_name AS seller, a.starting_price
        FROM watchlist w
        JOIN auctions a ON a.auction_id = w.auction_id
        JOIN items i ON i.item_id = a.item_id
        JOIN users u ON i.seller_id = u.user_id
        WHERE w.user_id IN ($in_similar)
          AND w.auction_id NOT IN ($in_user_items)
          AND u.user_id != ?
        LIMIT ?
    ";

    $stmt = $pdo->prepare($sql_recommend);
    $params = array_merge($similar_users, $user_items, [$user_id, $limit]);
    $stmt->execute($params);
    $recs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($recs)) {
        return get_random_recommendations($pdo, $user_id, $limit);
    }

    // Filter out items already in user’s watchlist
    $recs = array_filter($recs, function ($r) use ($user_items) {
        return !in_array($r['auction_id'], $user_items);
    });

    return array_values($recs); // reindex nicely
}

/**
 * Fallback — random scheduled auctions not owned by user
 */
function get_random_recommendations($pdo, $user_id, $limit) {
    $stmt = $pdo->prepare("
        SELECT a.auction_id, i.title, u.display_name AS seller, a.starting_price
        FROM auctions a
        JOIN items i ON i.item_id = a.item_id
        JOIN users u ON i.seller_id = u.user_id
        WHERE a.current_status = 'scheduled'
          AND u.user_id != ?
        ORDER BY RAND()
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch full auction details for display on recommendation cards
 */
function get_full_auction($auction_id) {
    global $pdo;

    $sql = "
        SELECT a.auction_id, a.starting_price, a.start_time, a.end_time, a.current_status,
               i.title, i.item_id,
               u.display_name AS seller,
               (
                    SELECT MAX(b.amount)
                    FROM bids b
                    WHERE b.auction_id = a.auction_id
               ) AS highest_bid
        FROM auctions a
        JOIN items i ON a.item_id = i.item_id
        JOIN users u ON i.seller_id = u.user_id
        WHERE a.auction_id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$auction_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Convert lightweight recs into full auction details
 */
function build_full_recommendation_list($user_id, $limit = 3) {
    $basic = get_recommendations($user_id, $limit);
    $final = [];

    foreach ($basic as $rec) {
        $full = get_full_auction($rec['auction_id']);
        if ($full) {
            $final[] = $full;
        }
    }

    return $final;
}
?>



