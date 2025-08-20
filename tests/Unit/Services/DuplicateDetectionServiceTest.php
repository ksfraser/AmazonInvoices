<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use AmazonInvoices\Services\DuplicateDetectionService;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

class DuplicateDetectionServiceTest extends TestCase
{
    private DuplicateDetectionService $service;
    private MockObject $database;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->database = $this->createMock(DatabaseRepositoryInterface::class);
        $this->service = new DuplicateDetectionService($this->database);
    }

    public function testExactOrderNumberMatch(): void
    {
        // Arrange
        $invoiceData = [
            'order_number' => 'AMZ123456',
            'invoice_total' => 99.99,
            'invoice_date' => '2025-08-01'
        ];

        $existingInvoice = [
            'id' => 1,
            'order_number' => 'AMZ123456',
            'invoice_total' => 99.99,
            'source_type' => 'email'
        ];

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('order_number'))
            ->willReturn('result');

        $this->database->expects($this->once())
            ->method('fetch')
            ->willReturn($existingInvoice);

        // Act
        $result = $this->service->findDuplicateInvoice($invoiceData);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(100, $result['confidence']);
        $this->assertEquals('exact_order_number', $result['match_type']);
        $this->assertEquals($existingInvoice, $result['existing_invoice']);
    }

    public function testExactInvoiceNumberMatch(): void
    {
        // Arrange
        $invoiceData = [
            'invoice_number' => 'INV-789',
            'invoice_total' => 149.99,
            'invoice_date' => '2025-08-01'
        ];

        // No order number match
        $this->database->expects($this->at(0))
            ->method('fetch')
            ->willReturn(null);

        // Invoice number match
        $existingInvoice = [
            'id' => 2,
            'invoice_number' => 'INV-789',
            'invoice_total' => 149.99
        ];

        $this->database->expects($this->at(2))
            ->method('fetch')
            ->willReturn($existingInvoice);

        // Act
        $result = $this->service->findDuplicateInvoice($invoiceData);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(95, $result['confidence']);
        $this->assertEquals('exact_invoice_number', $result['match_type']);
    }

    public function testDateTotalAddressMatch(): void
    {
        // Arrange
        $invoiceData = [
            'invoice_date' => '2025-08-01',
            'invoice_total' => 75.50,
            'billing_address' => '123 Main St, City, State'
        ];

        // No exact matches
        $this->database->expects($this->exactly(2))
            ->method('fetch')
            ->willReturn(null);

        // Date/total/address match
        $existingInvoice = [
            'id' => 3,
            'invoice_date' => '2025-08-01',
            'invoice_total' => 75.50,
            'billing_address' => '123 Main St, City, State'
        ];

        $this->database->expects($this->at(4))
            ->method('fetch')
            ->willReturn($existingInvoice);

        // Act
        $result = $this->service->findDuplicateInvoice($invoiceData);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(85, $result['confidence']);
        $this->assertEquals('date_total_address', $result['match_type']);
    }

    public function testItemSimilarityMatch(): void
    {
        // Arrange
        $invoiceData = [
            'invoice_total' => 199.99,
            'items' => [
                ['product_name' => 'Laptop Computer', 'quantity' => 1, 'unit_price' => 199.99]
            ]
        ];

        // No exact matches
        $this->database->expects($this->exactly(3))
            ->method('fetch')
            ->willReturn(null);

        // Item similarity match
        $this->database->expects($this->at(6))
            ->method('query')
            ->with($this->stringContains('amazon_invoice_items_staging'))
            ->willReturn('items_result');

        $this->database->expects($this->at(7))
            ->method('fetch')
            ->willReturn([
                'staging_invoice_id' => 4,
                'product_name' => 'Dell Laptop Computer',
                'quantity' => 1,
                'unit_price' => 199.99
            ]);

        $this->database->expects($this->at(8))
            ->method('fetch')
            ->willReturn(null); // End of items

        // Get the matching invoice
        $this->database->expects($this->at(9))
            ->method('query')
            ->with($this->stringContains('amazon_invoices_staging'))
            ->willReturn('invoice_result');

        $this->database->expects($this->at(10))
            ->method('fetch')
            ->willReturn([
                'id' => 4,
                'invoice_total' => 199.99,
                'source_type' => 'pdf'
            ]);

        // Act
        $result = $this->service->findDuplicateInvoice($invoiceData);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(75, $result['confidence']);
        $this->assertEquals('item_similarity', $result['match_type']);
    }

    public function testFuzzyTextMatch(): void
    {
        // Arrange
        $invoiceData = [
            'order_number' => 'ORDER123',
            'invoice_number' => 'INV456',
            'invoice_total' => 50.00
        ];

        // No exact matches
        $this->database->expects($this->exactly(4))
            ->method('fetch')
            ->willReturn(null);

        // Fuzzy matching setup
        $this->database->expects($this->at(8))
            ->method('query')
            ->with($this->stringContains('ORDER'))
            ->willReturn('fuzzy_result');

        $this->database->expects($this->at(9))
            ->method('fetch')
            ->willReturn([
                'id' => 5,
                'order_number' => 'ORDER124', // Similar but not exact
                'invoice_total' => 50.00
            ]);

        $this->database->expects($this->at(10))
            ->method('fetch')
            ->willReturn(null);

        // Act
        $result = $this->service->findDuplicateInvoice($invoiceData);

        // Assert
        $this->assertNotNull($result);
        $this->assertGreaterThan(60, $result['confidence']);
        $this->assertLessThan(80, $result['confidence']);
        $this->assertEquals('fuzzy_text', $result['match_type']);
    }

    public function testNoDuplicatesFound(): void
    {
        // Arrange
        $invoiceData = [
            'order_number' => 'UNIQUE123',
            'invoice_number' => 'UNIQUE456',
            'invoice_total' => 999.99
        ];

        // All queries return no results
        $this->database->expects($this->atLeastOnce())
            ->method('fetch')
            ->willReturn(null);

        // Act
        $result = $this->service->findDuplicateInvoice($invoiceData);

        // Assert
        $this->assertNull($result);
    }

    public function testLogDuplicateDetection(): void
    {
        // Arrange
        $invoiceData = ['order_number' => 'TEST123'];
        $duplicateInfo = [
            'confidence' => 95,
            'match_type' => 'exact_order_number',
            'existing_invoice' => ['id' => 1]
        ];

        $this->database->expects($this->once())
            ->method('escape')
            ->willReturn('TEST123');

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('duplicate_detection_log'))
            ->willReturn(true);

        // Act
        $this->service->logDuplicateDetection($invoiceData, $duplicateInfo, true);

        // Assert - if we get here without exception, the test passes
        $this->assertTrue(true);
    }

    public function testCalculateSimilarityScore(): void
    {
        // Test exact match
        $score = $this->invokePrivateMethod('calculateSimilarityScore', ['test', 'test']);
        $this->assertEquals(1.0, $score);

        // Test completely different strings
        $score = $this->invokePrivateMethod('calculateSimilarityScore', ['abc', 'xyz']);
        $this->assertLessThan(0.5, $score);

        // Test similar strings
        $score = $this->invokePrivateMethod('calculateSimilarityScore', ['Amazon Laptop', 'Amazon Laptop Computer']);
        $this->assertGreaterThan(0.7, $score);
    }

    public function testNormalizeText(): void
    {
        $normalized = $this->invokePrivateMethod('normalizeText', ['  Amazon LAPTOP Computer  ']);
        $this->assertEquals('amazon laptop computer', $normalized);

        $normalized = $this->invokePrivateMethod('normalizeText', ['Test-Item_123']);
        $this->assertEquals('test item 123', $normalized);
    }

    public function testCalculateItemSimilarity(): void
    {
        $items1 = [
            ['product_name' => 'Laptop', 'quantity' => 1, 'unit_price' => 999.99]
        ];

        $items2 = [
            ['product_name' => 'Dell Laptop', 'quantity' => 1, 'unit_price' => 999.99]
        ];

        $similarity = $this->invokePrivateMethod('calculateItemSimilarity', [$items1, $items2]);
        $this->assertGreaterThan(0.5, $similarity);
        $this->assertLessThan(1.0, $similarity);
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod(string $methodName, array $args = [])
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->service, $args);
    }
}
