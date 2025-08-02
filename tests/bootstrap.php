<?php

/**
 * PHPUnit Bootstrap File
 * 
 * Sets up testing environment for Amazon Invoices module
 * 
 * @package AmazonInvoices\Tests
 * @author  Your Name
 * @since   1.0.0
 */

// Set timezone for consistent testing
date_default_timezone_set('UTC');

// Define project root
define('PROJECT_ROOT', dirname(__DIR__));

// Include our mock functions (always loads in test environment)
require_once PROJECT_ROOT . '/src/Support/FrontAccountingMock.php';

// Autoloader for our classes
spl_autoload_register(function ($class) {
    // Handle our classes
    if (strpos($class, 'AmazonInvoices\\') === 0) {
        $path = PROJECT_ROOT . '/src/' . str_replace(['AmazonInvoices\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
    
    // Handle test classes
    if (strpos($class, 'AmazonInvoices\\Tests\\') === 0) {
        $path = PROJECT_ROOT . '/tests/' . str_replace(['AmazonInvoices\\Tests\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

// Test helper functions
if (!function_exists('createMockDatabase')) {
    /**
     * Create a mock database repository for testing
     * 
     * @return \AmazonInvoices\Repositories\FrontAccountingDatabaseRepository
     */
    function createMockDatabase() {
        return new \AmazonInvoices\Repositories\FrontAccountingDatabaseRepository();
    }
}

if (!function_exists('createTestInvoice')) {
    /**
     * Create a test invoice for testing
     * 
     * @return \AmazonInvoices\Models\Invoice
     */
    function createTestInvoice() {
        return new \AmazonInvoices\Models\Invoice(
            'AMZ-001-123456',
            'ORD-001-789012',
            new DateTime('2025-01-15'),
            99.99,
            'USD'
        );
    }
}

if (!function_exists('createTestInvoiceItem')) {
    /**
     * Create a test invoice item for testing
     * 
     * @return \AmazonInvoices\Models\InvoiceItem
     */
    function createTestInvoiceItem() {
        return new \AmazonInvoices\Models\InvoiceItem(
            1,
            'Test Product - Wireless Bluetooth Headphones',
            2,
            24.99,
            49.98
        );
    }
}
