/* ============================================================
   queries.sql — Core Queries / Updates / Deletes (MySQL 8.0+)
   Features:
   - Fully consistent with schema.sql (status: scheduled | running | ended | cancelled)
   - Uses placeholders (?) for prepared statements
   - Implements a unified rule for determining the "current highest bid / winner"
     using CTE + window functions:
       —— Ordered by amount DESC, and by bid_time ASC for ties;
          ROW_NUMBER() = 1 indicates the current leading bidder
   - Provides transaction templates for strongly consistent operations
     (bidding / auction closing)
   ============================================================ */

USE auction_db;
-- Unified CTE template for "the single current leading bid per auction" (reuse via CTE where needed)
-- Note: In case of equal bid amounts, the earlier bid takes precedence
--   WITH top_bid AS (
--     SELECT auction_id, bidder_id, amount, bid_time,
--            ROW_NUMBER() OVER (PARTITION BY auction_id
--                               ORDER BY amount DESC, bid_time ASC) AS rn
--     FROM bids
--   )

/* ============================================================
   Function 1: User registration and account
   ============================================================ */

-- 1.1 Registration (password_hash() is generated at the application layer)
INSERT INTO users (username, email, password_hash, role)
VALUES (?, ?, ?, ?);

-- 1.2 Login (username or email)
SELECT user_id, username, email, password_hash, role, created_at
FROM users
WHERE username = ? OR email = ?
LIMIT 1;

-- 1.3 Does the username/email exist?
SELECT COUNT(*) FROM users WHERE username = ?;
SELECT COUNT(*) FROM users WHERE email = ?;

-- 1.4 Reading and updating data
SELECT user_id, username, email, role, created_at FROM users WHERE user_id = ?;
UPDATE users SET email = ?         WHERE user_id = ?;
UPDATE users SET password_hash = ? WHERE user_id = ?;

-- 1.5 Administrator query (pagination)
SELECT user_id, username, email, role, created_at
FROM users
ORDER BY created_at DESC
LIMIT ? OFFSET ?;


/* ============================================================
   Function 2: Seller creates/manages auctions
   ============================================================ */

-- 2.1 New product + picture + auction (transactions controlled by the application layer)
INSERT INTO items (seller_id, category_id, title, description, `condition`)
VALUES (?, ?, ?, ?, ?);

INSERT INTO item_images (item_id, url, display_order)
VALUES (?, ?, ?);

INSERT INTO auctions (item_id, seller_id, starting_price, reserve_price, start_time, end_time, current_status)
VALUES (?, ?, ?, ?, ?, ?, 'scheduled');

-- 2.2 Seller’s auction (including current price/number of bids)
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  a.auction_id, i.title, i.description, c.name AS category_name,
  a.starting_price, a.reserve_price, a.start_time, a.end_time, a.current_status,
  COALESCE(tb.amount, a.starting_price) AS current_price,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id=a.auction_id) AS total_bids
FROM auctions a
JOIN items i      ON i.item_id=a.item_id
JOIN categories c ON c.category_id=i.category_id
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE a.seller_id=?
ORDER BY a.created_at DESC;

-- 2.3 Edit unstarted auctions
UPDATE auctions
SET starting_price=?, reserve_price=?, start_time=?, end_time=?
WHERE auction_id=? AND seller_id=? AND current_status='scheduled';

-- 2.4 Cancel only if "no bid" (scheduled or running)
-- It is recommended that only scheduled cancellation is allowed in the business; if running is allowed, there must be no bids.
UPDATE auctions a
LEFT JOIN (
  SELECT auction_id, COUNT(*) AS cnt FROM bids GROUP BY auction_id
) bx ON bx.auction_id=a.auction_id
SET a.current_status='cancelled'
WHERE a.auction_id=? AND a.seller_id=? AND a.current_status IN ('scheduled','running')
  AND COALESCE(bx.cnt,0)=0;

-- 2.5 Category drop-down
SELECT c1.category_id, c1.name, c1.parent_category_id, c2.name AS parent_name
FROM categories c1
LEFT JOIN categories c2 ON c2.category_id=c1.parent_category_id
ORDER BY COALESCE(c2.name,''), c1.name;

-- 2.6 The seller reads the details of a single auction (to facilitate backfilling of the editing form)
SELECT
  a.auction_id, a.item_id,
  i.title, i.description, i.`condition`, i.category_id,
  a.starting_price, a.reserve_price, a.start_time, a.end_time, a.current_status
FROM auctions a
JOIN items i ON i.item_id=a.item_id
WHERE a.auction_id=? AND a.seller_id=?;

-- 2.7 Deletion of pictures (limited to pictures of products under the seller’s name)
DELETE FROM item_images
WHERE image_id=?
  AND item_id IN (SELECT item_id FROM items WHERE seller_id=?);


/* ============================================================
   Function 3: Buyer search/browse
   ============================================================ */

-- 3.1 Full text search (running and not ended)
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  a.auction_id, i.title, i.description, i.`condition`,
  c.name AS category_name,
  a.starting_price, a.end_time, a.current_status,
  u.username AS seller_username,
  COALESCE(tb.amount, a.starting_price) AS current_price,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id=a.auction_id) AS bid_count
FROM auctions a
JOIN items i      ON i.item_id=a.item_id
JOIN categories c ON c.category_id=i.category_id
JOIN users u      ON u.user_id=a.seller_id
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE a.current_status='running' AND a.end_time>NOW()
  AND MATCH(i.title, i.description) AGAINST(? IN NATURAL LANGUAGE MODE)
ORDER BY a.end_time ASC
LIMIT ? OFFSET ?;

-- 3.2 LIKE 兜底搜索
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  a.auction_id, i.title, i.description, i.`condition`,
  c.name AS category_name, a.starting_price, a.end_time,
  u.username AS seller_username,
  COALESCE(tb.amount, a.starting_price) AS current_price,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id=a.auction_id) AS bid_count
FROM auctions a
JOIN items i      ON i.item_id=a.item_id
JOIN categories c ON c.category_id=i.category_id
JOIN users u      ON u.user_id=a.seller_id
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE a.current_status='running' AND a.end_time>NOW()
  AND (i.title LIKE ? OR i.description LIKE ?)
ORDER BY a.end_time ASC
LIMIT ? OFFSET ?;

-- 3.3 Category browsing
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  a.auction_id, i.title, i.`condition`,
  COALESCE(tb.amount, a.starting_price) AS current_price,
  a.end_time,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id=a.auction_id) AS bid_count
FROM auctions a
JOIN items i ON i.item_id=a.item_id
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE a.current_status='running' AND a.end_time>NOW()
  AND i.category_id=?
ORDER BY a.end_time ASC
LIMIT ? OFFSET ?;

-- 3.4 Price ascending/descending order, coming to an end, popular (parameterized sorting)
-- Description: The application layer replaces ORDER BY with whitelist splicing (to avoid injection)
-- Here are two commonly used sorting examples:
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  a.auction_id, i.title, c.name AS category_name, a.end_time,
  COALESCE(tb.amount, a.starting_price) AS current_price,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id=a.auction_id) AS bid_count
FROM auctions a
JOIN items i      ON i.item_id=a.item_id
JOIN categories c ON c.category_id=i.category_id
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE a.current_status='running' AND a.end_time>NOW()
ORDER BY current_price ASC
LIMIT ? OFFSET ?;

WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  a.auction_id, i.title, c.name AS category_name, a.end_time,
  COALESCE(tb.amount, a.starting_price) AS current_price,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id=a.auction_id) AS bid_count
FROM auctions a
JOIN items i      ON i.item_id=a.item_id
JOIN categories c ON c.category_id=i.category_id
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE a.current_status='running' AND a.end_time>NOW()
ORDER BY current_price DESC
LIMIT ? OFFSET ?;

-- 3.5 Auction details page (including current price/total bid/seller/category/pictures)
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  a.auction_id, i.item_id, i.title, i.description, i.`condition`,
  c.category_id, c.name AS category_name,
  u.user_id AS seller_id, u.username AS seller_username,
  a.starting_price, a.reserve_price, a.start_time, a.end_time, a.current_status,
  COALESCE(tb.amount, a.starting_price) AS current_price,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id=a.auction_id) AS total_bids
FROM auctions a
JOIN items i      ON i.item_id=a.item_id
JOIN categories c ON c.category_id=i.category_id
JOIN users u      ON u.user_id=a.seller_id
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE a.auction_id=?;

-- 3.6 All pictures in this auction
SELECT image_id, url, display_order
FROM item_images
WHERE item_id=(SELECT item_id FROM auctions WHERE auction_id=?)
ORDER BY display_order ASC;


/* ============================================================
   Function 4: Bidding and auction life cycle
   ============================================================ */

-- 4.0 Verify pre-bid context (current price/status/seller)
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  a.auction_id, a.current_status, a.start_time, a.end_time, a.seller_id,
  COALESCE(tb.amount, a.starting_price) AS current_highest
FROM auctions a
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE a.auction_id=?;

-- 4.1 Submit the bid (actually it must be placed in the transaction, see 4.2 Template)
INSERT INTO bids (auction_id, bidder_id, amount, is_proxy)
VALUES (?, ?, ?, FALSE);

-- 4.2 Transactional bidding (template, executed at the application layer; SQL fragment shown here)
-- BEGIN;
--   SELECT * FROM auctions WHERE auction_id=? FOR UPDATE;
--   -- Check that status = 'running', NOW() < end_time, and bidder != seller
--   WITH top_bid AS (
--     SELECT auction_id, bidder_id, amount, bid_time,
--       ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
--     FROM bids WHERE auction_id=?
--   )
--   SELECT COALESCE(MAX(amount), (SELECT starting_price FROM auctions WHERE auction_id=?)) AS cur
--   FROM bids WHERE auction_id=? FOR UPDATE;
--   -- Or retrieve amount where rn=1 from top_bid; if NULL, use starting_price
--   INSERT INTO bids(auction_id, bidder_id, amount, is_proxy) VALUES (?,?,?,FALSE);
--   -- Send “outbid” notifications to watchlist users (excluding the bidder themself)
--   INSERT INTO notifications(user_id, auction_id, type, content)
--   SELECT w.user_id, ?, 'outbid', CONCAT('New higher bid: ', FORMAT(?,2))
--   FROM watchlist w WHERE w.auction_id=? AND w.user_id<>?;
-- COMMIT;

-- 4.3 Bid list for a specific auction (latest first, marks whether the bid is leading)
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  b.bid_id, b.amount, b.bid_time, b.is_proxy, u.username AS bidder_username,
  CASE WHEN (tb.bidder_id=b.bidder_id AND tb.amount=b.amount AND tb.rn=1) THEN TRUE ELSE FALSE END AS is_highest
FROM bids b
JOIN users u ON u.user_id=b.bidder_id
LEFT JOIN top_bid tb ON tb.auction_id=b.auction_id AND tb.rn=1
WHERE b.auction_id=?
ORDER BY b.bid_time DESC;

-- 4.4 User’s bidding history (with current status/leading or not)
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  b.bid_id, b.auction_id, i.title AS item_title, b.amount AS my_bid, b.bid_time,
  a.end_time, a.current_status,
  COALESCE(tb.amount, a.starting_price) AS current_highest,
  CASE WHEN (tb.bidder_id=b.bidder_id AND tb.amount=b.amount AND tb.rn=1)
       THEN 'Leading' ELSE 'Outbid' END AS bid_status
FROM bids b
JOIN auctions a ON a.auction_id=b.auction_id
JOIN items i    ON i.item_id=a.item_id
LEFT JOIN top_bid tb ON tb.auction_id=b.auction_id AND tb.rn=1
WHERE b.bidder_id=?
ORDER BY b.bid_time DESC;

-- 4.5 Scheduled switching status: scheduled -> running
UPDATE auctions
SET current_status='running'
WHERE current_status='scheduled'
  AND start_time<=NOW() AND end_time>NOW();

-- 4.6 Timing switching status: running -> ended (only the status bit, see 4.7 for the real "end and order")
UPDATE auctions
SET current_status='ended'
WHERE current_status='running'
  AND end_time<=NOW();

-- 4.7 Auction closing (recommended: application layer loops through candidate set; 
-- this is a transaction template for a single auction_id)
-- BEGIN;
--   SELECT * FROM auctions WHERE auction_id=? FOR UPDATE;
--   WITH top_bid AS (
--     SELECT auction_id, bidder_id, amount, bid_time,
--            ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
--     FROM bids WHERE auction_id=?
--   )
--   SELECT bidder_id, amount FROM (
--     SELECT auction_id, bidder_id, amount, bid_time,
--            ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
--     FROM bids WHERE auction_id=?
--   ) z WHERE rn=1;          -- Get @winner and @final
--   -- If there are no bids or the reserve price is not met, mark as ended but no sale;
--   -- notify the seller only.
--   UPDATE auctions SET current_status='ended' 
--   WHERE auction_id=? AND current_status='running';
--   INSERT INTO transactions(auction_id, winner_id, amount, status)
--   VALUES (?,?,?,'pending'); -- Only if sale conditions are met
--   INSERT INTO notifications(user_id, auction_id, type, content) VALUES
--     (?, ?, 'win',  CONCAT('You won auction #', ?, ' at ', FORMAT(?,2))),
--     ((SELECT seller_id FROM auctions WHERE auction_id=?), ?, 'sold',
--      CONCAT('Auction #', ?, ' sold at ', FORMAT(?,2)));
-- COMMIT;

-- 4.8 Auctions / transactions I have won
SELECT
  t.tx_id, t.auction_id, t.amount, t.status, t.created_at,
  i.title AS item_title, u.username AS seller_username, u.email AS seller_email
FROM transactions t
JOIN auctions a ON a.auction_id=t.auction_id
JOIN items i    ON i.item_id=a.item_id
JOIN users u    ON u.user_id=a.seller_id
WHERE t.winner_id=?
ORDER BY t.created_at DESC;


/* ============================================================
   Extras E5: Follow and Notify
   ============================================================ */

-- 5.1 Join/update following
INSERT INTO watchlist(user_id, auction_id)
VALUES (?, ?)
ON DUPLICATE KEY UPDATE created_at=CURRENT_TIMESTAMP;

-- 5.2 Unfollow
DELETE FROM watchlist WHERE user_id=? AND auction_id=?;

-- 5.3 Have you followed
SELECT EXISTS(SELECT 1 FROM watchlist WHERE user_id=? AND auction_id=?) AS is_watching;

-- 5.4 My watch list (including current price/number of bids/my status)
WITH top_bid AS (
  SELECT auction_id, bidder_id, amount, bid_time,
         ROW_NUMBER() OVER (PARTITION BY auction_id ORDER BY amount DESC, bid_time ASC) AS rn
  FROM bids
)
SELECT
  w.auction_id, w.created_at AS watched_since,
  i.title, c.name AS category_name, a.end_time, a.current_status,
  COALESCE(tb.amount, a.starting_price) AS current_price,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id=a.auction_id) AS bid_count,
  CASE
    WHEN tb.bidder_id = w.user_id THEN 'Leading'
    WHEN EXISTS (SELECT 1 FROM bids b WHERE b.auction_id=a.auction_id AND b.bidder_id=w.user_id)
      THEN 'Outbid'
    ELSE 'Watching'
  END AS my_status
FROM watchlist w
JOIN auctions a   ON a.auction_id=w.auction_id
JOIN items i      ON i.item_id=a.item_id
JOIN categories c ON c.category_id=i.category_id
LEFT JOIN top_bid tb ON tb.auction_id=a.auction_id AND tb.rn=1
WHERE w.user_id=?
  AND a.current_status IN ('running','scheduled')
ORDER BY a.end_time ASC
LIMIT ? OFFSET ?;

-- 5.5 Notification writing (overpriced/ended/won)
INSERT INTO notifications(user_id, auction_id, type, content)
VALUES (?, ?, 'outbid', ?);

INSERT INTO notifications(user_id, auction_id, type, content)
VALUES (?, ?, 'auction_ended', ?);

INSERT INTO notifications(user_id, auction_id, type, content)
VALUES (?, ?, 'auction_won', ?);

-- 5.6 Get/mark notifications
SELECT n.notification_id, n.auction_id, n.type, n.content, n.created_at,
       i.title AS auction_title
FROM notifications n
LEFT JOIN auctions a ON a.auction_id=n.auction_id
LEFT JOIN items i    ON i.item_id=a.item_id
WHERE n.user_id=? AND n.is_read=FALSE
ORDER BY n.created_at DESC
LIMIT 50;

SELECT COUNT(*) AS unread_count
FROM notifications
WHERE user_id=? AND is_read=FALSE;

UPDATE notifications SET is_read=TRUE WHERE notification_id=? AND user_id=?;
UPDATE notifications SET is_read=TRUE WHERE user_id=? AND is_read=FALSE;

-- 5.7 Get other users who follow the auction (for mass notification/email)
SELECT DISTINCT w.user_id, u.email, u.username
FROM watchlist w
JOIN users u ON u.user_id=w.user_id
WHERE w.auction_id=? AND w.user_id<>?;

-- 5.8 Clear historical read notifications
DELETE FROM notifications
WHERE is_read=TRUE AND created_at < NOW() - INTERVAL 90 DAY;


/* ============================================================
   Additional functions: statistics/reports
   ============================================================ */

-- S1 Category popularity
SELECT
  c.category_id, c.name,
  COUNT(DISTINCT CASE WHEN a.current_status='running' AND a.end_time>NOW() THEN a.auction_id END) AS running_auctions,
  COUNT(b.bid_id) AS total_bids
FROM categories c
LEFT JOIN items i   ON i.category_id=c.category_id
LEFT JOIN auctions a ON a.item_id=i.item_id
LEFT JOIN bids b     ON b.auction_id=a.auction_id
GROUP BY c.category_id
ORDER BY running_auctions DESC, total_bids DESC;

-- S2 Seller’s completed transactions and net revenue in the past 30 days
SELECT
  u.user_id AS seller_id, u.username AS seller_name,
  COUNT(t.tx_id) AS sold_count,
  COALESCE(SUM(CASE WHEN t.status='paid' THEN t.amount END),0) AS paid_sum
FROM users u
LEFT JOIN auctions a     ON a.seller_id=u.user_id
LEFT JOIN transactions t ON t.auction_id=a.auction_id
WHERE u.role IN ('seller','admin') AND a.end_time >= NOW() - INTERVAL 30 DAY
GROUP BY u.user_id
ORDER BY paid_sum DESC, sold_count DESC;
