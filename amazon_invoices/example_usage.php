<?php
/**
 * Amazon Invoices Module - Example Usage
 * This file demonstrates how to use the module programmatically
 */

// This would normally be included automatically by FrontAccounting
require_once 'includes/db_functions.php';
require_once 'includes/amazon_downloader.php';

/**
 * Example: Download and process Amazon invoices
 */
function example_download_and_process() {
    
    // 1. Initialize downloader with settings
    $downloader = new AmazonInvoiceDownloader(
        'your-amazon-email@example.com',
        'your-amazon-password',
        '/path/to/download/folder/'
    );
    
    // 2. Download invoices for date range
    $start_date = '2024-01-01';
    $end_date = '2024-01-31';
    
    echo "Downloading Amazon invoices from {$start_date} to {$end_date}...\n";
    $invoice_ids = $downloader->download_invoices($start_date, $end_date);
    
    if (!$invoice_ids) {
        echo "No invoices downloaded.\n";
        return;
    }
    
    echo "Downloaded " . count($invoice_ids) . " invoices.\n";
    
    // 3. Process each invoice
    foreach ($invoice_ids as $staging_id) {
        process_single_invoice($downloader, $staging_id);
    }
}

/**
 * Process a single invoice through the complete workflow
 */
function process_single_invoice($downloader, $staging_id) {
    
    $invoice = get_amazon_invoice_staging($staging_id);
    echo "\nProcessing invoice: {$invoice['invoice_number']}\n";
    
    // 1. Auto-match items
    echo "  Auto-matching items...\n";
    $matched_count = $downloader->auto_match_items($staging_id);
    echo "  Auto-matched {$matched_count} items\n";
    
    // 2. Check if manual matching needed
    $items = get_amazon_invoice_items_staging($staging_id);
    $unmatched_items = array();
    
    while ($item = db_fetch($items)) {
        if (!$item['fa_item_matched']) {
            $unmatched_items[] = $item;
        }
    }
    
    if (!empty($unmatched_items)) {
        echo "  Manual matching required for " . count($unmatched_items) . " items:\n";
        foreach ($unmatched_items as $item) {
            echo "    - {$item['product_name']} (ASIN: {$item['asin']})\n";
            
            // In a real implementation, you might:
            // - Present a UI for manual matching
            // - Use more sophisticated matching algorithms
            // - Create new stock items automatically
            
            // For demo, we'll create a placeholder match
            $suggested_stock_id = suggest_stock_item_for_amazon_product($item);
            if ($suggested_stock_id) {
                update_amazon_item_matching($item['id'], $suggested_stock_id, 'auto');
                echo "      → Matched to stock item: {$suggested_stock_id}\n";
            }
        }
    }
    
    // 3. Allocate payments
    echo "  Allocating payments...\n";
    $payments = get_amazon_payment_staging($staging_id);
    
    while ($payment = db_fetch($payments)) {
        if (!$payment['allocation_complete']) {
            // Map payment method to bank account
            $bank_account = get_bank_account_for_payment_method($payment['payment_method']);
            $payment_type = get_default_payment_type();
            
            update_amazon_payment_allocation($payment['id'], $bank_account, $payment_type);
            echo "    Allocated {$payment['payment_method']} payment to bank account {$bank_account}\n";
        }
    }
    
    // 4. Validate before processing
    echo "  Validating invoice...\n";
    $validation_errors = $downloader->validate_invoice_data($staging_id);
    
    if (!empty($validation_errors)) {
        echo "  Validation errors:\n";
        foreach ($validation_errors as $error) {
            echo "    - {$error}\n";
        }
        update_amazon_invoice_staging_status($staging_id, 'error', implode('; ', $validation_errors));
        return false;
    }
    
    // 5. Process to FrontAccounting
    echo "  Processing to FrontAccounting...\n";
    $success = process_amazon_invoice_to_fa($staging_id);
    
    if ($success) {
        update_amazon_invoice_staging_status($staging_id, 'completed');
        echo "  ✓ Successfully processed invoice {$invoice['invoice_number']}\n";
    } else {
        update_amazon_invoice_staging_status($staging_id, 'error', 'Failed to process to FrontAccounting');
        echo "  ✗ Failed to process invoice {$invoice['invoice_number']}\n";
    }
    
    return $success;
}

/**
 * Example: Set up matching rules
 */
function example_setup_matching_rules() {
    
    echo "Setting up item matching rules...\n";
    
    // Rule 1: Match by ASIN
    add_amazon_matching_rule('asin', 'B08N5WRWNW', 'LAPTOP001', 1);
    echo "  Added ASIN rule: B08N5WRWNW → LAPTOP001\n";
    
    // Rule 2: Match by SKU
    add_amazon_matching_rule('sku', 'ABC-123', 'WIDGET001', 1);
    echo "  Added SKU rule: ABC-123 → WIDGET001\n";
    
    // Rule 3: Match by keyword
    add_amazon_matching_rule('keyword', 'wireless mouse', 'MOUSE001', 2);
    echo "  Added keyword rule: 'wireless mouse' → MOUSE001\n";
    
    // Rule 4: Match by exact product name
    add_amazon_matching_rule('product_name', 'Amazon Basics USB-C to USB-A Cable', 'CABLE001', 1);
    echo "  Added product name rule: exact match → CABLE001\n";
    
    echo "Matching rules setup complete.\n";
}

/**
 * Example: Generate reports
 */
function example_generate_reports() {
    
    echo "Generating Amazon invoice reports...\n";
    
    // Monthly summary
    $start_date = '2024-01-01';
    $end_date = '2024-01-31';
    
    $sql = "SELECT 
                COUNT(*) as invoice_count,
                SUM(invoice_total) as total_amount,
                AVG(invoice_total) as avg_amount,
                status
            FROM ".TB_PREF."amazon_invoices_staging 
            WHERE invoice_date BETWEEN '{$start_date}' AND '{$end_date}'
            GROUP BY status";
    
    $result = db_query($sql, "Failed to generate report");
    
    echo "\nMonthly Summary ({$start_date} to {$end_date}):\n";
    echo str_repeat('-', 50) . "\n";
    
    while ($row = db_fetch($result)) {
        echo sprintf("  %-12s: %3d invoices, $%8.2f total, $%6.2f avg\n",
            ucfirst($row['status']),
            $row['invoice_count'],
            $row['total_amount'],
            $row['avg_amount']
        );
    }
    
    // Top unmatched items
    echo "\nTop Unmatched Items:\n";
    echo str_repeat('-', 50) . "\n";
    
    $sql = "SELECT 
                product_name,
                COUNT(*) as frequency,
                SUM(total_price) as total_value
            FROM ".TB_PREF."amazon_invoice_items_staging 
            WHERE fa_item_matched = 0
            GROUP BY product_name
            ORDER BY frequency DESC, total_value DESC
            LIMIT 10";
    
    $result = db_query($sql, "Failed to get unmatched items");
    
    while ($row = db_fetch($result)) {
        echo sprintf("  %dx %-30s ($%.2f)\n",
            $row['frequency'],
            substr($row['product_name'], 0, 30),
            $row['total_value']
        );
    }
}

/**
 * Helper function to suggest stock item for Amazon product
 */
function suggest_stock_item_for_amazon_product($item) {
    
    // Simple suggestion based on product name keywords
    $product_name = strtolower($item['product_name']);
    
    if (strpos($product_name, 'laptop') !== false) return 'LAPTOP001';
    if (strpos($product_name, 'mouse') !== false) return 'MOUSE001';
    if (strpos($product_name, 'cable') !== false) return 'CABLE001';
    if (strpos($product_name, 'keyboard') !== false) return 'KEYBOARD001';
    
    // Default to a generic item
    return 'MISC001';
}

/**
 * Helper function to get bank account for payment method
 */
function get_bank_account_for_payment_method($payment_method) {
    
    switch ($payment_method) {
        case 'credit_card':
            return 1; // Main credit card account
        case 'bank_transfer':
            return 2; // Main bank account
        case 'paypal':
            return 3; // PayPal account
        default:
            return 1; // Default account
    }
}

/**
 * Helper function to get default payment type
 */
function get_default_payment_type() {
    return 1; // Default payment terms
}

// Example usage (commented out for safety)
/*
if (php_sapi_name() === 'cli') {
    echo "Amazon Invoices Module - Example Usage\n";
    echo "=====================================\n\n";
    
    // Set up matching rules first
    example_setup_matching_rules();
    echo "\n";
    
    // Download and process invoices
    example_download_and_process();
    echo "\n";
    
    // Generate reports
    example_generate_reports();
    echo "\n";
    
    echo "Example completed successfully!\n";
}
*/
?>
