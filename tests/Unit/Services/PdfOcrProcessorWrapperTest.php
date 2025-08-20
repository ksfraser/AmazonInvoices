<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use AmazonInvoices\Services\PdfOcrProcessorWrapper;
use AmazonInvoices\Services\DuplicateDetectionService;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

class PdfOcrProcessorWrapperTest extends TestCase
{
    private PdfOcrProcessorWrapper $wrapper;
    private MockObject $database;
    private MockObject $duplicateDetector;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->database = $this->createMock(DatabaseRepositoryInterface::class);
        $this->duplicateDetector = $this->createMock(DuplicateDetectionService::class);
        
        // Create temporary directories for testing
        $this->tempDir = sys_get_temp_dir() . '/amazon_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        $this->wrapper = new PdfOcrProcessorWrapper(
            $this->database,
            $this->duplicateDetector,
            $this->tempDir . '/uploads/',
            $this->tempDir . '/temp/'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up temporary directory
        $this->removeDirectory($this->tempDir);
    }

    public function testFileExists(): void
    {
        // Arrange
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, 'test content');

        // Act & Assert
        $this->assertTrue($this->wrapper->fileExists($testFile));
        $this->assertFalse($this->wrapper->fileExists('/nonexistent/file.txt'));
    }

    public function testReadWriteFile(): void
    {
        // Arrange
        $testFile = $this->tempDir . '/test.txt';
        $content = 'Test file content';

        // Act
        $writeResult = $this->wrapper->writeFile($testFile, $content);
        $readContent = $this->wrapper->readFile($testFile);

        // Assert
        $this->assertTrue($writeResult);
        $this->assertEquals($content, $readContent);
    }

    public function testGetFileSize(): void
    {
        // Arrange
        $testFile = $this->tempDir . '/test.txt';
        $content = 'Test content';
        file_put_contents($testFile, $content);

        // Act
        $size = $this->wrapper->getFileSize($testFile);

        // Assert
        $this->assertEquals(strlen($content), $size);
    }

    public function testCopyFile(): void
    {
        // Arrange
        $source = $this->tempDir . '/source.txt';
        $destination = $this->tempDir . '/destination.txt';
        file_put_contents($source, 'test content');

        // Act
        $result = $this->wrapper->copyFile($source, $destination);

        // Assert
        $this->assertTrue($result);
        $this->assertTrue(file_exists($destination));
        $this->assertEquals('test content', file_get_contents($destination));
    }

    public function testMoveFile(): void
    {
        // Arrange
        $source = $this->tempDir . '/source.txt';
        $destination = $this->tempDir . '/destination.txt';
        file_put_contents($source, 'test content');

        // Act
        $result = $this->wrapper->moveFile($source, $destination);

        // Assert
        $this->assertTrue($result);
        $this->assertFalse(file_exists($source));
        $this->assertTrue(file_exists($destination));
    }

    public function testCreateTempFile(): void
    {
        // Act
        $tempFile = $this->wrapper->createTempFile('test_');

        // Assert
        $this->assertStringContains('test_', basename($tempFile));
        $this->assertTrue(file_exists($tempFile));
        
        // Clean up
        unlink($tempFile);
    }

    public function testListDirectory(): void
    {
        // Arrange
        $testDir = $this->tempDir . '/list_test/';
        mkdir($testDir, 0755, true);
        file_put_contents($testDir . 'file1.pdf', 'content');
        file_put_contents($testDir . 'file2.txt', 'content');
        file_put_contents($testDir . 'file3.pdf', 'content');

        // Act
        $allFiles = $this->wrapper->listDirectory($testDir);
        $pdfFiles = $this->wrapper->listDirectory($testDir, 'pdf');

        // Assert
        $this->assertCount(3, $allFiles);
        $this->assertCount(2, $pdfFiles);
    }

    public function testStorePdfLog(): void
    {
        // Arrange
        $pdfData = [
            'file_path' => '/path/to/invoice.pdf',
            'file_name' => 'invoice.pdf',
            'file_hash' => 'abc123',
            'status' => 'processed'
        ];

        $this->database->expects($this->exactly(4))
            ->method('escape')
            ->willReturnArgument(0);

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('INSERT INTO'))
            ->willReturn(true);

        $this->database->expects($this->once())
            ->method('getLastInsertId')
            ->willReturn(789);

        // Act
        $result = $this->wrapper->storePdfLog($pdfData);

        // Assert
        $this->assertEquals(789, $result);
    }

    public function testIsPdfProcessed(): void
    {
        // Arrange
        $testFile = $this->tempDir . '/test.pdf';
        file_put_contents($testFile, 'PDF content');

        $this->database->expects($this->once())
            ->method('escape')
            ->willReturnArgument(0);

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('file_hash'))
            ->willReturn('result');

        $this->database->expects($this->once())
            ->method('fetch')
            ->willReturn(['id' => 1]);

        // Act
        $result = $this->wrapper->isPdfProcessed($testFile);

        // Assert
        $this->assertTrue($result);
    }

    public function testStoreInvoice(): void
    {
        // Arrange
        $invoiceData = [
            'invoice_number' => 'PDF-INV-123',
            'order_number' => 'PDF-ORD-456',
            'invoice_date' => '2025-08-04',
            'invoice_total' => 199.99,
            'source_file' => '/path/to/invoice.pdf',
            'items' => [
                [
                    'product_name' => 'PDF Test Item',
                    'quantity' => 1,
                    'unit_price' => 199.99,
                    'total_price' => 199.99
                ]
            ]
        ];

        $this->database->expects($this->atLeastOnce())
            ->method('escape')
            ->willReturnArgument(0);

        $this->database->expects($this->exactly(2)) // Invoice + Items
            ->method('query')
            ->willReturn(true);

        $this->database->expects($this->once())
            ->method('getLastInsertId')
            ->willReturn(987);

        // Act
        $result = $this->wrapper->storeInvoice($invoiceData);

        // Assert
        $this->assertEquals(987, $result);
    }

    public function testParseInvoiceText(): void
    {
        // Arrange
        $invoiceText = "Order #AMZ987654\nInvoice #PDF-INV-321\nTotal $299.99\nTax $24.99\nShipping $9.99\n08/04/2025\nBilling Address: 123 Test St, Test City, TS 12345";

        // Act
        $result = $this->wrapper->parseInvoiceText($invoiceText);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('AMZ987654', $result['order_number']);
        $this->assertEquals('PDF-INV-321', $result['invoice_number']);
        $this->assertEquals(299.99, $result['invoice_total']);
        $this->assertEquals(24.99, $result['tax_amount']);
        $this->assertEquals(9.99, $result['shipping_amount']);
        $this->assertEquals('2025-08-04', $result['invoice_date']);
        $this->assertStringContains('123 Test St', $result['billing_address']);
    }

    public function testParseInvoiceTextNoData(): void
    {
        // Arrange
        $invoiceText = "This is just random text with no invoice information.";

        // Act
        $result = $this->wrapper->parseInvoiceText($invoiceText);

        // Assert
        $this->assertNull($result);
    }

    public function testExtractItems(): void
    {
        // Arrange
        $invoiceText = "2 x Gaming Laptop $899.99\n1 x Wireless Mouse (Qty: 1) $29.99\nKeyboard - $79.99 (x 1)";

        // Act
        $result = $this->wrapper->extractItems($invoiceText);

        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals(2, $result[0]['quantity']);
        $this->assertEquals('Gaming Laptop', $result[0]['product_name']);
        $this->assertEquals(899.99, $result[0]['unit_price']);
        $this->assertEquals(1799.98, $result[0]['total_price']);
    }

    public function testExtractBillingAddress(): void
    {
        // Arrange
        $invoiceText = "Billing Address: John Doe\n123 Main Street\nAnytown, State 12345\nUSA\n\nOther content here";

        // Act
        $result = $this->wrapper->extractBillingAddress($invoiceText);

        // Assert
        $this->assertNotNull($result);
        $this->assertStringContains('John Doe', $result);
        $this->assertStringContains('123 Main Street', $result);
    }

    public function testProcessUploadedFiles(): void
    {
        // Arrange
        $uploadedFiles = [
            [
                'name' => 'invoice1.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $this->createMockPdfFile(),
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ],
            [
                'name' => 'invalid.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/invalid',
                'error' => UPLOAD_ERR_OK,
                'size' => 512
            ]
        ];

        // Act
        $results = $this->wrapper->processUploadedFiles($uploadedFiles);

        // Assert
        $this->assertCount(2, $results);
        $this->assertFalse($results[1]['success']); // Invalid file type
        $this->assertEquals('Invalid PDF file', $results[1]['error']);
    }

    public function testFindDuplicateInvoice(): void
    {
        // Arrange
        $invoiceData = ['order_number' => 'PDF123'];
        $duplicateInfo = ['confidence' => 90, 'match_type' => 'exact_order_number'];

        $this->duplicateDetector->expects($this->once())
            ->method('findDuplicateInvoice')
            ->with($invoiceData)
            ->willReturn($duplicateInfo);

        // Act
        $result = $this->wrapper->findDuplicateInvoice($invoiceData);

        // Assert
        $this->assertEquals($duplicateInfo, $result);
    }

    /**
     * Test private methods using reflection
     */
    public function testGenerateUniqueFileName(): void
    {
        $reflection = new \ReflectionClass($this->wrapper);
        $method = $reflection->getMethod('generateUniqueFileName');
        $method->setAccessible(true);

        $originalName = 'test invoice.pdf';
        $uniqueName = $method->invoke($this->wrapper, $originalName);

        $this->assertStringContains('test_invoice', $uniqueName);
        $this->assertStringContains('.pdf', $uniqueName);
        $this->assertStringContains(date('Ymd'), $uniqueName);
    }

    public function testIsValidPdfFile(): void
    {
        $reflection = new \ReflectionClass($this->wrapper);
        $method = $reflection->getMethod('isValidPdfFile');
        $method->setAccessible(true);

        // Create a mock PDF file
        $pdfFile = $this->createMockPdfFile();
        
        $this->assertTrue($method->invoke($this->wrapper, $pdfFile, 'application/pdf'));
        $this->assertFalse($method->invoke($this->wrapper, $pdfFile, 'text/plain'));
        
        unlink($pdfFile);
    }

    private function createMockPdfFile(): string
    {
        $pdfFile = $this->tempDir . '/mock.pdf';
        file_put_contents($pdfFile, '%PDF-1.4 Mock PDF content');
        return $pdfFile;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
