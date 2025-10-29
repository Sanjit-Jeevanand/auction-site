-- Create database
DROP DATABASE IF EXISTS auction_db;
CREATE DATABASE auction_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE auction_db;

-- ============================================
-- Table 1: USERS
-- Stores all system user information including buyers, sellers and administrators
-- ============================================

-- ============================================
-- USERS TABLE (FINAL VERSION)
-- ============================================

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- primary contact / authentication
  email VARCHAR(255) NOT NULL UNIQUE,        -- primary email (login)
  alt_email VARCHAR(255) DEFAULT NULL,       -- optional alternate email
  password_hash VARCHAR(255) NOT NULL,       -- password_hash()

  -- personal / profile information
  title VARCHAR(20) DEFAULT NULL,            -- Mr / Ms / Dr / etc
  first_name VARCHAR(100) DEFAULT NULL,
  last_name VARCHAR(100) DEFAULT NULL,
  display_name VARCHAR(120) DEFAULT NULL,
  date_of_birth DATE DEFAULT NULL,           -- for age checks
  phone VARCHAR(30) DEFAULT NULL,
  country VARCHAR(100) DEFAULT NULL,
  language VARCHAR(50) DEFAULT 'en',         -- preferred language
  currency CHAR(3) DEFAULT 'GBP',            -- preferred currency (ISO)
  profile_json JSON DEFAULT NULL,            -- flexible profile extension

  -- account metadata
  role ENUM('buyer','seller','both','admin') NOT NULL DEFAULT 'buyer',
  is_email_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  email_confirmation_token VARCHAR(128) DEFAULT NULL,
  subscribe_updates TINYINT(1) NOT NULL DEFAULT 1, -- opt-in for newsletters / alerts
  is_verified TINYINT(1) NOT NULL DEFAULT 0,       -- ID/doc verification status

  -- password reset / recovery
  reset_token VARCHAR(128) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL,

  -- security & login protection
  failed_login_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,

  -- timestamps
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  last_login_at DATETIME DEFAULT NULL,

  -- indexes for performance
  INDEX idx_role (role),
  INDEX idx_country (country),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
  
-- ============================================
-- Table 2: CATEGORIES
-- Stores classification information of auction items and supports hierarchical structure
-- ============================================
CREATE TABLE categories (
  category_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100) NOT NULL,
  parent_category_id INT UNSIGNED NULL,

  CONSTRAINT fk_cat_parent
    FOREIGN KEY (parent_category_id) REFERENCES categories(category_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  UNIQUE KEY uk_cat_name_parent (name, parent_category_id)
) ENGINE=InnoDB;

-- ============================================
-- Table 3: ITEMS
-- Store information about items to be auctioned
-- ============================================
CREATE TABLE items (
  item_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id   INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title       VARCHAR(200)  NOT NULL,
  description TEXT          NOT NULL,
  `condition` VARCHAR(50)   NOT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_item_seller
    FOREIGN KEY (seller_id)   REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_item_category
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  INDEX idx_item_category (category_id),
  INDEX idx_item_seller   (seller_id),
  INDEX idx_item_created  (created_at),
  FULLTEXT INDEX ft_item_title_desc (title, description),
  CHECK (`condition` IN ('new','like new','used','refurbished'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- Table 4: ITEMIMAGES
-- Image URL of stored items, supports multiple images
-- ============================================
CREATE TABLE item_images (
  image_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id       INT UNSIGNED NOT NULL,
  url           VARCHAR(500) NOT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_img_item
    FOREIGN KEY (item_id) REFERENCES items(item_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uk_item_order (item_id, display_order),

  INDEX idx_item_disp (item_id, display_order)
) ENGINE=InnoDB;


-- ============================================
-- Table 5: AUCTIONS
-- Store auction information including price, time and status
-- ============================================
CREATE TABLE auctions (
  auction_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id        INT UNSIGNED NOT NULL,
  seller_id      INT UNSIGNED NOT NULL,
  starting_price DECIMAL(12,2) NOT NULL,
  reserve_price  DECIMAL(12,2) NULL,
  start_time     DATETIME NOT NULL,
  end_time       DATETIME NOT NULL,
  current_status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_auc_item
    FOREIGN KEY (item_id) REFERENCES items(item_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_auc_seller
    FOREIGN KEY (seller_id) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CHECK (current_status IN ('scheduled','running','ended','cancelled')),
  CHECK (end_time > start_time),
  CHECK (reserve_price IS NULL OR reserve_price >= starting_price),

  INDEX idx_auc_times    (start_time, end_time),
  INDEX idx_auc_end_time (end_time), 
  INDEX idx_auc_status   (current_status),
  INDEX idx_auc_seller   (seller_id),
  INDEX idx_auc_item     (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- Table 6: BIDS
-- Record bidding information for all users
-- ============================================
CREATE TABLE bids (
  bid_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auction_id INT UNSIGNED NOT NULL,
  bidder_id  INT UNSIGNED NOT NULL,
  amount     DECIMAL(12,2) NOT NULL,
  bid_time   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_proxy   BOOLEAN NOT NULL DEFAULT FALSE,

  CONSTRAINT fk_bid_auc
    FOREIGN KEY (auction_id)
    REFERENCES auctions(auction_id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_bid_user
    FOREIGN KEY (bidder_id)
    REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  INDEX idx_bid_auc_time (auction_id, bid_time),
  INDEX idx_bid_auc_amt  (auction_id, amount DESC),
  INDEX idx_bid_bidder   (bidder_id),

  CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================
-- Table 7: WATCHLIST
-- Store auctions that users follow
-- ============================================
CREATE TABLE watchlist (
  user_id    INT UNSIGNED NOT NULL,
  auction_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (user_id, auction_id),

  KEY idx_auction (auction_id),

  CONSTRAINT fk_watch_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_watch_auc
    FOREIGN KEY (auction_id) REFERENCES auctions(auction_id)
    ON DELETE CASCADE ON UPDATE CASCADE
)ENGINE=InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================
-- Table 8: TRANSACTIONS
-- Record transaction results after the auction ends
-- ============================================
CREATE TABLE transactions (
  tx_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auction_id INT UNSIGNED NOT NULL,
  winner_id  INT UNSIGNED NOT NULL,
  amount     DECIMAL(12,2) NOT NULL,
  status     VARCHAR(20)   NOT NULL,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_tx_auc
    FOREIGN KEY (auction_id) REFERENCES auctions(auction_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_tx_winner
    FOREIGN KEY (winner_id) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CHECK (status IN ('pending','paid','failed','cancelled')),
  UNIQUE KEY uk_tx_auc (auction_id),

  INDEX idx_tx_winner (winner_id),
  INDEX idx_tx_status (status),
  INDEX idx_tx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================
-- Table 9: NOTIFICATIONS
-- Various notifications sent by the storage system to users
-- ============================================
CREATE TABLE notifications (
  notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  auction_id INT UNSIGNED NULL,
  type       VARCHAR(50)  NOT NULL,
  content    VARCHAR(500) NOT NULL,
  is_read    BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_notif_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_notif_auc
    FOREIGN KEY (auction_id) REFERENCES auctions(auction_id)
    ON DELETE SET NULL ON UPDATE CASCADE,

  INDEX idx_notif_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
