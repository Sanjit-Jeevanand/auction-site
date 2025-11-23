<?php
require_once __DIR__ . '/db.php';

/**
 * Add a notification for a user.
 *
 * @param int $user_id The recipient user.
 * @param int|null $auction_id Related auction (optional).
 * @param string $type Type of notification ('bid', 'status', 'watchlist', etc.)
 * @param string $content The message to show to the user.
 */
function add_notification($user_id, $auction_id, $type, $content): void {
    global $pdo;

    $sql = "INSERT INTO notifications (user_id, auction_id, type, content, is_read, created_at)
            VALUES (:user_id, :auction_id, :type, :content, 0, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'auction_id' => $auction_id,
        'type' => $type,
        'content' => $content
    ]);
}

/**
 * Fetch notifications for a specific user
 */
function get_notifications($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT n.*, a.auction_id, i.title AS item_title
        FROM notifications n
        LEFT JOIN auctions a ON n.auction_id = a.auction_id
        LEFT JOIN items i ON a.item_id = i.item_id
        WHERE n.user_id = :uid
        ORDER BY n.created_at DESC
    ");
    $stmt->execute(['uid' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mark notification as read
 */
function mark_notification_read($notification_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = :nid");
    $stmt->execute(['nid' => $notification_id]);
}

/**
 * Helper to generate safe View Auction link
 */
function get_notification_link($auction_id, $notification_id) {
    if (!$auction_id) return "#";

    // Detect if user is buyer or seller
    $current_user = current_user_id();
    global $pdo;

    // Check who the seller is for that auction
    $stmt = $pdo->prepare("SELECT seller_id FROM auctions WHERE auction_id = ?");
    $stmt->execute([$auction_id]);
    $seller_id = $stmt->fetchColumn();

    if ($current_user == $seller_id) {
        // Seller → go to seller page
        return "../Pages/seller_auctions.php?mark_read={$notification_id}&auction_id={$auction_id}";
    } else {
        // Buyer → go to bid history
        return "../Pages/bid_history.php?mark_read={$notification_id}&auction_id={$auction_id}";
    }
}

