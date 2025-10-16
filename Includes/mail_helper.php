<?php
// mail_helper.php
require_once __DIR__ . '/mail_config.php';

// composer autoload
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    // Vendor autoload missing — mail sending won't work
    // But we still keep demo fallback in callers.
    $composer_autoload_missing = true;
} else {
    require_once $composerAutoload;
    $composer_autoload_missing = false;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an HTML email using PHPMailer.
 * Returns array: [bool $ok, string $errorMessage]
 */
function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): array {
    if (!MAIL_SEND_ENABLED) {
        return [false, 'MAIL_SEND_ENABLED is false (demo mode)'];
    }

    global $composer_autoload_missing;
    if ($composer_autoload_missing) {
        return [false, 'Composer autoload (vendor/autoload.php) not found'];
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;

        // encryption
        if (!empty(SMTP_SECURE)) {
            if (strtolower(SMTP_SECURE) === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        }

        $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);

        $mail->send();
        return [true, ''];
    } catch (Exception $e) {
        return [false, $mail->ErrorInfo ?? $e->getMessage()];
    }
}