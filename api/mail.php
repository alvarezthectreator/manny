<?php

declare(strict_types=1);

/**
 * Send application email without making authentication or ledger writes fail when mail is unavailable.
 */
function cladvest_mail_config(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }

    $localPath = __DIR__ . '/../config/mail.local.php';
    $local = is_file($localPath) ? require $localPath : [];
    $read = static function (string $name, mixed $default) use ($local): mixed {
        $value = getenv('CLADVEST_MAIL_' . strtoupper($name));
        return $value !== false && $value !== '' ? $value : ($local[$name] ?? $default);
    };
    $config = [
        'from' => (string) $read('from', 'no-reply@cladvest.local'),
        'from_name' => (string) $read('from_name', 'Cladvest'),
        'enabled' => filter_var($read('enabled', '1'), FILTER_VALIDATE_BOOL),
    ];
    return $config;
}

function cladvest_send_mail(string $to, string $subject, string $body): bool
{
    $config = cladvest_mail_config();
    if (!$config['enabled'] || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = $config['from'];
    $fromName = str_replace(["\r", "\n"], '', $config['from_name']);
    $headers = [
        'From: ' . $fromName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: Cladvest',
    ];
    $sent = mail($to, str_replace(["\r", "\n"], '', $subject), $body, implode("\r\n", $headers));
    if (!$sent) {
        error_log('Cladvest mail delivery failed for ' . $to . ' subject ' . $subject);
    }
    return $sent;
}

function cladvest_send_registration_email(string $to, string $firstName): void
{
    cladvest_send_mail(
        $to,
        'Welcome to Cladvest',
        "Hello {$firstName},\n\nYour Cladvest account is ready. You can now sign in and manage your account.\n\nCladvest"
    );
}

function cladvest_send_login_email(string $to, string $ip): void
{
    $location = $ip !== '' ? " from {$ip}" : '';
    cladvest_send_mail(
        $to,
        'New Cladvest sign-in',
        "Your Cladvest account was signed in to{$location}.\n\nIf this was not you, change your password and review your account immediately.\n\nCladvest"
    );
}

function cladvest_send_password_reset_email(string $to, string $resetUrl): void
{
    cladvest_send_mail(
        $to,
        'Reset your Cladvest password',
        "A password reset was requested for your Cladvest account.\n\nOpen this link within one hour to choose a new password:\n{$resetUrl}\n\nIf you did not request this, you can ignore this email.\n\nCladvest"
    );
}

function cladvest_send_cash_email(string $to, string $type, string $status, string $amount): void
{
    $label = ucfirst($type);
    $message = match ($status) {
        'pending' => "Your {$label} request for {$amount} is pending administrator review.",
        'completed' => "Your {$label} request for {$amount} was completed and recorded.",
        'rejected' => "Your {$label} request for {$amount} was rejected.",
        default => "Your {$label} request for {$amount} has been updated to {$status}.",
    };
    cladvest_send_mail($to, "Cladvest {$label} request {$status}", $message . "\n\nCladvest");
}
