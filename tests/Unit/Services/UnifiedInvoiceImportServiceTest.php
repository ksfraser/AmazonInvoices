<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use AmazonInvoices\Services\UnifiedInvoiceImportService;
use AmazonInvoices\Services\DuplicateDetectionService;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

class UnifiedInvoiceImportServiceTest extends TestCase
{
    private UnifiedInvoiceImportService $service;
    private MockObject $database;
    private MockObject $duplicateDetector;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->database = $this->createMock(DatabaseRepositoryInterface::class);
        $this->duplicateDetector = $this->createMock(DuplicateDetectionService::class);
        $this->service = new UnifiedInvoiceImportService($this->database, $this->duplicateDetector);
    }

    public function testProcessEmailsSuccess(): void
    {
        // Arrange
        $options = ['max_emails' => 10, 'days_back' => 7];
        $mockResults = [
            [
                'success' => true,
                'subject' => 'Amazon Order Shipped',
                'invoice_data' => [
                    'order_number' => 'AMZ123',
                    'invoice_total' => 99.99
                ]
            ]
        ];

        // Mock the Gmail processor would be called here
        // For this test, we'll assume it returns the expected format

        // Mock duplicate detection
        $this->duplicateDetector->expects($this->once())
            ->method('findDuplicateInvoice')
            ->willReturn(null); // No duplicate found

        // Act
        $results = $this->service->processEmails($options);

        // Assert
        $this->assertIsArray($results);
        // Additional assertions would depend on the actual implementation
    }

    public function testProcessEmailsWithDuplicates(): void
    {
        // Arrange
        $duplicateInfo = [
            'confidence' => 95,
            'match_type' => 'exact_order_number',
            'existing_invoice' => ['id' => 1]
        ];

        $this->duplicateDetector->expects($this->once())
            ->method('findDuplicateInvoice')
            ->willReturn($duplicateInfo);

        // Act
        $results = $this->service->processEmails(['max_emails' => 1]);

        // Assert
        $this->assertIsArray($results);
        // Test would verify duplicate info is included in results
    }

    public function testProcessPdfFileSuccess(): void
    {
        // Arrange
        $filePath = '/path/to/test.pdf';
        $options = ['ocr_engine' => 'tesseract'];

        $this->duplicateDetector->expects($this->once())
            ->method('findDuplicateInvoice')
            ->willReturn(null);

        // Act
        $result = $this->service->processPdfFile($filePath, $options);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('is_duplicate', $result);
    }

    public function testGetProcessingStatistics(): void
    {
        // Arrange
        $daysBack = 30;

        // Mock database queries for statistics
        $this->database->expects($this->exactly(4))
            ->method('query')
            ->willReturn('mock_result');

        $this->database->expects($this->exactly(4))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                ['total_emails' => 100, 'processed_emails' => 95, 'failed_emails' => 5],
                ['total_pdfs' => 50, 'processed_pdfs' => 48, 'failed_pdfs' => 2],
                ['total_invoices' => 120, 'pending_invoices' => 10, 'completed_invoices' => 110, 'import_methods_used' => 2],
                ['total_duplicates' => 15, 'avg_confidence' => 87.5]
            );

        // Act
        $stats = $this->service->getProcessingStatistics($daysBack);

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals($daysBack, $stats['period_days']);
        $this->assertArrayHasKey('email_import', $stats);
        $this->assertArrayHasKey('pdf_import', $stats);
        $this->assertArrayHasKey('invoice_processing', $stats);
        $this->assertArrayHasKey('duplicate_detection', $stats);

        // Test calculated success rates
        $this->assertEquals(95.0, $stats['email_import']['success_rate']);
        $this->assertEquals(96.0, $stats['pdf_import']['success_rate']);
    }

    public function testGetPendingInvoices(): void
    {
        // Arrange
        $limit = 10;
        $mockInvoices = [
            [
                'id' => 1,
                'order_number' => 'AMZ123',
                'invoice_total' => 99.99,
                'item_count' => 2,
                'items_total' => 99.99
            ],
            [
                'id' => 2,
                'order_number' => 'AMZ456',
                'invoice_total' => 149.99,
                'item_count' => 1,
                'items_total' => 149.99
            ]
        ];

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('amazon_invoices_staging'))
            ->willReturn('mock_result');

        $this->database->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                $mockInvoices[0],
                $mockInvoices[1],
                null // End of results
            );

        // Act
        $invoices = $this->service->getPendingInvoices($limit);

        // Assert
        $this->assertIsArray($invoices);
        $this->assertCount(2, $invoices);
        $this->assertEquals('AMZ123', $invoices[0]['order_number']);
        $this->assertEquals('AMZ456', $invoices[1]['order_number']);
    }

    public function testMarkInvoiceAsProcessed(): void
    {
        // Arrange
        $invoiceId = 123;
        $faInvoiceNumber = 'FA-INV-456';

        $this->database->expects($this->once())
            ->method('escape')
            ->with($faInvoiceNumber)
            ->willReturn($faInvoiceNumber);

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('UPDATE'))
            ->willReturn(true);

        // Act
        $result = $this->service->markInvoiceAsProcessed($invoiceId, $faInvoiceNumber);

        // Assert
        $this->assertTrue($result);
    }

    public function testGetInvoiceDetails(): void
    {
        // Arrange
        $invoiceId = 123;
        $mockInvoice = [
            'id' => 123,
            'order_number' => 'AMZ789',
            'invoice_total' => 199.99
        ];
        $mockItems = [
            ['id' => 1, 'product_name' => 'Item 1', 'quantity' => 1],
            ['id' => 2, 'product_name' => 'Item 2', 'quantity' => 2]
        ];
        $mockPayments = [
            ['id' => 1, 'payment_method' => 'Credit Card', 'amount' => 199.99]
        ];

        // Mock invoice query
        $this->database->expects($this->at(0))
            ->method('query')
            ->willReturn('invoice_result');
        $this->database->expects($this->at(1))
            ->method('fetch')
            ->willReturn($mockInvoice);

        // Mock items query
        $this->database->expects($this->at(2))
            ->method('query')
            ->willReturn('items_result');
        $this->database->expects($this->at(3))
            ->method('fetch')
            ->willReturn($mockItems[0]);
        $this->database->expects($this->at(4))
            ->method('fetch')
            ->willReturn($mockItems[1]);
        $this->database->expects($this->at(5))
            ->method('fetch')
            ->willReturn(null);

        // Mock payments query
        $this->database->expects($this->at(6))
            ->method('query')
            ->willReturn('payments_result');
        $this->database->expects($this->at(7))
            ->method('fetch')
            ->willReturn($mockPayments[0]);
        $this->database->expects($this->at(8))
            ->method('fetch')
            ->willReturn(null);

        // Act
        $details = $this->service->getInvoiceDetails($invoiceId);

        // Assert
        $this->assertNotNull($details);
        $this->assertEquals(123, $details['id']);
        $this->assertCount(2, $details['items']);
        $this->assertCount(1, $details['payments']);
    }

    public function testGetInvoiceDetailsNotFound(): void
    {
        // Arrange
        $invoiceId = 999;

        $this->database->expects($this->once())
            ->method('query')
            ->willReturn('result');
        $this->database->expects($this->once())
            ->method('fetch')
            ->willReturn(null);

        // Act
        $details = $this->service->getInvoiceDetails($invoiceId);

        // Assert
        $this->assertNull($details);
    }

    public function testCleanupOldRecords(): void
    {
        // Arrange
        $daysToKeep = 90;

        $this->database->expects($this->exactly(4))
            ->method('query')
            ->willReturn(true);

        $this->database->expects($this->exactly(4))
            ->method('getAffectedRows')
            ->willReturnOnConsecutiveCalls(5, 3, 2, 1);

        // Act
        $cleaned = $this->service->cleanupOldRecords($daysToKeep);

        // Assert
        $this->assertIsArray($cleaned);
        $this->assertEquals(5, $cleaned['invoices']);
        $this->assertEquals(3, $cleaned['email_logs']);
        $this->assertEquals(2, $cleaned['pdf_logs']);
        $this->assertEquals(1, $cleaned['duplicate_logs']);
    }

    public function testGetRecentActivity(): void
    {
        // Arrange
        $limit = 10;
        $mockEmailActivity = [
            [
                'source_type' => 'email',
                'source_id' => 'msg123',
                'description' => 'Order Shipped Email',
                'status' => 'processed',
                'created_at' => '2025-08-04 10:00:00'
            ]
        ];
        $mockPdfActivity = [
            [
                'source_type' => 'pdf',
                'source_id' => '/path/invoice.pdf',
                'description' => 'invoice.pdf',
                'status' => 'processed',
                'created_at' => '2025-08-04 09:00:00'
            ]
        ];

        // Mock email activity query
        $this->database->expects($this->at(0))
            ->method('query')
            ->willReturn('email_result');
        $this->database->expects($this->at(1))
            ->method('fetch')
            ->willReturn($mockEmailActivity[0]);
        $this->database->expects($this->at(2))
            ->method('fetch')
            ->willReturn(null);

        // Mock PDF activity query
        $this->database->expects($this->at(3))
            ->method('query')
            ->willReturn('pdf_result');
        $this->database->expects($this->at(4))
            ->method('fetch')
            ->willReturn($mockPdfActivity[0]);
        $this->database->expects($this->at(5))
            ->method('fetch')
            ->willReturn(null);

        // Act
        $activity = $this->service->getRecentActivity($limit);

        // Assert
        $this->assertIsArray($activity);
        $this->assertLessThanOrEqual($limit, count($activity));
        // Should be sorted by date descending
        if (count($activity) > 1) {
            $this->assertGreaterThanOrEqual(
                strtotime($activity[1]['created_at']),
                strtotime($activity[0]['created_at'])
            );
        }
    }

    private function calculateSuccessRate(int $successful, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($successful / $total) * 100, 2);
    }
}
