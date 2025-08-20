<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use AmazonInvoices\Services\GmailProcessorWrapper;
use AmazonInvoices\Services\DuplicateDetectionService;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

class GmailProcessorWrapperTest extends TestCase
{
    private GmailProcessorWrapper $wrapper;
    private MockObject $database;
    private MockObject $duplicateDetector;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->database = $this->createMock(DatabaseRepositoryInterface::class);
        $this->duplicateDetector = $this->createMock(DuplicateDetectionService::class);
        $this->wrapper = new GmailProcessorWrapper($this->database, $this->duplicateDetector);
    }

    public function testStoreEmailLog(): void
    {
        // Arrange
        $emailData = [
            'gmail_message_id' => 'msg123456',
            'email_subject' => 'Amazon Order Shipped',
            'email_from' => 'ship-confirm@amazon.com',
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
            ->willReturn(123);

        // Act
        $result = $this->wrapper->storeEmailLog($emailData);

        // Assert
        $this->assertEquals(123, $result);
    }

    public function testIsEmailProcessed(): void
    {
        // Arrange
        $messageId = 'msg123456';

        $this->database->expects($this->once())
            ->method('escape')
            ->with($messageId)
            ->willReturn($messageId);

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('gmail_message_id'))
            ->willReturn('result');

        $this->database->expects($this->once())
            ->method('fetch')
            ->willReturn(['id' => 1]);

        // Act
        $result = $this->wrapper->isEmailProcessed($messageId);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsEmailNotProcessed(): void
    {
        // Arrange
        $messageId = 'msg999999';

        $this->database->expects($this->once())
            ->method('escape')
            ->willReturn($messageId);

        $this->database->expects($this->once())
            ->method('query')
            ->willReturn('result');

        $this->database->expects($this->once())
            ->method('fetch')
            ->willReturn(null);

        // Act
        $result = $this->wrapper->isEmailProcessed($messageId);

        // Assert
        $this->assertFalse($result);
    }

    public function testStoreInvoice(): void
    {
        // Arrange
        $invoiceData = [
            'invoice_number' => 'INV-123',
            'order_number' => 'AMZ-456',
            'invoice_date' => '2025-08-04',
            'invoice_total' => 99.99,
            'source_type' => 'email',
            'items' => [
                [
                    'product_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 99.99,
                    'total_price' => 99.99
                ]
            ],
            'payments' => [
                [
                    'payment_method' => 'Credit Card',
                    'amount' => 99.99
                ]
            ]
        ];

        $this->database->expects($this->atLeastOnce())
            ->method('escape')
            ->willReturnArgument(0);

        $this->database->expects($this->exactly(3)) // Invoice + Items + Payments
            ->method('query')
            ->willReturn(true);

        $this->database->expects($this->once())
            ->method('getLastInsertId')
            ->willReturn(456);

        // Act
        $result = $this->wrapper->storeInvoice($invoiceData);

        // Assert
        $this->assertEquals(456, $result);
    }

    public function testFindDuplicateInvoice(): void
    {
        // Arrange
        $invoiceData = ['order_number' => 'AMZ123'];
        $duplicateInfo = ['confidence' => 95, 'match_type' => 'exact_order_number'];

        $this->duplicateDetector->expects($this->once())
            ->method('findDuplicateInvoice')
            ->with($invoiceData)
            ->willReturn($duplicateInfo);

        // Act
        $result = $this->wrapper->findDuplicateInvoice($invoiceData);

        // Assert
        $this->assertEquals($duplicateInfo, $result);
    }

    public function testGetSearchPatterns(): void
    {
        // Arrange
        $mockPatterns = [
            [
                'id' => 1,
                'pattern_name' => 'Amazon Order Shipped',
                'subject_pattern' => '%order%shipped%',
                'priority' => 1
            ],
            [
                'id' => 2,
                'pattern_name' => 'Amazon Receipt',
                'subject_pattern' => '%receipt%',
                'priority' => 2
            ]
        ];

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('email_search_patterns'))
            ->willReturn('result');

        $this->database->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                $mockPatterns[0],
                $mockPatterns[1],
                null
            );

        // Act
        $result = $this->wrapper->getSearchPatterns();

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('Amazon Order Shipped', $result[0]['pattern_name']);
        $this->assertEquals('Amazon Receipt', $result[1]['pattern_name']);
    }

    public function testGetGmailCredentials(): void
    {
        // Arrange
        $mockCredentials = [
            [
                'id' => 1,
                'client_id' => 'test_client_id',
                'client_secret' => 'encrypted_secret',
                'is_active' => 1
            ]
        ];

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('gmail_credentials'))
            ->willReturn('result');

        $this->database->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                $mockCredentials[0],
                null
            );

        // Act
        $result = $this->wrapper->getGmailCredentials();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('test_client_id', $result[0]['client_id']);
    }

    public function testGetAuthUrl(): void
    {
        // Arrange
        $clientId = 'test_client_id';
        $redirectUri = 'https://example.com/callback';
        $scopes = ['https://www.googleapis.com/auth/gmail.readonly'];

        // Mock session start
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Act
        $authUrl = $this->wrapper->getAuthUrl($clientId, $redirectUri, $scopes);

        // Assert
        $this->assertStringContains('accounts.google.com/o/oauth2/v2/auth', $authUrl);
        $this->assertStringContains($clientId, $authUrl);
        $this->assertStringContains(urlencode($redirectUri), $authUrl);
        $this->assertArrayHasKey('gmail_oauth_state', $_SESSION);
    }

    public function testParseAmazonEmail(): void
    {
        // Arrange
        $emailData = [
            'content' => 'Your Amazon order #AMZ123456 has been shipped. Total: $99.99',
            'headers' => [
                ['name' => 'Subject', 'value' => 'Your Amazon order has shipped'],
                ['name' => 'From', 'value' => 'ship-confirm@amazon.com']
            ],
            'attachments' => []
        ];

        // Act
        $result = $this->wrapper->parseAmazonEmail($emailData);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('AMZ123456', $result['order_number']);
        $this->assertEquals(99.99, $result['invoice_total']);
        $this->assertEquals('Your Amazon order has shipped', $result['email_subject']);
        $this->assertEquals('ship-confirm@amazon.com', $result['email_from']);
    }

    public function testParseNonAmazonEmail(): void
    {
        // Arrange
        $emailData = [
            'content' => 'Regular email content',
            'headers' => [
                ['name' => 'Subject', 'value' => 'Regular email'],
                ['name' => 'From', 'value' => 'someone@example.com']
            ],
            'attachments' => []
        ];

        // Act
        $result = $this->wrapper->parseAmazonEmail($emailData);

        // Assert
        $this->assertNull($result);
    }

    public function testExtractInvoiceData(): void
    {
        // Arrange
        $emailContent = "Order #AMZ123456\nInvoice #INV789\nTotal $149.99\nJuly 15, 2025\n2 x Laptop Computer $74.99";

        // Act
        $result = $this->wrapper->extractInvoiceData($emailContent);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('AMZ123456', $result['order_number']);
        $this->assertEquals('INV789', $result['invoice_number']);
        $this->assertEquals(149.99, $result['invoice_total']);
        $this->assertEquals('2025-07-15', $result['invoice_date']);
        $this->assertNotEmpty($result['items']);
    }

    public function testExtractInvoiceDataNoData(): void
    {
        // Arrange
        $emailContent = "This is just regular email content with no invoice data.";

        // Act
        $result = $this->wrapper->extractInvoiceData($emailContent);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test private method using reflection
     */
    public function testIsAmazonEmail(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->wrapper);
        $method = $reflection->getMethod('isAmazonEmail');
        $method->setAccessible(true);

        // Test Amazon email recognition
        $this->assertTrue($method->invoke($this->wrapper, 'Your Amazon order shipped', 'ship@amazon.com', ''));
        $this->assertTrue($method->invoke($this->wrapper, 'Order confirmation', 'orders@amazon.co.uk', ''));
        $this->assertFalse($method->invoke($this->wrapper, 'Regular email', 'someone@example.com', ''));
    }

    /**
     * Test extraction of items from email content
     */
    public function testExtractItemsFromContent(): void
    {
        $reflection = new \ReflectionClass($this->wrapper);
        $method = $reflection->getMethod('extractItemsFromContent');
        $method->setAccessible(true);

        $content = "2 x Laptop Computer $599.99\n1 x Mouse $29.99";
        $items = $method->invoke($this->wrapper, $content);

        $this->assertCount(2, $items);
        $this->assertEquals(2, $items[0]['quantity']);
        $this->assertEquals('Laptop Computer', $items[0]['product_name']);
        $this->assertEquals(599.99, $items[0]['unit_price']);
        $this->assertEquals(1199.98, $items[0]['total_price']);
    }
}
