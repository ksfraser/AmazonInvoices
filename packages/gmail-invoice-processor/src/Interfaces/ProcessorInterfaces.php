<?php

declare(strict_types=1);

namespace AmazonInvoices\GmailProcessor\Interfaces;

/**
 * Storage Interface for Gmail Processing
 * 
 * Abstracts storage operations for the Gmail processor
 * allowing different storage backends (database, file, etc.)
 */
interface StorageInterface
{
    /**
     * Store processed email data
     */
    public function storeEmailLog(array $emailData): int;
    
    /**
     * Check if email already processed
     */
    public function isEmailProcessed(string $messageId): bool;
    
    /**
     * Store extracted invoice data
     */
    public function storeInvoice(array $invoiceData): int;
    
    /**
     * Check for duplicate invoices
     */
    public function findDuplicateInvoice(array $invoiceData): ?array;
    
    /**
     * Get email search patterns
     */
    public function getSearchPatterns(): array;
    
    /**
     * Get Gmail credentials
     */
    public function getGmailCredentials(): array;
}

/**
 * OAuth Interface for Gmail Authentication
 */
interface OAuthInterface
{
    /**
     * Get authorization URL
     */
    public function getAuthUrl(string $clientId, string $redirectUri, array $scopes): string;
    
    /**
     * Exchange authorization code for tokens
     */
    public function exchangeAuthCode(string $code, string $clientId, string $clientSecret, string $redirectUri): array;
    
    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken, string $clientId, string $clientSecret): array;
}

/**
 * Email Parser Interface
 */
interface EmailParserInterface
{
    /**
     * Parse Amazon invoice email
     */
    public function parseAmazonEmail(array $emailData): ?array;
    
    /**
     * Extract invoice data from email content
     */
    public function extractInvoiceData(string $emailContent, array $attachments = []): ?array;
}
