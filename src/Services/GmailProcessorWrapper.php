<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use GmailInvoiceProcessor\GmailInvoiceProcessor;
use GmailInvoiceProcessor\Interfaces\StorageInterface as GmailStorageInterface;
use GmailInvoiceProcessor\Interfaces\OAuthInterface;
use GmailInvoiceProcessor\Interfaces\EmailParserInterface;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

/**
 * Gmail Processor Wrapper for FrontAccounting Integration
 * 
 * Wraps the standalone Gmail processor library with FrontAccounting-specific
 * storage implementations and business logic
 */
class GmailProcessorWrapper implements GmailStorageInterface, OAuthInterface, EmailParserInterface
{
    /**
     * @var DatabaseRepositoryInterface
     */
    private $database;

    /**
     * @var DuplicateDetectionService
     */
    private $duplicateDetector;

    /**
     * @var GmailInvoiceProcessor
     */
    private $processor;
    
    public function __construct(
        DatabaseRepositoryInterface $database,
        DuplicateDetectionService $duplicateDetector
    ) {
        $this->database = $database;
        $this->duplicateDetector = $duplicateDetector;
        
        // Create the processor with this wrapper as the implementation
        $this->processor = new GmailInvoiceProcessor($this, $this, $this);
    }
    
    /**
     * Process emails using the wrapped processor
     */
    public function processEmails(array $options = []): array
    {
        $credentials = $this->getGmailCredentials();
        if (empty($credentials)) {
            throw new \Exception("No Gmail credentials configured");
        }
        
        return $this->processor->processEmails($credentials[0], $options);
    }
    
    // ===============================
    // StorageInterface Implementation
    // ===============================
    
    public function storeEmailLog(array $emailData): int
    {
        $fields = [];
        $values = [];
        
        foreach ($emailData as $key => $value) {
            $fields[] = $key;
            $values[] = is_string($value) ? "'" . $this->database->escape($value) . "'" : $value;
        }
        
        $query = "INSERT INTO " . TB_PREF . "amazon_email_logs (" . 
                 implode(', ', $fields) . ") VALUES (" . 
                 implode(', ', $values) . ")";
        
        $this->database->query($query);
        return $this->database->getLastInsertId();
    }
    
    public function isEmailProcessed(string $messageId): bool
    {
        $escapedMessageId = $this->database->escape($messageId);
        $query = "SELECT id FROM " . TB_PREF . "amazon_email_logs 
                  WHERE gmail_message_id = '$escapedMessageId'";
        
        $result = $this->database->query($query);
        return $this->database->fetch($result) !== null;
    }
    
    public function storeInvoice(array $invoiceData): int
    {
        // Map invoice data to staging table format
        $stagingData = [
            'invoice_number' => $invoiceData['invoice_number'] ?? '',
            'order_number' => $invoiceData['order_number'] ?? '',
            'invoice_date' => $invoiceData['invoice_date'] ?? date('Y-m-d'),
            'invoice_total' => $invoiceData['invoice_total'] ?? 0,
            'tax_amount' => $invoiceData['tax_amount'] ?? 0,
            'shipping_amount' => $invoiceData['shipping_amount'] ?? 0,
            'currency' => $invoiceData['currency'] ?? 'USD',
            'raw_data' => json_encode($invoiceData),
            'status' => 'pending',
            'source_type' => $invoiceData['source_type'] ?? 'email',
            'source_id' => $invoiceData['source_id'] ?? ''
        ];
        
        $fields = [];
        $values = [];
        
        foreach ($stagingData as $key => $value) {
            $fields[] = $key;
            $values[] = is_string($value) ? "'" . $this->database->escape($value) . "'" : $value;
        }
        
        $query = "INSERT INTO " . TB_PREF . "amazon_invoices_staging (" . 
                 implode(', ', $fields) . ") VALUES (" . 
                 implode(', ', $values) . ")";
        
        $this->database->query($query);
        $invoiceId = $this->database->getLastInsertId();
        
        // Store invoice items if present
        if (!empty($invoiceData['items']) && is_array($invoiceData['items'])) {
            $this->storeInvoiceItems($invoiceId, $invoiceData['items']);
        }
        
        // Store payment information if present
        if (!empty($invoiceData['payments']) && is_array($invoiceData['payments'])) {
            $this->storePayments($invoiceId, $invoiceData['payments']);
        }
        
        return $invoiceId;
    }
    
    public function findDuplicateInvoice(array $invoiceData): ?array
    {
        return $this->duplicateDetector->findDuplicateInvoice($invoiceData);
    }
    
    public function getSearchPatterns(): array
    {
        $query = "SELECT * FROM " . TB_PREF . "email_search_patterns 
                  WHERE is_active = 1 ORDER BY priority, pattern_name";
        $result = $this->database->query($query);
        
        $patterns = [];
        while ($row = $this->database->fetch($result)) {
            $patterns[] = $row;
        }
        
        return $patterns;
    }
    
    public function getGmailCredentials(): array
    {
        $query = "SELECT * FROM " . TB_PREF . "gmail_credentials 
                  WHERE is_active = 1 ORDER BY created_at DESC";
        $result = $this->database->query($query);
        
        $credentials = [];
        while ($row = $this->database->fetch($result)) {
            $credentials[] = $row;
        }
        
        return $credentials;
    }
    
    // =============================
    // OAuthInterface Implementation
    // =============================
    
    public function getAuthUrl(string $clientId, string $redirectUri, array $scopes): string
    {
        $scopeString = implode(' ', $scopes);
        $state = bin2hex(random_bytes(16));
        
        // Store state for verification
        session_start();
        $_SESSION['gmail_oauth_state'] = $state;
        
        return "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopeString,
            'response_type' => 'code',
            'access_type' => 'offline',
            'state' => $state
        ]);
    }
    
    public function exchangeAuthCode(string $code, string $clientId, string $clientSecret, string $redirectUri): array
    {
        $postData = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("OAuth token exchange failed: HTTP $httpCode");
        }
        
        $tokenData = json_decode($response, true);
        if (!$tokenData || isset($tokenData['error'])) {
            throw new \Exception("OAuth token exchange failed: " . ($tokenData['error'] ?? 'Unknown error'));
        }
        
        return $tokenData;
    }
    
    public function refreshToken(string $refreshToken, string $clientId, string $clientSecret): array
    {
        $postData = [
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Token refresh failed: HTTP $httpCode");
        }
        
        $tokenData = json_decode($response, true);
        if (!$tokenData || isset($tokenData['error'])) {
            throw new \Exception("Token refresh failed: " . ($tokenData['error'] ?? 'Unknown error'));
        }
        
        return $tokenData;
    }
    
    // ==================================
    // EmailParserInterface Implementation
    // ==================================
    
    public function parseAmazonEmail(array $emailData): ?array
    {
        $content = $emailData['content'] ?? '';
        $attachments = $emailData['attachments'] ?? [];
        $headers = $emailData['headers'] ?? [];
        
        // Extract basic email information
        $subject = $this->getHeaderValue($headers, 'Subject') ?? '';
        $from = $this->getHeaderValue($headers, 'From') ?? '';
        
        // Check if this looks like an Amazon email
        if (!$this->isAmazonEmail($subject, $from, $content)) {
            return null;
        }
        
        // Extract invoice data from content
        $invoiceData = $this->extractInvoiceData($content, $attachments);
        
        if (!$invoiceData) {
            return null;
        }
        
        // Add email metadata
        $invoiceData['email_subject'] = $subject;
        $invoiceData['email_from'] = $from;
        $invoiceData['extracted_from'] = 'email_content';
        
        return $invoiceData;
    }
    
    public function extractInvoiceData(string $emailContent, array $attachments = []): ?array
    {
        $invoiceData = [];
        
        // Extract order number
        if (preg_match('/Order #([A-Z0-9-]+)/i', $emailContent, $matches)) {
            $invoiceData['order_number'] = $matches[1];
        }
        
        // Extract invoice/receipt number
        if (preg_match('/Invoice #([A-Z0-9-]+)/i', $emailContent, $matches)) {
            $invoiceData['invoice_number'] = $matches[1];
        } elseif (preg_match('/Receipt #([A-Z0-9-]+)/i', $emailContent, $matches)) {
            $invoiceData['invoice_number'] = $matches[1];
        }
        
        // Extract total amount
        if (preg_match('/Total[:\s]+\$([0-9,]+\.?\d*)/i', $emailContent, $matches)) {
            $invoiceData['invoice_total'] = (float)str_replace(',', '', $matches[1]);
        } elseif (preg_match('/\$([0-9,]+\.?\d*)\s*total/i', $emailContent, $matches)) {
            $invoiceData['invoice_total'] = (float)str_replace(',', '', $matches[1]);
        }
        
        // Extract date
        if (preg_match('/(\w+\s+\d{1,2},\s+\d{4})/i', $emailContent, $matches)) {
            $invoiceData['invoice_date'] = date('Y-m-d', strtotime($matches[1]));
        }
        
        // Extract items (basic parsing)
        $items = $this->extractItemsFromContent($emailContent);
        if (!empty($items)) {
            $invoiceData['items'] = $items;
        }
        
        // Process attachments for more detailed data
        foreach ($attachments as $attachment) {
            if ($attachment['mime_type'] === 'application/pdf') {
                // Could integrate PDF parsing here for more detailed extraction
                $invoiceData['has_pdf_attachment'] = true;
            }
        }
        
        // Return null if we couldn't extract meaningful data
        if (empty($invoiceData['order_number']) && empty($invoiceData['invoice_number']) && empty($invoiceData['invoice_total'])) {
            return null;
        }
        
        return $invoiceData;
    }
    
    // =================
    // Helper Methods
    // =================
    
    private function storeInvoiceItems(int $invoiceId, array $items): void
    {
        foreach ($items as $lineNumber => $item) {
            $itemData = [
                'staging_invoice_id' => $invoiceId,
                'line_number' => $lineNumber + 1,
                'product_name' => $item['product_name'] ?? '',
                'asin' => $item['asin'] ?? '',
                'sku' => $item['sku'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? 0,
                'total_price' => $item['total_price'] ?? 0
            ];
            
            $fields = [];
            $values = [];
            
            foreach ($itemData as $key => $value) {
                $fields[] = $key;
                $values[] = is_string($value) ? "'" . $this->database->escape($value) . "'" : $value;
            }
            
            $query = "INSERT INTO " . TB_PREF . "amazon_invoice_items_staging (" . 
                     implode(', ', $fields) . ") VALUES (" . 
                     implode(', ', $values) . ")";
            
            $this->database->query($query);
        }
    }
    
    private function storePayments(int $invoiceId, array $payments): void
    {
        foreach ($payments as $payment) {
            $paymentData = [
                'staging_invoice_id' => $invoiceId,
                'payment_method' => $payment['payment_method'] ?? '',
                'payment_reference' => $payment['payment_reference'] ?? '',
                'amount' => $payment['amount'] ?? 0
            ];
            
            $fields = [];
            $values = [];
            
            foreach ($paymentData as $key => $value) {
                $fields[] = $key;
                $values[] = is_string($value) ? "'" . $this->database->escape($value) . "'" : $value;
            }
            
            $query = "INSERT INTO " . TB_PREF . "amazon_payment_staging (" . 
                     implode(', ', $fields) . ") VALUES (" . 
                     implode(', ', $values) . ")";
            
            $this->database->query($query);
        }
    }
    
    private function getHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (is_array($header) && isset($header['name'], $header['value'])) {
                if (strtolower($header['name']) === strtolower($name)) {
                    return $header['value'];
                }
            } elseif (is_object($header) && method_exists($header, 'getName') && method_exists($header, 'getValue')) {
                if (strtolower($header->getName()) === strtolower($name)) {
                    return $header->getValue();
                }
            }
        }
        return null;
    }
    
    private function isAmazonEmail(string $subject, string $from, string $content): bool
    {
        // Check from address
        if (preg_match('/@amazon\.(com|co\.uk|de|fr|it|es|ca|com\.au|co\.jp)$/i', $from)) {
            return true;
        }
        
        // Check subject patterns
        $amazonPatterns = [
            '/your.*amazon.*order/i',
            '/your.*order.*shipped/i',
            '/your.*receipt.*amazon/i',
            '/amazon.*invoice/i',
            '/order.*confirmation/i'
        ];
        
        foreach ($amazonPatterns as $pattern) {
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }
        
        // Check content for Amazon indicators
        if (preg_match('/amazon\.(com|co\.uk|de|fr)/i', $content) && 
            preg_match('/order #[A-Z0-9-]+/i', $content)) {
            return true;
        }
        
        return false;
    }
    
    private function extractItemsFromContent(string $content): array
    {
        $items = [];
        
        // This is a simplified extraction - real implementation would be more sophisticated
        // Look for item patterns in email content
        if (preg_match_all('/(\d+)\s*x\s*(.+?)\s*\$([0-9,]+\.?\d*)/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $items[] = [
                    'quantity' => (int)$match[1],
                    'product_name' => trim($match[2]),
                    'unit_price' => (float)str_replace(',', '', $match[3]),
                    'total_price' => (int)$match[1] * (float)str_replace(',', '', $match[3])
                ];
            }
        }
        
        return $items;
    }
}
