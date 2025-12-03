<?php

/* ==============================
   COLLABORATIVE FILTERING SYSTEM
   Fully working version
   ============================== */

function get_recommendations($user_id, $limit = 5) {
    global $pdo;

    // If not logged in, deterministic fallback
    if (!$user_id) {
        return get_random_recommendations($limit);
    }

    /* -----------------------------
       STEP 1 — Auctions THIS user interacted with
       ----------------------------- */
    $stmt = $pdo->prepare("
        SELECT DISTINCT auction_id
        FROM watchlist WHERE user_id = ?

        UNION

        SELECT DISTINCT auction_id
        FROM bids WHERE bidder_id = ?
    ");
    $stmt->execute([$user_id, $user_id]);
    $user_items = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Cold start
    if (empty($user_items)) {
        return get_random_recommendations($limit);
    }

    /* -----------------------------
       STEP 2 — Find similar users
       ----------------------------- */
    $in = implode(',', array_fill(0, count($user_items), '?'));

    $sql = "
        SELECT DISTINCT user_id
        FROM watchlist
        WHERE auction_id IN ($in)
        AND user_id != ?

        UNION

        SELECT DISTINCT bidder_id
        FROM bids
        WHERE auction_id IN ($in)
        AND bidder_id != ?

        UNION

        SELECT DISTINCT b2.bidder_id
        FROM bids b1
        JOIN bids b2
          ON b1.auction_id = b2.auction_id
        WHERE b1.bidder_id = ?
          AND b2.bidder_id != ?
    ";

    $params = array_merge(
        $user_items, [$user_id],
        $user_items, [$user_id],
        [$user_id, $user_id]
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $similar_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // If nobody similar, fallback
    if (empty($similar_users)) {
        return get_random_recommendations($limit);
    }

    $similar_users = array_unique(array_map('intval', $similar_users));

    /* -----------------------------
       STEP 3 — What similar users interact with
       ----------------------------- */
    $in_users = implode(',', array_fill(0, count($similar_users), '?'));

    $sql = "
        SELECT auction_id, COUNT(*) AS strength
        FROM (
            SELECT auction_id
            FROM watchlist
            WHERE user_id IN ($in_users)

            UNION ALL

            SELECT auction_id
            FROM bids
            WHERE bidder_id IN ($in_users)
        ) t
        GROUP BY auction_id
        ORDER BY strength DESC
    ";

    $params_users = array_merge($similar_users, $similar_users);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_users);
    $ranked = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    /* -----------------------------
       STEP 4 — Remove already seen auctions
       ----------------------------- */
    $recommend_ids = array_diff(array_keys($ranked), $user_items);

    if (empty($recommend_ids)) {
        return get_random_recommendations($limit);
    }

    $recommend_ids = array_slice($recommend_ids, 0, $limit);

    /* -----------------------------
       STEP 5 — Fetch auction details
       ----------------------------- */
    $in_final = implode(',', array_fill(0, count($recommend_ids), '?'));

    $sql = "
        SELECT
            a.auction_id,
            i.title,
            u.display_name AS seller,
            a.starting_price,
            COALESCE(MAX(b.amount), a.starting_price) AS highest_bid,
            a.end_time
        FROM auctions a
        JOIN items i ON a.item_id = i.item_id
        JOIN users u ON u.user_id = i.seller_id
        LEFT JOIN bids b ON b.auction_id = a.auction_id
        WHERE a.auction_id IN ($in_final)
        GROUP BY a.auction_id
        ORDER BY FIELD(a.auction_id, " . implode(',', $recommend_ids) . ")
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($recommend_ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/* ==============================
   FALLBACK FUNCTION
   ============================== */
function get_random_recommendations($limit = 5) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            a.auction_id,
            i.title,
            u.display_name AS seller,
            a.starting_price,
            COALESCE(MAX(b.amount), a.starting_price) AS highest_bid,
            a.end_time
        FROM auctions a
        JOIN items i ON a.item_id = i.item_id
        JOIN users u ON u.user_id = i.seller_id
        LEFT JOIN bids b ON b.auction_id = a.auction_id
        GROUP BY a.auction_id
        ORDER BY a.start_time ASC
        LIMIT ?
    ");

    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
