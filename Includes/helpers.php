<?php
// includes/helpers.php
if (session_status() === PHP_SESSION_NONE) session_start();

function login_required() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /auction-site/login.php');
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}