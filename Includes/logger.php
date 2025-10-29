<?php
// includes/logger.php
function log_event(string $level, string $message, array $context = []): void {
    $payload = [
        'ts' => date('c'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'user_id' => $_SESSION['user_id'] ?? null
    ];
    // one-line JSON - good for parsing
    error_log(json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}