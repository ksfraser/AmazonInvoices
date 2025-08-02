<?php

/**
 * Simple Test Runner for Amazon Invoices Module
 * 
 * This script runs basic tests without PHPUnit to verify the system works
 * 
 * @package AmazonInvoices\Tests
 * @author  Your Name
 * @since   1.0.0
 */

// Include bootstrap
require_once __DIR__ . '/tests/bootstrap.php';

echo "Amazon Invoices Module Test Runner\n";
echo "==================================\n\n";

// Test 1: Database Connection
echo "1. Testing Database Repository...\n";
try {
    $database = new \AmazonInvoices\Repositories\FrontAccountingDatabaseRepository();
    $prefix = $database->getTablePrefix();
    echo "   ✓ Database repository created successfully\n";
    echo "   ✓ Table prefix: {$prefix}\n";
} catch (Exception $e) {
    echo "   ✗ Database test failed: " . $e->getMessage() . "\n";
}

// Test 2: Model Creation
echo "\n2. Testing Model Creation...\n";
try {
    $invoice = createTestInvoice();
    echo "   ✓ Invoice model created successfully\n";
    echo "   ✓ Invoice number: " . $invoice->getInvoiceNumber() . "\n";
    echo "   ✓ Total amount: $" . $invoice->getTotalAmount() . "\n";
    
    $item = createTestInvoiceItem();
    echo "   ✓ Invoice item created successfully\n";
    echo "   ✓ Product: " . $item->getProductName() . "\n";
    
    $invoice->addItem($item);
    echo "   ✓ Item added to invoice (Count: " . $invoice->getItemCount() . ")\n";
    
    $payment = new \AmazonInvoices\Models\Payment('credit_card', 99.99, 'CC-12345');
    $invoice->addPayment($payment);
    echo "   ✓ Payment added to invoice\n";
    echo "   ✓ Is fully paid: " . ($invoice->isFullyPaid() ? 'Yes' : 'No') . "\n";
    
} catch (Exception $e) {
    echo "   ✗ Model test failed: " . $e->getMessage() . "\n";
}

// Test 3: Validation
echo "\n3. Testing Model Validation...\n";
try {
    $validInvoice = createTestInvoice();
    echo "   ✓ Valid invoice validation: " . ($validInvoice->validate() ? 'PASS' : 'FAIL') . "\n";
    
    $invalidInvoice = new \AmazonInvoices\Models\Invoice('', '', new DateTime(), -100, '');
    echo "   ✓ Invalid invoice validation: " . ($invalidInvoice->validate() ? 'FAIL' : 'PASS') . "\n";
    
    $validItem = createTestInvoiceItem();
    echo "   ✓ Valid item validation: " . ($validItem->validate() ? 'PASS' : 'FAIL') . "\n";
    
    $invalidItem = new \AmazonInvoices\Models\InvoiceItem(0, '', 0, -10, 0);
    echo "   ✓ Invalid item validation: " . ($invalidItem->validate() ? 'FAIL' : 'PASS') . "\n";
    
} catch (Exception $e) {
    echo "   ✗ Validation test failed: " . $e->getMessage() . "\n";
}

// Test 4: Array Conversion
echo "\n4. Testing Array Conversion...\n";
try {
    $invoice = createTestInvoice();
    $array = $invoice->toArray();
    echo "   ✓ Invoice to array conversion successful\n";
    echo "   ✓ Array keys: " . implode(', ', array_keys($array)) . "\n";
    
    $fromArray = \AmazonInvoices\Models\Invoice::fromArray($array);
    echo "   ✓ Invoice from array conversion successful\n";
    echo "   ✓ Original number: " . $invoice->getInvoiceNumber() . "\n";
    echo "   ✓ Converted number: " . $fromArray->getInvoiceNumber() . "\n";
    echo "   ✓ Match: " . ($invoice->getInvoiceNumber() === $fromArray->getInvoiceNumber() ? 'Yes' : 'No') . "\n";
    
} catch (Exception $e) {
    echo "   ✗ Array conversion test failed: " . $e->getMessage() . "\n";
}

// Test 5: Service Creation
echo "\n5. Testing Service Layer...\n";
try {
    $database = new \AmazonInvoices\Repositories\FrontAccountingDatabaseRepository();
    
    $installer = new \AmazonInvoices\Services\DatabaseInstallationService($database);
    echo "   ✓ Database installation service created\n";
    echo "   ✓ Schema version: " . $installer->getSchemaVersion() . "\n";
    
    $downloader = new \AmazonInvoices\Services\AmazonInvoiceDownloader($database, '/tmp/test');
    echo "   ✓ Invoice downloader service created\n";
    
    $matcher = new \AmazonInvoices\Services\ItemMatchingService($database);
    echo "   ✓ Item matching service created\n";
    
    $invoiceRepo = new \AmazonInvoices\Repositories\InvoiceRepository($database);
    echo "   ✓ Invoice repository created\n";
    
} catch (Exception $e) {
    echo "   ✗ Service test failed: " . $e->getMessage() . "\n";
}

// Test 6: Sample Invoice Generation
echo "\n6. Testing Sample Invoice Generation...\n";
try {
    $database = new \AmazonInvoices\Repositories\FrontAccountingDatabaseRepository();
    $downloader = new \AmazonInvoices\Services\AmazonInvoiceDownloader($database, '/tmp/test');
    
    $sampleInvoices = $downloader->downloadInvoices(new DateTime('2025-01-01'), new DateTime('2025-01-31'));
    echo "   ✓ Generated " . count($sampleInvoices) . " sample invoices\n";
    
    if (!empty($sampleInvoices)) {
        $firstInvoice = $sampleInvoices[0];
        echo "   ✓ First invoice: " . $firstInvoice->getInvoiceNumber() . "\n";
        echo "   ✓ Items count: " . $firstInvoice->getItemCount() . "\n";
        echo "   ✓ Payments count: " . count($firstInvoice->getPayments()) . "\n";
        echo "   ✓ Validation: " . ($firstInvoice->validate() ? 'PASS' : 'FAIL') . "\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ Sample generation test failed: " . $e->getMessage() . "\n";
}

// Test 7: Mock Functions
echo "\n7. Testing Mock Functions...\n";
try {
    // Test that our mock functions are working
    echo "   ✓ TB_PREF constant: " . TB_PREF . "\n";
    echo "   ✓ db_escape function: " . db_escape("test'string") . "\n";
    echo "   ✓ Translation function: " . _('Test String') . "\n";
    
    // Test mock transaction
    begin_transaction();
    echo "   ✓ Begin transaction executed\n";
    commit_transaction();
    echo "   ✓ Commit transaction executed\n";
    
} catch (Exception $e) {
    echo "   ✗ Mock function test failed: " . $e->getMessage() . "\n";
}

echo "\n=================\n";
echo "Test run complete!\n";
echo "=================\n\n";

echo "To run PHPUnit tests (requires Composer install):\n";
echo "  composer install\n";
echo "  ./vendor/bin/phpunit\n\n";

echo "To run with coverage:\n";
echo "  ./vendor/bin/phpunit --coverage-html tests/coverage\n\n";
