<?php
/**
 * Amazon Invoices Helper Functions
 * 
 * Common utility functions for the Amazon Invoices module
 * 
 * @package AmazonInvoices
 * @author  Your Name
 * @since   1.0.0
 */

/**
 * Get the base URL for the FrontAccounting installation
 * 
 * @return string Base URL
 */
function get_base_url(): string
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get the path to root from current script
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname(dirname($scriptPath)); // Go up from amazon_invoices folder
    
    return rtrim($protocol . $host . $basePath, '/');
}

/**
 * Check if user has specific access permission
 * Fallback for when FA's check_user_access is not available
 * 
 * @param string $permission Permission to check
 * @return bool True if user has access
 */
function amazon_check_user_access(string $permission): bool
{
    // If FA function exists, use it
    if (function_exists('check_user_access')) {
        return check_user_access($permission);
    }
    
    // Fallback - check session or assume access for development
    if (isset($_SESSION['wa_current_user'])) {
        return true; // In production, implement proper permission checking
    }
    
    return false;
}

/**
 * Display FA-style notification
 * Fallback for when FA's display_notification is not available
 * 
 * @param string $message Message to display
 * @param string $type Type of notification (success, error, warning)
 */
function amazon_display_notification(string $message, string $type = 'success'): void
{
    if (function_exists('display_notification')) {
        display_notification($message);
        return;
    }
    
    // Fallback - store in session for display
    if (!isset($_SESSION['amazon_notifications'])) {
        $_SESSION['amazon_notifications'] = [];
    }
    
    $_SESSION['amazon_notifications'][] = [
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    ];
}

/**
 * Display FA-style error
 * Fallback for when FA's display_error is not available
 * 
 * @param string $message Error message to display
 */
function amazon_display_error(string $message): void
{
    if (function_exists('display_error')) {
        display_error($message);
        return;
    }
    
    amazon_display_notification($message, 'error');
}

/**
 * Get stored notifications for display
 * 
 * @return array Array of notifications
 */
function amazon_get_notifications(): array
{
    $notifications = $_SESSION['amazon_notifications'] ?? [];
    $_SESSION['amazon_notifications'] = []; // Clear after getting
    
    return $notifications;
}

/**
 * Sanitize input for database/display
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function amazon_sanitize_input(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency amount
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @return string Formatted amount
 */
function amazon_format_currency(float $amount, string $currency = 'USD'): string
{
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CAD' => 'C$',
        'JPY' => '¥'
    ];
    
    $symbol = $symbols[$currency] ?? $currency . ' ';
    
    return $symbol . number_format($amount, 2);
}

/**
 * Get marketplace name from ID
 * 
 * @param string $marketplaceId Marketplace identifier
 * @return string Marketplace name
 */
function amazon_get_marketplace_name(string $marketplaceId): string
{
    $marketplaces = [
        'ATVPDKIKX0DER' => 'Amazon US',
        'A2EUQ1WTGCTBG2' => 'Amazon CA',
        'A1AM78C64UM0Y8' => 'Amazon MX',
        'A2VIGQ35RCS4UG' => 'Amazon AE',
        'A1PA6795UKMFR9' => 'Amazon DE',
        'A1RKKUPIHCS9HS' => 'Amazon ES',
        'A13V1IB3VIYZZH' => 'Amazon FR',
        'A21TJRUUN4KGV' => 'Amazon IN',
        'APJ6JRA9NG5V4' => 'Amazon IT',
        'A1F83G8C2ARO7P' => 'Amazon UK',
        'A1805IZSGTT6HS' => 'Amazon NL',
        'A2Q3Y263D00KWC' => 'Amazon BR',
        'A39IBJ37TRP1C6' => 'Amazon AU',
        'A17E79C6D8DWNP' => 'Amazon SA',
        'AAHKV2X7AFYLW' => 'Amazon SG',
        'A19VAU5U5O7RUS' => 'Amazon FE'
    ];
    
    return $marketplaces[$marketplaceId] ?? "Unknown ($marketplaceId)";
}

/**
 * Validate email address format
 * 
 * @param string $email Email to validate
 * @return bool True if valid
 */
function amazon_validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate a secure random string
 * 
 * @param int $length Length of string to generate
 * @return string Random string
 */
function amazon_generate_random_string(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Log activity for debugging/audit
 * 
 * @param string $action Action performed
 * @param string $details Additional details
 * @param string $level Log level (info, warning, error)
 */
function amazon_log_activity(string $action, string $details = '', string $level = 'info'): void
{
    // In production, integrate with FA's logging or use file logging
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'details' => $details,
        'level' => $level,
        'user' => $_SESSION['wa_current_user']->user ?? 'system',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // For now, store in session for debugging
    if (!isset($_SESSION['amazon_debug_log'])) {
        $_SESSION['amazon_debug_log'] = [];
    }
    
    $_SESSION['amazon_debug_log'][] = $logEntry;
    
    // Keep only last 100 entries
    if (count($_SESSION['amazon_debug_log']) > 100) {
        $_SESSION['amazon_debug_log'] = array_slice($_SESSION['amazon_debug_log'], -100);
    }
}

/**
 * Check if we're in development mode
 * 
 * @return bool True if in development
 */
function amazon_is_development(): bool
{
    // Check for development indicators
    $devIndicators = [
        $_SERVER['HTTP_HOST'] === 'localhost',
        strpos($_SERVER['HTTP_HOST'], '.local') !== false,
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false,
        defined('AMAZON_INVOICES_DEBUG') && AMAZON_INVOICES_DEBUG
    ];
    
    return in_array(true, $devIndicators, true);
}
?>
