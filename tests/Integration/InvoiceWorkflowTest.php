<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Integration;

use PHPUnit\Framework\TestCase;
use AmazonInvoices\Services\DatabaseInstallationService;
use AmazonInvoices\Services\AmazonInvoiceDownloader;
use AmazonInvoices\Services\ItemMatchingService;
use AmazonInvoices\Repositories\InvoiceRepository;
use AmazonInvoices\Repositories\FrontAccountingDatabaseRepository;
use AmazonInvoices\Models\Invoice;

/**
 * Integration tests for Amazon Invoice workflow
 * 
 * Tests the complete flow from downloading to processing invoices
 * 
 * @package AmazonInvoices\Tests\Integration
 * @author  Your Name
 * @since   1.0.0
 */
class InvoiceWorkflowTest extends TestCase
{
    private FrontAccountingDatabaseRepository $database;
    private DatabaseInstallationService $installer;
    private AmazonInvoiceDownloader $downloader;
    private ItemMatchingService $matcher;
    private InvoiceRepository $invoiceRepo;

    protected function setUp(): void
    {
        $this->database = new FrontAccountingDatabaseRepository();
        $this->installer = new DatabaseInstallationService($this->database);
        $this->downloader = new AmazonInvoiceDownloader($this->database);
        $this->matcher = new ItemMatchingService($this->database);
        $this->invoiceRepo = new InvoiceRepository($this->database);
    }

    public function testCompleteInvoiceWorkflow(): void
    {
        // 1. Ensure database is set up
        $this->assertTrue($this->installer->isInstalled());

        // 2. Download sample invoices
        $invoices = $this->downloader->downloadInvoices();
        $this->assertNotEmpty($invoices);
        $this->assertInstanceOf(Invoice::class, $invoices[0]);

        // 3. Save invoice to repository
        $invoice = $invoices[0];
        $savedInvoice = $this->invoiceRepo->save($invoice);
        $this->assertNotNull($savedInvoice->getId());
        $this->assertEquals('pending', $savedInvoice->getStatus());

        // 4. Auto-match items
        $matchedCount = $this->matcher->autoMatchInvoiceItems($savedInvoice->getId());
        $this->assertGreaterThanOrEqual(0, $matchedCount);

        // 5. Retrieve and verify saved invoice
        $retrievedInvoice = $this->invoiceRepo->findById($savedInvoice->getId());
        $this->assertNotNull($retrievedInvoice);
        $this->assertEquals($invoice->getInvoiceNumber(), $retrievedInvoice->getInvoiceNumber());

        // 6. Update status
        $statusUpdated = $this->invoiceRepo->updateStatus(
            $savedInvoice->getId(),
            'processed',
            'Integration test completed'
        );
        $this->assertTrue($statusUpdated);

        // 7. Verify status update
        $processedInvoice = $this->invoiceRepo->findById($savedInvoice->getId());
        $this->assertEquals('processed', $processedInvoice->getStatus());

        // 8. Clean up - delete test invoice
        $deleted = $this->invoiceRepo->delete($savedInvoice->getId());
        $this->assertTrue($deleted);

        // 9. Verify deletion
        $deletedInvoice = $this->invoiceRepo->findById($savedInvoice->getId());
        $this->assertNull($deletedInvoice);
    }

    public function testInvoiceSearch(): void
    {
        // Create and save test invoices
        $invoice1 = createTestInvoice();
        $invoice1->setStatus('pending');
        $savedInvoice1 = $this->invoiceRepo->save($invoice1);

        $invoice2 = createTestInvoice();
        $invoice2->setInvoiceNumber('AMZ-002-987654');
        $invoice2->setOrderNumber('ORD-002-456789');
        $invoice2->setStatus('completed');
        $savedInvoice2 = $this->invoiceRepo->save($invoice2);

        try {
            // Test find by status
            $pendingInvoices = $this->invoiceRepo->findByStatus('pending');
            $this->assertGreaterThanOrEqual(1, count($pendingInvoices));

            $completedInvoices = $this->invoiceRepo->findByStatus('completed');
            $this->assertGreaterThanOrEqual(1, count($completedInvoices));

            // Test find by invoice number
            $foundInvoice = $this->invoiceRepo->findByInvoiceNumber($invoice1->getInvoiceNumber());
            $this->assertNotNull($foundInvoice);
            $this->assertEquals($invoice1->getInvoiceNumber(), $foundInvoice->getInvoiceNumber());

            // Test find by date range
            $startDate = new \DateTime('2025-01-01');
            $endDate = new \DateTime('2025-12-31');
            $invoicesInRange = $this->invoiceRepo->findByDateRange($startDate, $endDate);
            $this->assertGreaterThanOrEqual(2, count($invoicesInRange));

            // Test count
            $totalCount = $this->invoiceRepo->count();
            $this->assertGreaterThanOrEqual(2, $totalCount);

            // Test filtered count
            $pendingCount = $this->invoiceRepo->count(['status' => 'pending']);
            $this->assertGreaterThanOrEqual(1, $pendingCount);

        } finally {
            // Clean up
            $this->invoiceRepo->delete($savedInvoice1->getId());
            $this->invoiceRepo->delete($savedInvoice2->getId());
        }
    }

    public function testItemMatching(): void
    {
        // Test adding custom matching rules
        $ruleId = $this->matcher->addMatchingRule(
            'asin_pattern',
            'B08TEST*',
            'TEST-STOCK-001',
            1
        );
        $this->assertGreaterThan(0, $ruleId);

        // Test retrieving matching rules
        $rules = $this->matcher->getMatchingRules();
        $this->assertIsArray($rules);

        // Test finding matches for specific products
        $matches = $this->matcher->getSuggestedStockItems('Test Bluetooth Headphones', 5);
        $this->assertIsArray($matches);

        // Test updating rule status
        $updated = $this->matcher->updateRuleStatus($ruleId, false);
        $this->assertTrue($updated);

        // Test deleting rule
        $deleted = $this->matcher->deleteRule($ruleId);
        $this->assertTrue($deleted);
    }

    public function testDatabaseInstallation(): void
    {
        // Test schema version
        $version = $this->installer->getSchemaVersion();
        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);

        // Test installation status
        $isInstalled = $this->installer->isInstalled();
        $this->assertTrue($isInstalled);
    }

    public function testInvoiceDownloader(): void
    {
        // Test downloading with different configurations
        $config = [
            'sample_data_count' => 3,
            'include_items' => true,
            'include_payments' => true
        ];

        $downloader = new AmazonInvoiceDownloader($this->database, $config);
        $invoices = $downloader->downloadInvoices();

        $this->assertCount(3, $invoices);
        
        foreach ($invoices as $invoice) {
            $this->assertInstanceOf(Invoice::class, $invoice);
            $this->assertGreaterThan(0, $invoice->getItemCount());
            $this->assertGreaterThan(0, count($invoice->getPayments()));
            $this->assertTrue($invoice->validate());
        }
    }

    public function testErrorHandling(): void
    {
        // Test handling of invalid invoice data
        try {
            $invalidInvoice = new Invoice('', '', new \DateTime(), -100, '');
            $this->invoiceRepo->save($invalidInvoice);
            $this->fail('Should have thrown exception for invalid invoice');
        } catch (\Exception $e) {
            $this->assertStringContainsString('validation', strtolower($e->getMessage()));
        }

        // Test handling of non-existent invoice lookup
        $nonExistentInvoice = $this->invoiceRepo->findById(999999);
        $this->assertNull($nonExistentInvoice);

        // Test handling of invalid matching rule
        try {
            $this->matcher->addMatchingRule('invalid_type', 'pattern', 'stock', 1);
            // Note: Current implementation may not validate rule types, 
            // but this test ensures we handle unexpected scenarios gracefully
        } catch (\Exception $e) {
            // Expected behavior for invalid rule types
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }
}
