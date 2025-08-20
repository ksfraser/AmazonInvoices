<?php

declare(strict_types=1);

namespace AmazonInvoices\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use AmazonInvoices\Services\AmazonCredentialService;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

class AmazonCredentialServiceTest extends TestCase
{
    private AmazonCredentialService $service;
    private MockObject $database;
    private string $testEncryptionKey = 'test-encryption-key-32-chars-long';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->database = $this->createMock(DatabaseRepositoryInterface::class);
        $this->service = new AmazonCredentialService($this->database, $this->testEncryptionKey);
    }

    public function testStoreCredentials(): void
    {
        // Arrange
        $credentialType = 'gmail';
        $credentials = [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'refresh_token' => 'test_refresh_token'
        ];

        $this->database->expects($this->exactly(2))
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
        $result = $this->service->storeCredentials($credentialType, $credentials);

        // Assert
        $this->assertEquals(123, $result);
    }

    public function testGetCredentials(): void
    {
        // Arrange
        $credentialType = 'gmail';
        $encryptedData = $this->encryptTestData([
            'client_id' => 'stored_client_id',
            'client_secret' => 'stored_client_secret'
        ]);

        $mockResult = [
            'id' => 456,
            'credential_type' => 'gmail',
            'encrypted_data' => $encryptedData,
            'is_active' => 1,
            'created_at' => '2025-08-04 12:00:00'
        ];

        $this->database->expects($this->once())
            ->method('escape')
            ->with($credentialType)
            ->willReturn($credentialType);

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('SELECT'))
            ->willReturn('result');

        $this->database->expects($this->once())
            ->method('fetch')
            ->willReturn($mockResult);

        // Act
        $result = $this->service->getCredentials($credentialType);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('stored_client_id', $result['client_id']);
        $this->assertEquals('stored_client_secret', $result['client_secret']);
    }

    public function testGetCredentialsNotFound(): void
    {
        // Arrange
        $credentialType = 'nonexistent';

        $this->database->expects($this->once())
            ->method('escape')
            ->willReturn($credentialType);

        $this->database->expects($this->once())
            ->method('query')
            ->willReturn('result');

        $this->database->expects($this->once())
            ->method('fetch')
            ->willReturn(null);

        // Act
        $result = $this->service->getCredentials($credentialType);

        // Assert
        $this->assertNull($result);
    }

    public function testUpdateCredentials(): void
    {
        // Arrange
        $credentialId = 789;
        $newCredentials = [
            'client_id' => 'updated_client_id',
            'client_secret' => 'updated_client_secret'
        ];

        $this->database->expects($this->once())
            ->method('escape')
            ->willReturnArgument(0);

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('UPDATE'))
            ->willReturn(true);

        // Act
        $result = $this->service->updateCredentials($credentialId, $newCredentials);

        // Assert
        $this->assertTrue($result);
    }

    public function testDeleteCredentials(): void
    {
        // Arrange
        $credentialId = 999;

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains("id = $credentialId"))
            ->willReturn(true);

        // Act
        $result = $this->service->deleteCredentials($credentialId);

        // Assert
        $this->assertTrue($result);
    }

    public function testListCredentials(): void
    {
        // Arrange
        $mockCredentials = [
            [
                'id' => 1,
                'credential_type' => 'gmail',
                'is_active' => 1,
                'created_at' => '2025-08-01 10:00:00',
                'updated_at' => '2025-08-01 10:00:00'
            ],
            [
                'id' => 2,
                'credential_type' => 'amazon_api',
                'is_active' => 1,
                'created_at' => '2025-08-02 11:00:00',
                'updated_at' => '2025-08-02 11:00:00'
            ]
        ];

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('SELECT'))
            ->willReturn('result');

        $this->database->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                $mockCredentials[0],
                $mockCredentials[1],
                null
            );

        // Act
        $result = $this->service->listCredentials();

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('gmail', $result[0]['credential_type']);
        $this->assertEquals('amazon_api', $result[1]['credential_type']);
    }

    public function testToggleCredentialStatus(): void
    {
        // Arrange
        $credentialId = 555;
        $isActive = false;

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('is_active = 0'))
            ->willReturn(true);

        // Act
        $result = $this->service->toggleCredentialStatus($credentialId, $isActive);

        // Assert
        $this->assertTrue($result);
    }

    public function testValidateCredentials(): void
    {
        // Test valid Gmail credentials
        $validGmailCredentials = [
            'client_id' => 'valid_client_id.apps.googleusercontent.com',
            'client_secret' => 'valid_client_secret'
        ];

        $this->assertTrue($this->service->validateCredentials('gmail', $validGmailCredentials));

        // Test invalid Gmail credentials
        $invalidGmailCredentials = [
            'client_id' => '', // Empty client ID
            'client_secret' => 'valid_client_secret'
        ];

        $this->assertFalse($this->service->validateCredentials('gmail', $invalidGmailCredentials));

        // Test unknown credential type
        $this->assertFalse($this->service->validateCredentials('unknown', []));
    }

    public function testTestConnection(): void
    {
        // Test Gmail connection
        $gmailCredentials = [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret'
        ];

        $result = $this->service->testConnection('gmail', $gmailCredentials);
        
        // Since we can't actually test the connection without real credentials,
        // we expect it to return a result array with connection status
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testEncryptDecryptData(): void
    {
        // Test encryption and decryption using reflection to access private methods
        $reflection = new \ReflectionClass($this->service);
        
        $encryptMethod = $reflection->getMethod('encryptData');
        $encryptMethod->setAccessible(true);
        
        $decryptMethod = $reflection->getMethod('decryptData');
        $decryptMethod->setAccessible(true);

        $originalData = ['test' => 'data', 'number' => 123];
        
        // Encrypt data
        $encrypted = $encryptMethod->invoke($this->service, $originalData);
        $this->assertIsString($encrypted);
        $this->assertNotEquals(json_encode($originalData), $encrypted);
        
        // Decrypt data
        $decrypted = $decryptMethod->invoke($this->service, $encrypted);
        $this->assertEquals($originalData, $decrypted);
    }

    public function testGenerateEncryptionKey(): void
    {
        // Test key generation using reflection
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateEncryptionKey');
        $method->setAccessible(true);

        $key = $method->invoke($this->service);
        
        $this->assertIsString($key);
        $this->assertEquals(32, strlen($key)); // AES-256 requires 32-byte key
    }

    public function testGetCredentialsByType(): void
    {
        // Arrange
        $credentialType = 'amazon_api';
        $mockCredentials = [
            [
                'id' => 1,
                'credential_type' => 'amazon_api',
                'is_active' => 1
            ],
            [
                'id' => 2,
                'credential_type' => 'amazon_api',
                'is_active' => 0
            ]
        ];

        $this->database->expects($this->once())
            ->method('escape')
            ->willReturn($credentialType);

        $this->database->expects($this->once())
            ->method('query')
            ->willReturn('result');

        $this->database->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                $mockCredentials[0],
                $mockCredentials[1],
                null
            );

        // Act
        $result = $this->service->getCredentialsByType($credentialType);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('amazon_api', $result[0]['credential_type']);
        $this->assertEquals('amazon_api', $result[1]['credential_type']);
    }

    public function testGetActiveCredentials(): void
    {
        // Arrange
        $credentialType = 'gmail';
        $encryptedData = $this->encryptTestData([
            'client_id' => 'active_client_id',
            'client_secret' => 'active_client_secret'
        ]);

        $mockResult = [
            'id' => 111,
            'credential_type' => 'gmail',
            'encrypted_data' => $encryptedData,
            'is_active' => 1
        ];

        $this->database->expects($this->once())
            ->method('escape')
            ->willReturn($credentialType);

        $this->database->expects($this->once())
            ->method('query')
            ->with($this->stringContains('is_active = 1'))
            ->willReturn('result');

        $this->database->expects($this->once())
            ->method('fetch')
            ->willReturn($mockResult);

        // Act
        $result = $this->service->getActiveCredentials($credentialType);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('active_client_id', $result['client_id']);
        $this->assertEquals(1, $result['is_active']);
    }

    /**
     * Helper method to encrypt test data for mocking
     */
    private function encryptTestData(array $data): string
    {
        $json = json_encode($data);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($json, 'AES-256-CBC', $this->testEncryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
}
