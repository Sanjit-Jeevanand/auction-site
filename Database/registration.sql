-- WARNING: this drops the existing DB and all its data.
DROP DATABASE IF EXISTS auction_db;

CREATE DATABASE auction_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE auction_db;

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- primary contact / auth
  email VARCHAR(255) NOT NULL UNIQUE,        -- primary email (login)
  alt_email VARCHAR(255) DEFAULT NULL,      -- optional alternate email
  password_hash VARCHAR(255) NOT NULL,      -- store password_hash()

  -- personal / profile fields
  title VARCHAR(20) DEFAULT NULL,           -- Mr / Ms / Dr / etc
  first_name VARCHAR(100) DEFAULT NULL,
  last_name VARCHAR(100) DEFAULT NULL,
  display_name VARCHAR(120) DEFAULT NULL,
  date_of_birth DATE DEFAULT NULL,          -- DOB (for age checks)
  phone VARCHAR(30) DEFAULT NULL,
  country VARCHAR(100) DEFAULT NULL,
  language VARCHAR(50) DEFAULT 'en',        -- preferred language code
  currency CHAR(3) DEFAULT 'GBP',           -- preferred currency ISO code
  profile_json JSON DEFAULT NULL,           -- flexible extra profile fields

  -- account metadata
  role ENUM('buyer','seller','both','admin') NOT NULL DEFAULT 'buyer',
  is_email_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  email_confirmation_token VARCHAR(128) DEFAULT NULL,
  subscribe_updates TINYINT(1) NOT NULL DEFAULT 1, -- newsletter / alerts opt-in
  is_verified TINYINT(1) NOT NULL DEFAULT 0,       -- ID/doc verification status

  -- security / locking
  failed_login_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,

  -- timestamps
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  last_login_at DATETIME DEFAULT NULL,

  -- indexes
  INDEX idx_role (role),
  INDEX idx_country (country),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;