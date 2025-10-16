<?php
// mail_config.php
// Toggle sending: false = show demo link / don't attempt SMTP
define('MAIL_SEND_ENABLED', false); // set to true when you have SMTP credentials

// SMTP settings (only used if MAIL_SEND_ENABLED === true)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'youraddress@gmail.com');
define('SMTP_PASS', 'your-app-password'); // use Gmail app password or real SMTP password
define('SMTP_FROM', 'youraddress@gmail.com');
define('SMTP_FROM_NAME', 'Auction Site');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl' or '' for none