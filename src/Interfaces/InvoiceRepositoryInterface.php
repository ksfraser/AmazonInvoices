<?php

declare(strict_types=1);

namespace AmazonInvoices\Interfaces;

use AmazonInvoices\Models\Invoice;

/**
 * Invoice repository interface
 * 
 * Defines the contract for persisting and retrieving invoices
 * 
 * @package AmazonInvoices\Interfaces
 * @author  Your Name
 * @since   1.0.0
 */
interface InvoiceRepositoryInterface
{
    /**
     * Save an invoice to the repository
     * 
     * @param Invoice $invoice Invoice to save
     * @return Invoice Saved invoice with populated ID
     * @throws \Exception When save operation fails
     */
    public function save(Invoice $invoice): Invoice;

    /**
     * Find an invoice by ID
     * 
     * @param int $id Invoice ID
     * @return Invoice|null Found invoice or null if not found
     * @throws \Exception When query fails
     */
    public function findById(int $id): ?Invoice;

    /**
     * Find an invoice by invoice number
     * 
     * @param string $invoiceNumber Amazon invoice number
     * @return Invoice|null Found invoice or null if not found
     * @throws \Exception When query fails
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice;

    /**
     * Find invoices by status
     * 
     * @param string $status Invoice status
     * @param int|null $limit Maximum number of results
     * @return Invoice[] Array of invoices
     * @throws \Exception When query fails
     */
    public function findByStatus(string $status, ?int $limit = null): array;

    /**
     * Find invoices by date range
     * 
     * @param \DateTime $startDate Start date
     * @param \DateTime $endDate End date
     * @param int|null $limit Maximum number of results
     * @return Invoice[] Array of invoices
     * @throws \Exception When query fails
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate, ?int $limit = null): array;

    /**
     * Get all invoices with optional filters
     * 
     * @param array $filters Optional filters (status, date_from, date_to, etc.)
     * @param int|null $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return Invoice[] Array of invoices
     * @throws \Exception When query fails
     */
    public function findAll(array $filters = [], ?int $limit = null, int $offset = 0): array;

    /**
     * Count invoices with optional filters
     * 
     * @param array $filters Optional filters (status, date_from, date_to, etc.)
     * @return int Count of invoices
     * @throws \Exception When query fails
     */
    public function count(array $filters = []): int;

    /**
     * Update invoice status
     * 
     * @param int $id Invoice ID
     * @param string $status New status
     * @param string|null $notes Optional notes
     * @param int|null $faTransactionNumber Optional FA transaction number
     * @return bool True on success
     * @throws \Exception When update fails
     */
    public function updateStatus(int $id, string $status, ?string $notes = null, ?int $faTransactionNumber = null): bool;

    /**
     * Delete an invoice
     * 
     * @param int $id Invoice ID
     * @return bool True on success
     * @throws \Exception When delete fails
     */
    public function delete(int $id): bool;

    /**
     * Check if invoice exists by invoice number
     * 
     * @param string $invoiceNumber Amazon invoice number
     * @return bool True if exists
     * @throws \Exception When query fails
     */
    public function existsByInvoiceNumber(string $invoiceNumber): bool;
}
