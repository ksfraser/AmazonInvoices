<?php

declare(strict_types=1);

namespace AmazonInvoices\Interfaces;

use AmazonInvoices\Models\Invoice;
use AmazonInvoices\Models\InvoiceItem;
use AmazonInvoices\Models\Payment;

/**
 * Amazon invoice downloader interface
 * 
 * Defines the contract for downloading invoices from Amazon
 * Implementations can use different methods (API, scraping, file import, etc.)
 * 
 * @package AmazonInvoices\Interfaces
 * @author  Your Name
 * @since   1.0.0
 */
interface InvoiceDownloaderInterface
{
    /**
     * Download invoices for a given date range
     * 
     * @param \DateTime $startDate Start date for invoice download
     * @param \DateTime $endDate   End date for invoice download
     * @return Invoice[] Array of downloaded invoices
     * @throws \Exception When download fails
     */
    public function downloadInvoices(\DateTime $startDate, \DateTime $endDate): array;

    /**
     * Parse invoice data from raw source (PDF, HTML, etc.)
     * 
     * @param string $rawData Raw invoice data
     * @param string $format  Data format (pdf, html, json, etc.)
     * @return Invoice Parsed invoice object
     * @throws \Exception When parsing fails
     */
    public function parseInvoiceData(string $rawData, string $format = 'pdf'): Invoice;

    /**
     * Validate invoice data integrity
     * 
     * @param Invoice $invoice Invoice to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateInvoice(Invoice $invoice): array;

    /**
     * Set authentication credentials
     * 
     * @param array $credentials Authentication credentials
     * @return void
     */
    public function setCredentials(array $credentials): void;

    /**
     * Test connection to Amazon services
     * 
     * @return bool True if connection successful
     */
    public function testConnection(): bool;
}
