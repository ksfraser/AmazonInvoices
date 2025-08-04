<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;
use AmazonInvoices\Models\Invoice;
use AmazonInvoices\Models\InvoiceItem;
use AmazonInvoices\Models\Payment;

/**
 * Gmail Email Processor Service
 * 
 * Handles connecting to Gmail and processing Amazon order confirmation emails
 * to extract invoice data for personal purchases.
 * 
 * @package AmazonInvoices\Services
 * @author  Your Name
 * @since   1.0.0
 */
class GmailEmailProcessor
{
    /**
     * @var DatabaseRepositoryInterface Database repository
     */
    private DatabaseRepositoryInterface $database;

    /**
     * @var array Gmail OAuth credentials
     */
    private array $credentials;

    /**
     * @var \Google_Client Google API client
     */
    private ?\Google_Client $googleClient = null;

    /**
     * @var \Google_Service_Gmail Gmail service
     */
    private ?\Google_Service_Gmail $gmailService = null;

    /**
     * @var array Email search patterns (configurable)
     */
    private array $searchPatterns = [
        'subject_patterns' => [
            'Your Amazon.com order',
            'Your order confirmation',
            'Your Amazon receipt',
            'Thank you for your Amazon order',
            'Your shipment from Amazon'
        ],
        'from_patterns' => [
            'auto-confirm@amazon.com',
            'ship-confirm@amazon.com',
            'order-update@amazon.com',
            'digital-no-reply@amazon.com'
        ],
        'body_patterns' => [
            'Order #',
            'Invoice Number',
            'Order Total',
            'Items Ordered',
            'Shipping Address'
        ]
    ];

    /**
     * Constructor
     * 
     * @param DatabaseRepositoryInterface $database Database repository
     * @param array $credentials Gmail OAuth credentials
     */
    public function __construct(DatabaseRepositoryInterface $database, array $credentials = [])
    {
        $this->database = $database;
        $this->credentials = $credentials;
        
        // Load search patterns from database/config
        $this->loadSearchPatterns();
        
        if (!empty($credentials)) {
            $this->initializeGoogleClient();
        }
    }

    /**
     * Initialize Google API client
     * 
     * @throws \Exception If initialization fails
     */
    private function initializeGoogleClient(): void
    {
        if (!class_exists('\Google_Client')) {
            throw new \Exception('Google API Client library not found. Install with: composer require google/apiclient');
        }

        $this->googleClient = new \Google_Client();
        $this->googleClient->setClientId($this->credentials['client_id']);
        $this->googleClient->setClientSecret($this->credentials['client_secret']);
        $this->googleClient->setRedirectUri($this->credentials['redirect_uri']);
        $this->googleClient->addScope(\Google_Service_Gmail::GMAIL_READONLY);
        $this->googleClient->setAccessType('offline');
        $this->googleClient->setPrompt('consent');

        // Set access token if available
        if (!empty($this->credentials['access_token'])) {
            $this->googleClient->setAccessToken($this->credentials['access_token']);
            
            // Refresh token if expired
            if ($this->googleClient->isAccessTokenExpired()) {
                if (!empty($this->credentials['refresh_token'])) {
                    $this->googleClient->fetchAccessTokenWithRefreshToken($this->credentials['refresh_token']);
                    $this->saveRefreshedToken();
                } else {
                    throw new \Exception('Access token expired and no refresh token available');
                }
            }
        }

        $this->gmailService = new \Google_Service_Gmail($this->googleClient);
    }

    /**
     * Get OAuth authorization URL
     * 
     * @return string Authorization URL
     */
    public function getAuthUrl(): string
    {
        if (!$this->googleClient) {
            throw new \Exception('Google client not initialized');
        }

        return $this->googleClient->createAuthUrl();
    }

    /**
     * Exchange authorization code for tokens
     * 
     * @param string $authCode Authorization code from OAuth callback
     * @return array Token data
     * @throws \Exception If token exchange fails
     */
    public function exchangeAuthCode(string $authCode): array
    {
        if (!$this->googleClient) {
            throw new \Exception('Google client not initialized');
        }

        $token = $this->googleClient->fetchAccessTokenWithAuthCode($authCode);
        
        if (isset($token['error'])) {
            throw new \Exception('Failed to exchange auth code: ' . $token['error']);
        }

        // Save tokens to database
        $this->saveTokens($token);

        return $token;
    }

    /**
     * Search for Amazon emails in specified date range
     * 
     * @param \DateTime $startDate Start date for search
     * @param \DateTime $endDate End date for search
     * @param int $maxResults Maximum number of emails to process
     * @return array Array of found email messages
     * @throws \Exception If Gmail API fails
     */
    public function searchAmazonEmails(\DateTime $startDate, \DateTime $endDate, int $maxResults = 100): array
    {
        if (!$this->gmailService) {
            throw new \Exception('Gmail service not initialized');
        }

        // Build search query
        $query = $this->buildSearchQuery($startDate, $endDate);
        
        try {
            $messages = [];
            $pageToken = null;
            
            do {
                $params = [
                    'q' => $query,
                    'maxResults' => min($maxResults - count($messages), 100),
                ];
                
                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $response = $this->gmailService->users_messages->listUsersMessages('me', $params);
                
                if ($response->getMessages()) {
                    foreach ($response->getMessages() as $message) {
                        $messages[] = $this->getMessageDetails($message->getId());
                        
                        if (count($messages) >= $maxResults) {
                            break 2;
                        }
                    }
                }
                
                $pageToken = $response->getNextPageToken();
                
            } while ($pageToken && count($messages) < $maxResults);

            return $messages;

        } catch (\Exception $e) {
            throw new \Exception('Failed to search Gmail: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process Amazon emails and extract invoice data
     * 
     * @param \DateTime $startDate Start date for processing
     * @param \DateTime $endDate End date for processing
     * @param int $maxEmails Maximum number of emails to process
     * @return array Array of Invoice objects
     */
    public function processAmazonEmails(\DateTime $startDate, \DateTime $endDate, int $maxEmails = 50): array
    {
        $emails = $this->searchAmazonEmails($startDate, $endDate, $maxEmails);
        $invoices = [];
        
        foreach ($emails as $email) {
            try {
                $invoice = $this->extractInvoiceFromEmail($email);
                if ($invoice) {
                    $invoices[] = $invoice;
                    
                    // Log successful processing
                    $this->logEmailProcessing($email['id'], 'success', 'Invoice extracted successfully');
                }
            } catch (\Exception $e) {
                // Log processing error but continue with other emails
                $this->logEmailProcessing($email['id'], 'error', $e->getMessage());
                continue;
            }
        }

        return $invoices;
    }

    /**
     * Extract invoice data from email content
     * 
     * @param array $email Email message data
     * @return Invoice|null Invoice object or null if extraction failed
     */
    private function extractInvoiceFromEmail(array $email): ?Invoice
    {
        $body = $this->getEmailBody($email);
        $subject = $this->getEmailSubject($email);
        $date = $this->getEmailDate($email);
        
        // Extract order number
        $orderNumber = $this->extractOrderNumber($body, $subject);
        if (!$orderNumber) {
            throw new \Exception('Could not extract order number from email');
        }

        // Extract invoice/receipt number
        $invoiceNumber = $this->extractInvoiceNumber($body, $subject) ?: 'EMAIL-' . $orderNumber;

        // Extract total amount
        $totalAmount = $this->extractTotalAmount($body);
        if ($totalAmount === null) {
            throw new \Exception('Could not extract total amount from email');
        }

        // Extract currency
        $currency = $this->extractCurrency($body) ?: 'USD';

        // Create invoice
        $invoice = new Invoice(
            $invoiceNumber,
            $orderNumber,
            $date,
            $totalAmount,
            $currency
        );

        // Extract additional details
        $invoice->setTaxAmount($this->extractTaxAmount($body) ?: 0.0);
        $invoice->setShippingAmount($this->extractShippingAmount($body) ?: 0.0);
        $invoice->setRawData(json_encode([
            'email_id' => $email['id'],
            'subject' => $subject,
            'body' => $body,
            'processed_at' => date('Y-m-d H:i:s')
        ]));

        // Extract items
        $items = $this->extractItemsFromEmail($body);
        foreach ($items as $item) {
            $invoice->addItem($item);
        }

        // Extract payment method
        $payment = $this->extractPaymentFromEmail($body, $totalAmount);
        if ($payment) {
            $invoice->addPayment($payment);
        }

        return $invoice;
    }

    /**
     * Build Gmail search query
     * 
     * @param \DateTime $startDate Start date
     * @param \DateTime $endDate End date
     * @return string Gmail search query
     */
    private function buildSearchQuery(\DateTime $startDate, \DateTime $endDate): string
    {
        $query = [];
        
        // Date range
        $query[] = 'after:' . $startDate->format('Y/m/d');
        $query[] = 'before:' . $endDate->format('Y/m/d');
        
        // From patterns
        $fromQueries = [];
        foreach ($this->searchPatterns['from_patterns'] as $from) {
            $fromQueries[] = 'from:' . $from;
        }
        if (!empty($fromQueries)) {
            $query[] = '(' . implode(' OR ', $fromQueries) . ')';
        }
        
        // Subject patterns
        $subjectQueries = [];
        foreach ($this->searchPatterns['subject_patterns'] as $subject) {
            $subjectQueries[] = 'subject:"' . $subject . '"';
        }
        if (!empty($subjectQueries)) {
            $query[] = '(' . implode(' OR ', $subjectQueries) . ')';
        }
        
        return implode(' ', $query);
    }

    /**
     * Get detailed message data
     * 
     * @param string $messageId Message ID
     * @return array Message details
     */
    private function getMessageDetails(string $messageId): array
    {
        $message = $this->gmailService->users_messages->get('me', $messageId);
        
        return [
            'id' => $messageId,
            'threadId' => $message->getThreadId(),
            'payload' => $message->getPayload(),
            'internalDate' => $message->getInternalDate(),
            'headers' => $this->extractHeaders($message->getPayload()),
            'body' => $this->extractMessageBody($message->getPayload())
        ];
    }

    /**
     * Extract headers from message payload
     * 
     * @param \Google_Service_Gmail_MessagePart $payload Message payload
     * @return array Headers array
     */
    private function extractHeaders(\Google_Service_Gmail_MessagePart $payload): array
    {
        $headers = [];
        
        if ($payload->getHeaders()) {
            foreach ($payload->getHeaders() as $header) {
                $headers[strtolower($header->getName())] = $header->getValue();
            }
        }
        
        return $headers;
    }

    /**
     * Extract message body from payload
     * 
     * @param \Google_Service_Gmail_MessagePart $payload Message payload
     * @return string Message body
     */
    private function extractMessageBody(\Google_Service_Gmail_MessagePart $payload): string
    {
        $body = '';
        
        if ($payload->getBody() && $payload->getBody()->getData()) {
            $body = base64_decode(str_replace(['-', '_'], ['+', '/'], $payload->getBody()->getData()));
        }
        
        // Check for multipart content
        if ($payload->getParts()) {
            foreach ($payload->getParts() as $part) {
                if ($part->getMimeType() === 'text/plain' && $part->getBody()->getData()) {
                    $body .= base64_decode(str_replace(['-', '_'], ['+', '/'], $part->getBody()->getData()));
                } elseif ($part->getMimeType() === 'text/html' && empty($body) && $part->getBody()->getData()) {
                    // Use HTML if no plain text available
                    $htmlBody = base64_decode(str_replace(['-', '_'], ['+', '/'], $part->getBody()->getData()));
                    $body = strip_tags($htmlBody);
                }
                
                // Recursively check nested parts
                if ($part->getParts()) {
                    $body .= $this->extractMessageBody($part);
                }
            }
        }
        
        return $body;
    }

    /**
     * Extract order number from email content
     * 
     * @param string $body Email body
     * @param string $subject Email subject
     * @return string|null Order number
     */
    private function extractOrderNumber(string $body, string $subject): ?string
    {
        // Try multiple patterns for order number
        $patterns = [
            '/Order\s*#?\s*:?\s*([0-9]{3}-[0-9]{7}-[0-9]{7})/i',
            '/Order\s*#?\s*:?\s*([A-Z0-9\-]{15,20})/i',
            '/Order\s*(?:Number|ID)\s*:?\s*([A-Z0-9\-]{10,20})/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                return trim($matches[1]);
            }
            if (preg_match($pattern, $subject, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Extract invoice number from email content
     * 
     * @param string $body Email body
     * @param string $subject Email subject
     * @return string|null Invoice number
     */
    private function extractInvoiceNumber(string $body, string $subject): ?string
    {
        $patterns = [
            '/Invoice\s*#?\s*:?\s*([A-Z0-9\-]{8,20})/i',
            '/Receipt\s*#?\s*:?\s*([A-Z0-9\-]{8,20})/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Extract total amount from email content
     * 
     * @param string $body Email body
     * @return float|null Total amount
     */
    private function extractTotalAmount(string $body): ?float
    {
        $patterns = [
            '/Order\s*Total\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Total\s*:?\s*\$([0-9,]+\.?[0-9]*)/i',
            '/Grand\s*Total\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Amount\s*Charged\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Extract currency from email content
     * 
     * @param string $body Email body
     * @return string|null Currency code
     */
    private function extractCurrency(string $body): ?string
    {
        // Look for currency symbols and codes
        $patterns = [
            '/\$[0-9,]+\.?[0-9]*/' => 'USD',
            '/€[0-9,]+\.?[0-9]*/' => 'EUR',
            '/£[0-9,]+\.?[0-9]*/' => 'GBP',
            '/¥[0-9,]+\.?[0-9]*/' => 'JPY',
            '/CAD?\s*\$?[0-9,]+\.?[0-9]*/' => 'CAD'
        ];
        
        foreach ($patterns as $pattern => $currency) {
            if (preg_match($pattern, $body)) {
                return $currency;
            }
        }
        
        return null;
    }

    /**
     * Extract tax amount from email content
     * 
     * @param string $body Email body
     * @return float|null Tax amount
     */
    private function extractTaxAmount(string $body): ?float
    {
        $patterns = [
            '/Tax\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Sales\s*Tax\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/VAT\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Extract shipping amount from email content
     * 
     * @param string $body Email body
     * @return float|null Shipping amount
     */
    private function extractShippingAmount(string $body): ?float
    {
        $patterns = [
            '/Shipping\s*(?:&\s*Handling)?\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Delivery\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/S&H\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Extract items from email content
     * 
     * @param string $body Email body
     * @return InvoiceItem[] Array of invoice items
     */
    private function extractItemsFromEmail(string $body): array
    {
        $items = [];
        
        // This is a complex pattern - Amazon emails vary significantly
        // This is a basic implementation that can be enhanced
        
        $patterns = [
            '/([^$\n]+)\s+\$([0-9,]+\.?[0-9]*)/m',
            '/(.+?)\s+Qty:\s*([0-9]+)\s+\$([0-9,]+\.?[0-9]*)/m'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if (count($match) >= 3) {
                        $description = trim($match[1]);
                        $price = (float) str_replace(',', '', $match[2]);
                        $quantity = isset($match[3]) ? (int) $match[2] : 1;
                        
                        // Skip if description looks like a total or shipping line
                        if (stripos($description, 'total') !== false || 
                            stripos($description, 'shipping') !== false ||
                            stripos($description, 'tax') !== false) {
                            continue;
                        }
                        
                        $item = new InvoiceItem(
                            count($items) + 1,
                            $description,
                            $quantity,
                            $price,
                            $price * $quantity
                        );
                        
                        $items[] = $item;
                    }
                }
                break; // Use first successful pattern
            }
        }
        
        return $items;
    }

    /**
     * Extract payment information from email content
     * 
     * @param string $body Email body
     * @param float $amount Payment amount
     * @return Payment|null Payment object
     */
    private function extractPaymentFromEmail(string $body, float $amount): ?Payment
    {
        // Extract payment method
        $paymentMethod = 'Credit Card'; // Default
        
        $methods = [
            '/Visa\s*ending\s*in\s*([0-9]{4})/i' => 'Visa',
            '/MasterCard\s*ending\s*in\s*([0-9]{4})/i' => 'MasterCard',
            '/American\s*Express\s*ending\s*in\s*([0-9]{4})/i' => 'American Express',
            '/Discover\s*ending\s*in\s*([0-9]{4})/i' => 'Discover',
            '/PayPal/i' => 'PayPal',
            '/Gift\s*Card/i' => 'Gift Card',
            '/Amazon\s*Pay/i' => 'Amazon Pay'
        ];
        
        foreach ($methods as $pattern => $method) {
            if (preg_match($pattern, $body, $matches)) {
                $paymentMethod = $method;
                if (isset($matches[1])) {
                    $paymentMethod .= ' ending in ' . $matches[1];
                }
                break;
            }
        }
        
        return new Payment(
            $paymentMethod,
            $amount,
            'EMAIL-' . date('YmdHis')
        );
    }

    /**
     * Get email body from message data
     * 
     * @param array $email Email message data
     * @return string Email body
     */
    private function getEmailBody(array $email): string
    {
        return $email['body'] ?? '';
    }

    /**
     * Get email subject from message data
     * 
     * @param array $email Email message data
     * @return string Email subject
     */
    private function getEmailSubject(array $email): string
    {
        return $email['headers']['subject'] ?? '';
    }

    /**
     * Get email date from message data
     * 
     * @param array $email Email message data
     * @return \DateTime Email date
     */
    private function getEmailDate(array $email): \DateTime
    {
        if (isset($email['headers']['date'])) {
            return new \DateTime($email['headers']['date']);
        }
        
        // Fallback to internal date
        if (isset($email['internalDate'])) {
            $timestamp = (int) ($email['internalDate'] / 1000);
            return (new \DateTime())->setTimestamp($timestamp);
        }
        
        return new \DateTime();
    }

    /**
     * Save OAuth tokens to database
     * 
     * @param array $tokens Token data
     */
    private function saveTokens(array $tokens): void
    {
        $this->database->query(
            "INSERT INTO " . $this->database->getTablePrefix() . "gmail_credentials 
             (access_token, refresh_token, expires_at, token_type, scope, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE 
             access_token = VALUES(access_token),
             refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
             expires_at = VALUES(expires_at),
             updated_at = NOW()",
            [
                $tokens['access_token'],
                $tokens['refresh_token'] ?? null,
                isset($tokens['expires_in']) ? date('Y-m-d H:i:s', time() + $tokens['expires_in']) : null,
                $tokens['token_type'] ?? 'Bearer',
                $tokens['scope'] ?? 'https://www.googleapis.com/auth/gmail.readonly'
            ]
        );
    }

    /**
     * Save refreshed access token
     */
    private function saveRefreshedToken(): void
    {
        $token = $this->googleClient->getAccessToken();
        $this->saveTokens($token);
    }

    /**
     * Load search patterns from database/config
     */
    private function loadSearchPatterns(): void
    {
        try {
            $result = $this->database->query(
                "SELECT pattern_type, pattern_value FROM " . 
                $this->database->getTablePrefix() . "email_search_patterns 
                WHERE active = 1 ORDER BY priority ASC"
            );
            
            while ($row = $this->database->fetch($result)) {
                $type = $row['pattern_type'];
                if (!isset($this->searchPatterns[$type])) {
                    $this->searchPatterns[$type] = [];
                }
                $this->searchPatterns[$type][] = $row['pattern_value'];
            }
        } catch (\Exception $e) {
            // Use default patterns if database query fails
        }
    }

    /**
     * Log email processing activity
     * 
     * @param string $emailId Email ID
     * @param string $status Processing status
     * @param string $details Processing details
     */
    private function logEmailProcessing(string $emailId, string $status, string $details): void
    {
        $this->database->query(
            "INSERT INTO " . $this->database->getTablePrefix() . "email_processing_log 
             (email_id, status, details, processed_at) 
             VALUES (?, ?, ?, NOW())",
            [$emailId, $status, $details]
        );
    }

    /**
     * Update search patterns
     * 
     * @param string $type Pattern type
     * @param array $patterns Array of patterns
     */
    public function updateSearchPatterns(string $type, array $patterns): void
    {
        $this->database->beginTransaction();
        
        try {
            // Delete existing patterns of this type
            $this->database->query(
                "DELETE FROM " . $this->database->getTablePrefix() . "email_search_patterns 
                 WHERE pattern_type = ?",
                [$type]
            );
            
            // Insert new patterns
            foreach ($patterns as $priority => $pattern) {
                $this->database->query(
                    "INSERT INTO " . $this->database->getTablePrefix() . "email_search_patterns 
                     (pattern_type, pattern_value, priority, active) 
                     VALUES (?, ?, ?, 1)",
                    [$type, $pattern, $priority]
                );
            }
            
            $this->database->commit();
            
            // Update local patterns
            $this->searchPatterns[$type] = $patterns;
            
        } catch (\Exception $e) {
            $this->database->rollback();
            throw $e;
        }
    }

    /**
     * Get current search patterns
     * 
     * @return array Search patterns
     */
    public function getSearchPatterns(): array
    {
        return $this->searchPatterns;
    }

    /**
     * Test Gmail connection
     * 
     * @return array Test result
     */
    public function testConnection(): array
    {
        try {
            if (!$this->gmailService) {
                throw new \Exception('Gmail service not initialized');
            }
            
            // Try to get user profile
            $profile = $this->gmailService->users->getProfile('me');
            
            return [
                'success' => true,
                'email' => $profile->getEmailAddress(),
                'messages_total' => $profile->getMessagesTotal(),
                'threads_total' => $profile->getThreadsTotal()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
