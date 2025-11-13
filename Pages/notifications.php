<style>
.unread-row {
    background-color: #f8f9fa;   /* Light grey for unread notifications */
    font-weight: 500;
}
.read-row {
    color: #555;                 /* Dim grey for read notifications */
}
</style>

<?php
require_once __DIR__ . '/../Includes/db.php';
require_once __DIR__ . '/../Includes/header.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>
        Please log in to view your notifications.
    </div></div>";
    require_once __DIR__ . '/../Includes/footer.php';
    exit;
}

// Decide where to redirect when clicking a notification
// Use $current_role from header.php and normalise it
$role = strtolower($current_role ?? ($_SESSION['role'] ?? 'buyer'));

$auction_page = ($role === 'seller' || $role === 'both')

    ? 'seller_auctions.php'
    : 'buyer_auctions.php';



// ✅ PAGINATION + FETCH
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;   // change to 5 later
$limit  = max(1, min(50, $limit));                           // safety clamp
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page   = max(1, $page);
$offset = ($page - 1) * $limit;

// Get total notification count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid");
$countStmt->execute(['uid' => (int)$user_id]);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));

// 🔒 Build safe paginated query
$sql = "
  SELECT notification_id, auction_id, type, content, is_read, created_at
  FROM notifications
  WHERE user_id = :uid
  ORDER BY is_read ASC, created_at DESC
  LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => (int)$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Notifications</h2>

    <?php if (empty($notifications)): ?>
        <div class="alert alert-info mt-3">No notifications yet.</div>
    <?php else: ?>
        <table class="table table-striped mt-3">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Auction</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr class="<?= $n['is_read'] ? 'read-row' : 'unread-row'; ?>"
    onclick="window.location='<?= $auction_page ?>?mark_read=<?= (int)$n['notification_id']; ?>&auction_id=<?= (int)$n['auction_id']; ?>'"

    style="cursor:pointer;">

                        <td>
                            <?= htmlspecialchars($n['type']); ?>
                            <?= $n['is_read'] ? '✅' : '🔔'; ?>
                        </td>
                        <td><?= htmlspecialchars($n['content']); ?></td>
                        <td>
                            <?php if (!empty($n['auction_id'])): ?>
                                <a href="<?= $auction_page ?>?mark_read=<?= (int)$n['notification_id']; ?>&auction_id=<?= (int)$n['auction_id']; ?>">

                                    View Auction
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($n['created_at']))); ?></td>
                        <td><?= $n['is_read'] ? 'Read' : 'Unread'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ✅ Pagination Buttons -->
<div class="d-flex justify-content-center align-items-center mt-4 mb-4">
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Notification pages">
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?page=<?= $i ?>&limit=<?= $limit ?>"
                           style="border-radius: 5px; padding: 5px 12px;">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>
