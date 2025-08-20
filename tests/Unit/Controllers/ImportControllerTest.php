<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use AmazonInvoices\Controllers\ImportController;

class ImportControllerTest extends TestCase
{
    private ImportController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock global variables and functions for testing
        $_GET = [];
        $_POST = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        
        $this->controller = new ImportController();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up global state
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testHandleRequestDashboard(): void
    {
        // Arrange
        $_GET['action'] = 'dashboard';

        // Act
        ob_start();
        $this->controller->handleRequest();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
    }

    public function testHandleRequestEmails(): void
    {
        // Arrange
        $_GET['action'] = 'emails';

        // Act
        ob_start();
        $this->controller->handleRequest();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
    }

    public function testHandleRequestUpload(): void
    {
        // Arrange
        $_GET['action'] = 'upload';

        // Act
        ob_start();
        $this->controller->handleRequest();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
    }

    public function testHandleRequestDirectory(): void
    {
        // Arrange
        $_GET['action'] = 'directory';

        // Act
        ob_start();
        $this->controller->handleRequest();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
    }

    public function testHandleRequestReview(): void
    {
        // Arrange
        $_GET['action'] = 'review';

        // Act
        ob_start();
        $this->controller->handleRequest();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
    }

    public function testHandleRequestInvalidAction(): void
    {
        // Arrange
        $_GET['action'] = 'invalid_action';

        // Act
        ob_start();
        $this->controller->handleRequest();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
    }

    public function testProcessEmailsPost(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['max_emails'] = '25';
        $_POST['days_back'] = '14';
        $_POST['search_query'] = 'amazon order';

        // Act
        ob_start();
        $this->controller->processEmails();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        // Check if it's JSON output
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
    }

    public function testUploadPdfsPost(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_FILES['pdf_files'] = [
            'name' => ['test1.pdf', 'test2.pdf'],
            'type' => ['application/pdf', 'application/pdf'],
            'tmp_name' => ['/tmp/upload1', '/tmp/upload2'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [1024, 2048]
        ];

        // Act
        ob_start();
        $this->controller->uploadPdfs();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        // Check if it's JSON output
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
    }

    public function testProcessDirectoryPost(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['directory_path'] = '/nonexistent/path'; // Will fail validation
        $_POST['recursive'] = '1';

        // Act
        ob_start();
        $this->controller->processDirectory();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertFalse($decoded['success']);
        $this->assertEquals('Invalid directory path', $decoded['error']);
    }

    public function testGetInvoiceDetailsValid(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['id'] = '123';

        // Act
        ob_start();
        $this->controller->getInvoiceDetails();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
    }

    public function testGetInvoiceDetailsInvalid(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'GET';
        // Missing 'id' parameter

        // Act
        ob_start();
        $this->controller->getInvoiceDetails();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('error', $decoded);
    }

    public function testMarkInvoiceProcessedValid(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $input = json_encode([
            'invoice_id' => 123,
            'fa_invoice_number' => 'FA-INV-456'
        ]);

        // Mock php://input
        $this->mockPhpInput($input);

        // Act
        ob_start();
        $this->controller->markInvoiceProcessed();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('success', $decoded);
    }

    public function testMarkInvoiceProcessedInvalid(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $input = json_encode([
            'invoice_id' => 0 // Invalid ID
        ]);

        $this->mockPhpInput($input);

        // Act
        ob_start();
        $this->controller->markInvoiceProcessed();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertEquals('Invalid invoice ID', $decoded['error']);
    }

    public function testGetStatistics(): void
    {
        // Arrange
        $_GET['days'] = '60';

        // Act
        ob_start();
        $this->controller->getStatistics();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
    }

    public function testCleanupPost(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['days_to_keep'] = '120';

        // Act
        ob_start();
        $this->controller->cleanup();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertTrue($decoded['success']);
        $this->assertArrayHasKey('cleaned', $decoded);
    }

    public function testApiProcessEmails(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $input = json_encode([
            'action' => 'process_emails',
            'options' => ['max_emails' => 10]
        ]);

        $this->mockPhpInput($input);

        // Act
        ob_start();
        $this->controller->api();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
    }

    public function testApiGetPending(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $input = json_encode([
            'action' => 'get_pending',
            'limit' => 25
        ]);

        $this->mockPhpInput($input);

        // Act
        ob_start();
        $this->controller->api();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('invoices', $decoded);
    }

    public function testApiGetStatistics(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $input = json_encode([
            'action' => 'get_statistics',
            'days' => 14
        ]);

        $this->mockPhpInput($input);

        // Act
        ob_start();
        $this->controller->api();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
    }

    public function testApiMarkProcessed(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $input = json_encode([
            'action' => 'mark_processed',
            'invoice_id' => 789,
            'fa_invoice_number' => 'FA-789'
        ]);

        $this->mockPhpInput($input);

        // Act
        ob_start();
        $this->controller->api();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('success', $decoded);
    }

    public function testApiUnknownAction(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $input = json_encode([
            'action' => 'unknown_action'
        ]);

        $this->mockPhpInput($input);

        // Act
        ob_start();
        $this->controller->api();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertEquals('Unknown action', $decoded['error']);
    }

    public function testApiInvalidMethod(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Act
        ob_start();
        $this->controller->api();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertEquals('Only POST method allowed', $decoded['error']);
    }

    public function testMarkInvoiceProcessedInvalidMethod(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Act
        ob_start();
        $this->controller->markInvoiceProcessed();
        $output = ob_get_clean();

        // Assert
        $this->assertNotEmpty($output);
        
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertEquals('Method not allowed', $decoded['error']);
    }

    /**
     * Mock php://input for testing POST requests with JSON payloads
     */
    private function mockPhpInput(string $input): void
    {
        // In a real testing environment, you might use stream wrappers
        // or dependency injection to mock file_get_contents('php://input')
        // For this example, we'll assume the controller can handle this
        
        // Note: This is a simplified approach. In practice, you might want to
        // refactor the controller to accept the input as a parameter for better testability
    }
}
