<?php

declare(strict_types=1);

namespace AmazonInvoices\GmailProcessor;

use AmazonInvoices\GmailProcessor\Interfaces\StorageInterface;
use AmazonInvoices\GmailProcessor\Interfaces\OAuthInterface;
use AmazonInvoices\GmailProcessor\Interfaces\EmailParserInterface;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;

/**
 * Gmail Invoice Processor
 * 
 * Standalone library for processing Amazon invoices from Gmail
 * Framework-agnostic with dependency injection
 */
class GmailInvoiceProcessor
{
    private StorageInterface $storage;
    private OAuthInterface $oauth;
    private EmailParserInterface $parser;
    private Google_Client $googleClient;
    private ?Google_Service_Gmail $gmailService = null;
    
    public function __construct(
        StorageInterface $storage,
        OAuthInterface $oauth,
        EmailParserInterface $parser
    ) {
        $this->storage = $storage;
        $this->oauth = $oauth;
        $this->parser = $parser;
        $this->googleClient = new Google_Client();
    }
    
    /**
     * Process emails for Amazon invoices
     */
    public function processEmails(array $credentials, array $options = []): array
    {
        $this->setupGoogleClient($credentials);
        $this->gmailService = new Google_Service_Gmail($this->googleClient);
        
        $patterns = $this->storage->getSearchPatterns();
        $results = [];
        
        foreach ($patterns as $pattern) {
            if (!$pattern['is_active']) {
                continue;
            }
            
            $query = $this->buildSearchQuery($pattern);
            $messages = $this->searchEmails($query, $options);
            
            foreach ($messages as $message) {
                try {
                    $result = $this->processMessage($message);
                    if ($result) {
                        $results[] = $result;
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'status' => 'error',
                        'message_id' => $message->getId(),
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Process a single Gmail message
     */
    public function processMessage(Google_Service_Gmail_Message $message): ?array
    {
        $messageId = $message->getId();
        
        // Check if already processed
        if ($this->storage->isEmailProcessed($messageId)) {
            return [
                'status' => 'duplicate',
                'message_id' => $messageId,
                'message' => 'Email already processed'
            ];
        }
        
        // Get full message details
        $fullMessage = $this->gmailService->users_messages->get('me', $messageId);
        $payload = $fullMessage->getPayload();
        $headers = $payload->getHeaders();
        
        // Extract email metadata
        $emailData = [
            'gmail_message_id' => $messageId,
            'email_subject' => $this->getHeaderValue($headers, 'Subject'),
            'email_from' => $this->getHeaderValue($headers, 'From'),
            'email_date' => $this->getHeaderValue($headers, 'Date'),
            'raw_email_data' => json_encode($fullMessage->toSimpleObject())
        ];
        
        // Extract email content
        $emailContent = $this->extractEmailContent($payload);
        $attachments = $this->extractAttachments($payload, $messageId);
        
        // Parse for Amazon invoice data
        $invoiceData = $this->parser->parseAmazonEmail([
            'content' => $emailContent,
            'attachments' => $attachments,
            'headers' => $headers
        ]);
        
        if (!$invoiceData) {
            // Store as processed but failed
            $emailData['processing_status'] = 'error';
            $emailData['error_message'] = 'No invoice data found in email';
            $this->storage->storeEmailLog($emailData);
            
            return [
                'status' => 'no_invoice_data',
                'message_id' => $messageId,
                'message' => 'No Amazon invoice data found'
            ];
        }
        
        // Check for duplicates across all import methods
        $duplicate = $this->storage->findDuplicateInvoice($invoiceData);
        if ($duplicate) {
            $emailData['processing_status'] = 'duplicate';
            $emailData['error_message'] = 'Duplicate invoice found: ' . $duplicate['source'];
            $this->storage->storeEmailLog($emailData);
            
            return [
                'status' => 'duplicate_invoice',
                'message_id' => $messageId,
                'duplicate_source' => $duplicate,
                'message' => 'Invoice already imported from another source'
            ];
        }
        
        // Store successful processing
        $emailData['processing_status'] = 'completed';
        $emailData['extracted_data'] = json_encode($invoiceData);
        $emailLogId = $this->storage->storeEmailLog($emailData);
        
        // Store invoice data
        $invoiceData['source_type'] = 'email';
        $invoiceData['source_id'] = $messageId;
        $invoiceData['email_log_id'] = $emailLogId;
        $invoiceId = $this->storage->storeInvoice($invoiceData);
        
        return [
            'status' => 'success',
            'message_id' => $messageId,
            'email_log_id' => $emailLogId,
            'invoice_id' => $invoiceId,
            'invoice_data' => $invoiceData
        ];
    }
    
    /**
     * Setup Google Client with credentials
     */
    private function setupGoogleClient(array $credentials): void
    {
        $this->googleClient->setClientId($credentials['client_id']);
        $this->googleClient->setClientSecret($credentials['client_secret']);
        $this->googleClient->addScope($credentials['scope'] ?? 'https://www.googleapis.com/auth/gmail.readonly');
        
        if (!empty($credentials['access_token'])) {
            $this->googleClient->setAccessToken($credentials['access_token']);
            
            if ($this->googleClient->isAccessTokenExpired() && !empty($credentials['refresh_token'])) {
                $this->googleClient->refreshToken($credentials['refresh_token']);
                
                // Update stored tokens (callback to storage)
                $newTokens = $this->googleClient->getAccessToken();
                // Storage implementation should handle token updates
            }
        }
    }
    
    /**
     * Build search query from pattern
     */
    private function buildSearchQuery(array $pattern): string
    {
        $query = '';
        
        switch ($pattern['pattern_type']) {
            case 'subject':
                $query = 'subject:' . $pattern['pattern_value'];
                break;
            case 'from':
                $query = 'from:' . $pattern['pattern_value'];
                break;
            case 'body':
                $query = $pattern['pattern_value'];
                break;
            case 'label':
                $query = 'label:' . $pattern['pattern_value'];
                break;
        }
        
        return $query;
    }
    
    /**
     * Search emails using Gmail API
     */
    private function searchEmails(string $query, array $options = []): array
    {
        $maxResults = $options['max_results'] ?? 50;
        $messages = [];
        
        try {
            $results = $this->gmailService->users_messages->listUsersMessages('me', [
                'q' => $query,
                'maxResults' => $maxResults
            ]);
            
            if ($results->getMessages()) {
                $messages = $results->getMessages();
            }
        } catch (\Exception $e) {
            throw new \Exception("Gmail search failed: " . $e->getMessage());
        }
        
        return $messages;
    }
    
    /**
     * Extract email content from payload
     */
    private function extractEmailContent($payload): string
    {
        $content = '';
        
        if ($payload->getBody() && $payload->getBody()->getData()) {
            $content = base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'));
        } else if ($payload->getParts()) {
            foreach ($payload->getParts() as $part) {
                if ($part->getMimeType() === 'text/plain' || $part->getMimeType() === 'text/html') {
                    if ($part->getBody() && $part->getBody()->getData()) {
                        $content .= base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    }
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Extract attachments from email
     */
    private function extractAttachments($payload, string $messageId): array
    {
        $attachments = [];
        
        if ($payload->getParts()) {
            foreach ($payload->getParts() as $part) {
                if ($part->getFilename() && $part->getBody()->getAttachmentId()) {
                    $attachment = $this->gmailService->users_messages_attachments->get(
                        'me',
                        $messageId,
                        $part->getBody()->getAttachmentId()
                    );
                    
                    $attachments[] = [
                        'filename' => $part->getFilename(),
                        'mime_type' => $part->getMimeType(),
                        'data' => base64_decode(strtr($attachment->getData(), '-_', '+/'))
                    ];
                }
            }
        }
        
        return $attachments;
    }
    
    /**
     * Get header value by name
     */
    private function getHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (strtolower($header->getName()) === strtolower($name)) {
                return $header->getValue();
            }
        }
        return null;
    }
}
