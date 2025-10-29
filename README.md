
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
├── includes/
│   ├── db.php
│   ├── helpers.php
│   ├── logger.php
│   ├── header.php
│   ├── footer.php
│   └── mail_helper.php
│
├── Pages/
│   ├── register.php
│   ├── login.php
│   ├── logout.php
│   ├── profile.php
│   ├── profile_edit.php
│   ├── confirm_email.php
│   ├── forgot_password.php
│   ├── reset_password.php
│   ├── create_auction.php
│   ├── browse.php
│   └── place_bid.php
│
├── assets/
│   ├── css/
│   └── js/
│
├── sql/
│   └── schema.sql
│
├── README.md
└── vendor/ (Composer dependencies)
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
- Member A — Auction Management & Items Module  
- Member B — Bidding & Transaction Module  
- Member C — User Management & Security Module
- Member D — Search, Filtering & Watchlist Module  
- Member E — UI/UX & Testing Module  

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
