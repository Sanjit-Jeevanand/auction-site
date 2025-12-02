
# Auction Site — COMP0178 Coursework Project

## 🏗 Overview
This project is a fully functional **online auction platform** built using PHP (with MySQL via phpMyAdmin), Bootstrap 5, and PDO.  
It allows users to register, browse, bid, and manage auctions securely, following best practices for authentication, authorization, and web security.

Developed as part of **COMP0178 Web Development Coursework**, this application demonstrates modern PHP development with secure form handling, session management, and data-driven dynamic pages.

---

## 🚀 Features

### 1. **User Management & Roles**
- Secure registration and login system with `password_hash()`.
- Roles: **Buyer**, **Seller**, or **Both**.
- Email confirmation (token-based) system.
- Forgot password & reset functionality.
- Profile management with editable details.
- Session timeout and security enforcement.
- Role-based access restrictions (e.g., sellers only for auction creation).

### 2. **Auction Management**
- Sellers can create auctions for items.
- Each item supports multiple images and category classification.
- Auctions include:
  - Starting price & reserve price
  - Start and end time
  - Automated status changes (scheduled → running → ended)
- Sellers can view and manage their own auctions.

### 3. **Item Browsing & Search**
- Buyers can search auctions by keyword, category, price range, and status.
- Sorting and filtering options:
  - Price (low–high / high–low)
  - Time left
  - Popularity
- Pagination for scalable browsing.

### 4. **Bidding System**
- Buyers can place bids in real time or upon refresh.
- System tracks highest bids automatically.
- Auction auto-closes at end time.
- Winner determined and notified automatically.
- Bidding validation ensures fair competition (must exceed current highest bid).

### 5. **Notifications & Watchlist**
- Buyers can watch auctions and get notifications.
- Notifications for:
  - Winning an auction
  - Being outbid
  - Auction ending
- Read/unread tracking for notifications.

### 6. **Transactions**
- After an auction ends, the system automatically records a transaction:
  - Links auction → winner → final bid.
  - Tracks payment and completion status.

---

## 🧱 Database Schema (Simplified)
Core tables:
- `users` — all user and authentication data  
- `categories` — item categories  
- `items` — auction items  
- `auctions` — auction lifecycle  
- `bids` — all bids placed  
- `watchlist` — users’ saved auctions  
- `transactions` — final sale records  
- `notifications` — alerts for user actions  

Database: `auction_db`

---

## 🧩 Security Features
- PDO prepared statements (SQL Injection protection)
- CSRF tokens for all forms
- XSS prevention with HTML escaping (`h()` helper)
- Password hashing (`password_hash()` and `password_verify()`)
- Session timeout, regeneration, and validation
- Role-based authorization
- Email confirmation tokens for verification
- Secure password reset with expiry tokens

---

## 🛠 Technology Stack
| Component | Technology |
|------------|-------------|
| **Frontend** | HTML5, CSS3, Bootstrap 5, JavaScript |
| **Backend** | PHP 8+ (Procedural with Helpers) |
| **Database** | MySQL (via phpMyAdmin) |
| **Session Management** | PHP Sessions |
| **Mail System** | PHPMailer (demo links for coursework) |
| **Environment** | XAMPP (Apache + MySQL) |

---

## ⚙️ Installation Guide

### Prerequisites
- **XAMPP** installed (Apache + MySQL)
- **phpMyAdmin** accessible
- PHP 8.0+ recommended

### Steps
1. Clone or copy the project into your XAMPP `htdocs` directory:
   ```bash
   /Applications/XAMPP/xamppfiles/htdocs/auction-site
   ```
2. Start Apache and MySQL via XAMPP control panel.
3. Open phpMyAdmin and create the database:
   ```sql
   SOURCE /Applications/XAMPP/xamppfiles/htdocs/auction-site/sql/schema.sql;
   ```
4. Configure database credentials in:
   ```
   includes/db.php
   ```
5. Open your browser and visit:
   ```
   http://localhost/auction-site/Pages/register.php
   ```

---

## 🧰 File Structure

```
auction-site/
├── Database/
│   ├── schema.sql          # Full database schema (tables, constraints, seed categories)
│   └── queries.sql         # Core SELECT/INSERT/UPDATE/DELETE queries used by the app
│
├── Includes/
│   ├── active_proxy.php        # Logic to activate / manage proxy bidding
│   ├── add_to_watchlist.php    # Add an auction to the watchlist
│   ├── db.php                  # Main PDO database connection (used in production)
│   ├── db1.php                 # Alternative / test DB connection (for local debugging)
│   ├── footer.php              # Shared footer layout
│   ├── header.php              # Shared header + navigation bar
│   ├── helpers.php             # Helper functions (sessions, auth, formatting, etc.)
│   ├── logger.php              # Simple logging utilities
│   ├── notify.php              # Notification helpers (outbid / win / end, etc.)
│   ├── proxy.php               # Proxy-bidding backend logic
│   ├── recommend.php           # Recommendation / personalised suggestion logic
│   └── remove_from_watchlist.php  # Remove auction from watchlist
│
├── Pages/
│   ├── Images/                 # Uploaded item images (runtime, not versioned)
│   │   └── …                   # Image files created at upload time
│   │
│   ├── bid_history.php         # User bid history
│   ├── buyer_auctions.php      # Buyer view of active auctions
│   ├── confirm_email.php       # Email confirmation landing page
│   ├── create_auction.php      # Form + logic to create a new auction
│   ├── create_bid.php          # Place a bid on an auction
│   ├── create_item.php         # Create a new item (title/desc/category/images)
│   ├── forgot_password.php     # Start password reset workflow
│   ├── login.php               # User login
│   ├── logout.php              # Session logout
│   ├── notifications.php       # List of notifications for the current user
│   ├── profile.php             # View own profile
│   ├── profile_edit.php        # Edit profile details
│   ├── register.php            # User registration
│   ├── reset_password.php      # Complete password reset
│   ├── seller_auctions.php     # Seller’s auctions (status, bids, prices)
│   ├── seller_items.php        # Items created by the seller
│   ├── seller_profile.php      # Public/extended seller profile
│   ├── set_auction_session.php # Helper endpoints for storing auction state in session
│   ├── set_bid_session.php
│   ├── set_history_session.php
│   ├── set_seller_history_session.php
│   ├── test_db.php             # Simple DB connectivity test page
│   └── watchlist.php           # User watchlist view
│
├── ERD_draft1.png              # ER diagram draft of the database
├── LICENSE
└── README.md                   # This file
```

---

## 🧾 Sample User Workflow
1. Register with your email → receive demo confirmation link.  
2. Confirm email → login.  
3. Edit your profile and choose role (Buyer/Seller).  
4. As a Seller:
   - Create a new item and auction.  
   - Set start and end times.  
5. As a Buyer:
   - Browse or search items.  
   - Place bids and monitor your watchlist.  
6. When auction ends:
   - Winner is recorded in `transactions`.  
   - Notifications are sent to both parties.

---

## 🔒 Logging & Monitoring
- JSON-encoded logs written to PHP error log via `log_event()`.
- Captures:
  - Registrations
  - Login attempts and failures
  - Password resets
  - Auction creation and bidding
- Helps detect brute-force attempts or suspicious activity.

---

## 👥 Authors
**Group Project Members**
- Member A — System Design & Report Writing Lead  
- Member B — Database Implementation & SQL Developer  
- Member C — User Management & Role Module (Backend Developer)
- Member D — Auction Logic & Bidding System Lead  
- Member E — Advanced Features & System Integration Lead  

---

## 📸 Demo Highlights
- Secure Registration & Login  
- Role-based Dashboard  
- Email Confirmation  
- Bidding Simulation  
- Auction End Auto-Closure  
- Profile Editing with Password Change  
- Logging & Session Timeout Demo  

---

## 🧠 Learning Outcomes
- Implemented secure authentication using PHP sessions.
- Applied real-world web security (CSRF, XSS, SQLi prevention).
- Understood role-based authorization in multi-user systems.
- Learned database design for relational auction models.
- Demonstrated practical PHP/MySQL integration with user experience design.

---

## 📜 License
This project is for **educational purposes only** under the UCL COMP0178 coursework.  
Redistribution or commercial use is not permitted.

---
