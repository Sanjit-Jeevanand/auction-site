<?php
function build_full_recommendation_list($user_id, $limit = 5) {
    global $pdo;

    // 1️⃣ Auctions this user touched
    $stmt = $pdo->prepare("
        SELECT DISTINCT auction_id FROM watchlist WHERE user_id = ?
        UNION
        SELECT DISTINCT auction_id FROM bids WHERE bidder_id = ?
    ");
    $stmt->execute([$user_id, $user_id]);
    $user_items = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($user_items)) {
        return get_random_recommendations($limit);
    }

    // 2️⃣ Find similar users
    $in = implode(',', array_fill(0, count($user_items), '?'));
    $sql = "SELECT DISTINCT user_id FROM watchlist WHERE auction_id IN ($in) AND user_id != ?";
    $params = array_merge($user_items, [$user_id]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $similar_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($similar_users)) {
        return get_random_recommendations($limit);
    }

    // 3️⃣ Auctions those users watched
    $in_users = implode(',', array_fill(0, count($similar_users), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT auction_id FROM watchlist WHERE user_id IN ($in_users)");
    $stmt->execute($similar_users);
    $similar_items = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 4️⃣ Remove already-known auctions
    $recommended_ids = array_diff($similar_items, $user_items);

    if (empty($recommended_ids)) {
        return get_random_recommendations($limit);
    }

    // 5️⃣ Fetch auctions details
    $in_final = implode(',', array_fill(0, count($recommended_ids), '?'));
    $sql = "
        SELECT a.auction_id, i.title, u.display_name AS seller,
               a.starting_price,
               (SELECT MAX(amount) FROM bids WHERE auction_id = a.auction_id) AS highest_bid,
               a.end_time
        FROM auctions a
        JOIN items i ON a.item_id = i.item_id
        JOIN users u ON i.seller_id = u.user_id
        WHERE a.auction_id IN ($in_final)
        LIMIT $limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($recommended_ids));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function get_random_recommendations($limit = 5) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT a.auction_id, i.title, u.display_name AS seller,
               a.starting_price,
               (SELECT MAX(amount) FROM bids WHERE auction_id = a.auction_id) AS highest_bid,
               a.end_time
        FROM auctions a
        JOIN items i ON a.item_id = i.item_id
        JOIN users u ON i.seller_id = u.user_id
        ORDER BY RAND()
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
