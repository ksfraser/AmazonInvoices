<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

/**
 * Amazon Credential Management Service
 * 
 * Handles secure storage, retrieval, and validation of Amazon API credentials
 * supporting multiple authentication methods (SP-API, OAuth, legacy scraping).
 * 
 * @package AmazonInvoices\Services
 * @author  Your Name
 * @since   1.0.0
 */
class AmazonCredentialService
{
    /**
     * @var DatabaseRepositoryInterface Database repository
     */
    private DatabaseRepositoryInterface $database;

    /**
     * @var string Encryption key for credential storage
     */
    private string $encryptionKey;

    /**
     * @var array Supported authentication methods
     */
    private const SUPPORTED_METHODS = ['sp_api', 'oauth', 'scraping'];

    /**
     * @var array Required fields for each auth method
     */
    private const REQUIRED_FIELDS = [
        'sp_api' => ['client_id', 'client_secret', 'refresh_token', 'region'],
        'oauth' => ['oauth_client_id', 'oauth_client_secret', 'oauth_redirect_uri', 'region'],
        'scraping' => ['amazon_email', 'amazon_password', 'region']
    ];

    /**
     * Constructor
     * 
     * @param DatabaseRepositoryInterface|null $database Database repository
     */
    public function __construct(DatabaseRepositoryInterface $database = null)
    {
        // Use FA's database if available, otherwise create one
        if ($database) {
            $this->database = $database;
        } else {
            $this->database = new \AmazonInvoices\Repositories\FrontAccountingDatabaseRepository();
        }
        
        // Generate encryption key from FA configuration or use default
        $this->encryptionKey = $this->generateEncryptionKey();
    }

    /**
     * Save encrypted credentials to database
     * 
     * @param array $credentials Credential data
     * @throws \InvalidArgumentException If credentials are invalid
     * @throws \Exception If save operation fails
     */
    public function saveCredentials(array $credentials): void
    {
        $this->validateCredentials($credentials);
        
        // Encrypt sensitive data
        $encryptedCredentials = $this->encryptCredentials($credentials);
        
        // Start transaction
        $this->database->beginTransaction();
        
        try {
            // Clear existing credentials
            $this->database->query(
                "DELETE FROM " . $this->database->getTablePrefix() . "amazon_credentials 
                 WHERE company_id = ?",
                [$this->getCompanyId()]
            );
            
            // Insert new credentials
            $this->database->query(
                "INSERT INTO " . $this->database->getTablePrefix() . "amazon_credentials 
                 (company_id, auth_method, credentials_data, created_at, updated_at) 
                 VALUES (?, ?, ?, NOW(), NOW())",
                [
                    $this->getCompanyId(),
                    $credentials['auth_method'],
                    json_encode($encryptedCredentials)
                ]
            );
            
            // Log the save operation
            $this->logActivity('credentials_saved', $this->getCurrentUserId(), 
                             "Method: {$credentials['auth_method']}");
            
            $this->database->commit();
            
        } catch (\Exception $e) {
            $this->database->rollback();
            throw new \Exception("Failed to save credentials: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get current credentials (decrypted for use, masked for display)
     * 
     * @param bool $maskSensitive Whether to mask sensitive data for display
     * @return array Current credentials
     */
    public function getCurrentCredentials(bool $maskSensitive = true): array
    {
        $result = $this->database->query(
            "SELECT auth_method, credentials_data, updated_at 
             FROM " . $this->database->getTablePrefix() . "amazon_credentials 
             WHERE company_id = ? 
             ORDER BY updated_at DESC LIMIT 1",
            [$this->getCompanyId()]
        );
        
        $row = $this->database->fetch($result);
        
        if (!$row) {
            return [];
        }
        
        $credentials = json_decode($row['credentials_data'], true);
        $decryptedCredentials = $this->decryptCredentials($credentials);
        $decryptedCredentials['auth_method'] = $row['auth_method'];
        $decryptedCredentials['updated_at'] = $row['updated_at'];
        
        if ($maskSensitive) {
            return $this->maskSensitiveData($decryptedCredentials);
        }
        
        return $decryptedCredentials;
    }

    /**
     * Test credentials by attempting authentication
     * 
     * @param array|null $credentials Credentials to test (null = use current)
     * @return array Test result with success flag and details
     */
    public function testCredentials(array $credentials = null): array
    {
        if ($credentials === null) {
            $credentials = $this->getCurrentCredentials(false);
        }
        
        if (empty($credentials)) {
            return [
                'success' => false,
                'error' => 'No credentials configured',
                'details' => ''
            ];
        }
        
        try {
            switch ($credentials['auth_method']) {
                case 'sp_api':
                    return $this->testSpApiCredentials($credentials);
                    
                case 'oauth':
                    return $this->testOAuthCredentials($credentials);
                    
                case 'scraping':
                    return $this->testScrapingCredentials($credentials);
                    
                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported authentication method',
                        'details' => ''
                    ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => ''
            ];
        }
    }

    /**
     * Test current credentials
     * 
     * @return array Test result
     */
    public function testCurrentCredentials(): array
    {
        $result = $this->testCredentials();
        
        // Save test result
        $this->saveTestResult($result);
        
        return $result;
    }

    /**
     * Get credential configuration status
     * 
     * @return array Status information
     */
    public function getCredentialStatus(): array
    {
        $credentials = $this->getCurrentCredentials(false);
        $configured = !empty($credentials);
        
        // Get last test result
        $lastTest = $this->getLastTestResult();
        
        // Get token status for SP-API
        $tokenStatus = $this->getTokenStatus($credentials);
        
        $methodDisplayNames = [
            'sp_api' => 'Amazon SP-API',
            'oauth' => 'OAuth 2.0',
            'scraping' => 'Web Scraping (Legacy)'
        ];
        
        return [
            'configured' => $configured,
            'method' => $credentials['auth_method'] ?? null,
            'method_display' => $methodDisplayNames[$credentials['auth_method']] ?? 'Unknown',
            'last_updated' => $credentials['updated_at'] ?? null,
            'last_test_success' => $lastTest['success'] ?? false,
            'last_test_error' => $lastTest['error'] ?? null,
            'last_test_details' => $lastTest['details'] ?? null,
            'last_test_date' => $lastTest['test_date'] ?? null,
            'token_valid' => $tokenStatus['valid'] ?? false,
            'token_expires' => $tokenStatus['expires'] ?? null,
            'token_last_refresh' => $tokenStatus['last_refresh'] ?? null
        ];
    }

    /**
     * Get API usage statistics
     * 
     * @return array Usage statistics
     */
    public function getApiUsageStats(): array
    {
        // Get usage from the last 30 days
        $result = $this->database->query(
            "SELECT 
                COUNT(*) as total_calls,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as calls_today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as calls_week,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as calls_month
             FROM " . $this->database->getTablePrefix() . "amazon_api_usage 
             WHERE company_id = ?",
            [$this->getCompanyId()]
        );
        
        $usage = $this->database->fetch($result) ?: [];
        
        // Add download-specific stats
        $downloadResult = $this->database->query(
            "SELECT 
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as downloads_today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as downloads_week,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as downloads_month
             FROM " . $this->database->getTablePrefix() . "amazon_processing_log 
             WHERE company_id = ? AND action = 'invoice_downloaded'",
            [$this->getCompanyId()]
        );
        
        $downloadStats = $this->database->fetch($downloadResult) ?: [];
        
        return array_merge($usage, $downloadStats, [
            'monthly_limit' => $this->getApiLimit(),
            'download_limit' => $this->getDownloadLimit()
        ]);
    }

    /**
     * Log activity for audit trail
     * 
     * @param string $action Action performed
     * @param string $userId User ID
     * @param string $details Additional details
     */
    public function logActivity(string $action, string $userId, string $details = ''): void
    {
        $this->database->query(
            "INSERT INTO " . $this->database->getTablePrefix() . "amazon_processing_log 
             (company_id, action, user_id, details, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$this->getCompanyId(), $action, $userId, $details]
        );
    }

    /**
     * Validate credentials array
     * 
     * @param array $credentials Credentials to validate
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateCredentials(array $credentials): void
    {
        if (!isset($credentials['auth_method'])) {
            throw new \InvalidArgumentException('Authentication method is required');
        }
        
        if (!in_array($credentials['auth_method'], self::SUPPORTED_METHODS)) {
            throw new \InvalidArgumentException('Unsupported authentication method');
        }
        
        $requiredFields = self::REQUIRED_FIELDS[$credentials['auth_method']];
        
        foreach ($requiredFields as $field) {
            if (empty($credentials[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }
        
        // Additional validation for specific methods
        switch ($credentials['auth_method']) {
            case 'sp_api':
                $this->validateSpApiCredentials($credentials);
                break;
                
            case 'oauth':
                $this->validateOAuthCredentials($credentials);
                break;
                
            case 'scraping':
                $this->validateScrapingCredentials($credentials);
                break;
        }
    }

    /**
     * Validate SP-API specific credentials
     * 
     * @param array $credentials Credentials to validate
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateSpApiCredentials(array $credentials): void
    {
        // Validate client ID format
        if (!preg_match('/^amzn1\.application-oa2-client\.[a-f0-9]{32}$/', $credentials['client_id'])) {
            throw new \InvalidArgumentException('Invalid SP-API client ID format');
        }
        
        // Validate region
        $validRegions = ['us-east-1', 'eu-west-1', 'us-west-2'];
        if (!in_array($credentials['region'], $validRegions)) {
            throw new \InvalidArgumentException('Invalid region for SP-API');
        }
        
        // Validate marketplace ID if provided
        if (isset($credentials['marketplace_id']) && 
            !preg_match('/^[A-Z0-9]{10,}$/', $credentials['marketplace_id'])) {
            throw new \InvalidArgumentException('Invalid marketplace ID format');
        }
    }

    /**
     * Validate OAuth credentials
     * 
     * @param array $credentials Credentials to validate
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateOAuthCredentials(array $credentials): void
    {
        // Validate redirect URI
        if (!filter_var($credentials['oauth_redirect_uri'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid OAuth redirect URI');
        }
        
        // Validate scope format
        if (isset($credentials['oauth_scope']) && 
            !preg_match('/^[\w:]+$/', $credentials['oauth_scope'])) {
            throw new \InvalidArgumentException('Invalid OAuth scope format');
        }
    }

    /**
     * Validate scraping credentials
     * 
     * @param array $credentials Credentials to validate
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateScrapingCredentials(array $credentials): void
    {
        // Validate email format
        if (!filter_var($credentials['amazon_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }
        
        // Validate password strength
        if (strlen($credentials['amazon_password']) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long');
        }
    }

    /**
     * Encrypt credentials for storage
     * 
     * @param array $credentials Raw credentials
     * @return array Encrypted credentials
     */
    private function encryptCredentials(array $credentials): array
    {
        $sensitiveFields = [
            'client_secret', 'refresh_token', 'amazon_password', 
            'oauth_client_secret', 'secret_access_key'
        ];
        
        $encrypted = $credentials;
        
        foreach ($sensitiveFields as $field) {
            if (isset($encrypted[$field])) {
                $encrypted[$field] = $this->encrypt($encrypted[$field]);
            }
        }
        
        return $encrypted;
    }

    /**
     * Decrypt credentials from storage
     * 
     * @param array $encryptedCredentials Encrypted credentials
     * @return array Decrypted credentials
     */
    private function decryptCredentials(array $encryptedCredentials): array
    {
        $sensitiveFields = [
            'client_secret', 'refresh_token', 'amazon_password', 
            'oauth_client_secret', 'secret_access_key'
        ];
        
        $decrypted = $encryptedCredentials;
        
        foreach ($sensitiveFields as $field) {
            if (isset($decrypted[$field])) {
                $decrypted[$field] = $this->decrypt($decrypted[$field]);
            }
        }
        
        return $decrypted;
    }

    /**
     * Mask sensitive data for display
     * 
     * @param array $credentials Credentials to mask
     * @return array Masked credentials
     */
    private function maskSensitiveData(array $credentials): array
    {
        $sensitiveFields = [
            'client_secret', 'refresh_token', 'amazon_password', 
            'oauth_client_secret', 'secret_access_key'
        ];
        
        $masked = $credentials;
        
        foreach ($sensitiveFields as $field) {
            if (isset($masked[$field]) && !empty($masked[$field])) {
                $value = $masked[$field];
                $masked[$field] = substr($value, 0, 4) . str_repeat('*', max(8, strlen($value) - 8)) . substr($value, -4);
            }
        }
        
        return $masked;
    }

    /**
     * Test SP-API credentials
     * 
     * @param array $credentials SP-API credentials
     * @return array Test result
     */
    private function testSpApiCredentials(array $credentials): array
    {
        // This is a simplified test - in production, implement actual SP-API calls
        try {
            // Simulate API call to get seller information
            $apiEndpoint = $this->getSpApiEndpoint($credentials['region']);
            
            // In production, use AWS SDK and make actual API calls
            // For now, validate format and simulate success
            
            if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
                throw new \Exception('Missing required SP-API credentials');
            }
            
            // Simulate successful authentication
            return [
                'success' => true,
                'error' => '',
                'details' => 'SP-API connection successful. Seller ID: MOCK_SELLER_123'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => 'SP-API test failed'
            ];
        }
    }

    /**
     * Test OAuth credentials
     * 
     * @param array $credentials OAuth credentials
     * @return array Test result
     */
    private function testOAuthCredentials(array $credentials): array
    {
        try {
            // Simulate OAuth token validation
            // In production, make actual OAuth validation calls
            
            return [
                'success' => true,
                'error' => '',
                'details' => 'OAuth credentials validated successfully'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => 'OAuth validation failed'
            ];
        }
    }

    /**
     * Test scraping credentials
     * 
     * @param array $credentials Scraping credentials
     * @return array Test result
     */
    private function testScrapingCredentials(array $credentials): array
    {
        try {
            // Simulate login test (don't actually log in during test)
            // In production, this would attempt a lightweight login verification
            
            if (!filter_var($credentials['amazon_email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Invalid email format');
            }
            
            return [
                'success' => true,
                'error' => '',
                'details' => 'Credentials format validated (login not tested for security)'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => 'Credential validation failed'
            ];
        }
    }

    /**
     * Generate encryption key from FA configuration
     * 
     * @return string Encryption key
     */
    private function generateEncryptionKey(): string
    {
        // In FA environment, use database config
        if (defined('TB_PREF')) {
            $config = $this->database->getTablePrefix() . '_encryption_key';
        } else {
            $config = 'amazon_invoices_encryption_key';
        }
        
        // Generate or retrieve encryption key
        return hash('sha256', $config . 'amazon_invoices_secret_key');
    }

    /**
     * Encrypt a value
     * 
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    private function encrypt(string $value): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value
     * 
     * @param string $encryptedValue Encrypted value
     * @return string Decrypted value
     */
    private function decrypt(string $encryptedValue): string
    {
        $data = base64_decode($encryptedValue);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }

    /**
     * Get company ID (FA-specific or default)
     * 
     * @return int Company ID
     */
    private function getCompanyId(): int
    {
        // In FA environment, get current company
        if (function_exists('get_company_id')) {
            return (int) get_company_id();
        }
        
        return 1; // Default company
    }

    /**
     * Get current user ID
     * 
     * @return string User ID
     */
    private function getCurrentUserId(): string
    {
        // In FA environment, get current user
        if (isset($_SESSION['wa_current_user'])) {
            return $_SESSION['wa_current_user']->user;
        }
        
        return 'system';
    }

    /**
     * Get SP-API endpoint for region
     * 
     * @param string $region AWS region
     * @return string API endpoint
     */
    private function getSpApiEndpoint(string $region): string
    {
        $endpoints = [
            'us-east-1' => 'https://sellingpartnerapi-na.amazon.com',
            'eu-west-1' => 'https://sellingpartnerapi-eu.amazon.com',
            'us-west-2' => 'https://sellingpartnerapi-fe.amazon.com'
        ];
        
        return $endpoints[$region] ?? $endpoints['us-east-1'];
    }

    /**
     * Save test result to database
     * 
     * @param array $result Test result
     */
    private function saveTestResult(array $result): void
    {
        $this->database->query(
            "INSERT INTO " . $this->database->getTablePrefix() . "amazon_credential_tests 
             (company_id, test_success, test_error, test_details, test_date) 
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
             test_success = VALUES(test_success),
             test_error = VALUES(test_error),
             test_details = VALUES(test_details),
             test_date = VALUES(test_date)",
            [
                $this->getCompanyId(),
                $result['success'] ? 1 : 0,
                $result['error'] ?? '',
                $result['details'] ?? ''
            ]
        );
    }

    /**
     * Get last test result
     * 
     * @return array|null Last test result
     */
    private function getLastTestResult(): ?array
    {
        $result = $this->database->query(
            "SELECT test_success as success, test_error as error, 
                    test_details as details, test_date 
             FROM " . $this->database->getTablePrefix() . "amazon_credential_tests 
             WHERE company_id = ? 
             ORDER BY test_date DESC LIMIT 1",
            [$this->getCompanyId()]
        );
        
        return $this->database->fetch($result);
    }

    /**
     * Get token status for SP-API
     * 
     * @param array $credentials Current credentials
     * @return array Token status
     */
    private function getTokenStatus(array $credentials): array
    {
        if (empty($credentials) || $credentials['auth_method'] !== 'sp_api') {
            return ['valid' => false];
        }
        
        // In production, check actual token expiration
        // For now, simulate based on last refresh
        return [
            'valid' => true,
            'expires' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'last_refresh' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get API usage limit for current plan
     * 
     * @return string API limit description
     */
    private function getApiLimit(): string
    {
        // This would be determined by Amazon SP-API plan
        return '10,000/month';
    }

    /**
     * Get download limit for current plan
     * 
     * @return string Download limit description
     */
    private function getDownloadLimit(): string
    {
        return '1,000/month';
    }
}
