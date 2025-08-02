<?php

declare(strict_types=1);

namespace AmazonInvoices\Interfaces;

/**
 * Item matching service interface
 * 
 * Defines the contract for matching Amazon items to system stock items
 * 
 * @package AmazonInvoices\Interfaces
 * @author  Your Name
 * @since   1.0.0
 */
interface ItemMatchingServiceInterface
{
    /**
     * Find matching stock item for Amazon product
     * 
     * @param string|null $asin Amazon Standard Identification Number
     * @param string|null $sku Stock Keeping Unit
     * @param string $productName Product name/description
     * @return string|null Matched stock item ID or null if no match
     * @throws \Exception When matching fails
     */
    public function findMatchingStockItem(?string $asin, ?string $sku, string $productName): ?string;

    /**
     * Add a new matching rule
     * 
     * @param string $matchType Type of match (asin, sku, product_name, keyword)
     * @param string $matchValue Value to match against
     * @param string $stockId Target stock item ID
     * @param int $priority Rule priority (lower = higher priority)
     * @return int Rule ID
     * @throws \Exception When adding rule fails
     */
    public function addMatchingRule(string $matchType, string $matchValue, string $stockId, int $priority = 1): int;

    /**
     * Get all matching rules
     * 
     * @param bool $activeOnly Whether to return only active rules
     * @return array Array of matching rules
     * @throws \Exception When query fails
     */
    public function getMatchingRules(bool $activeOnly = true): array;

    /**
     * Update matching rule status
     * 
     * @param int $ruleId Rule ID
     * @param bool $active Whether rule is active
     * @return bool True on success
     * @throws \Exception When update fails
     */
    public function updateRuleStatus(int $ruleId, bool $active): bool;

    /**
     * Delete matching rule
     * 
     * @param int $ruleId Rule ID
     * @return bool True on success
     * @throws \Exception When delete fails
     */
    public function deleteRule(int $ruleId): bool;

    /**
     * Auto-match items for an invoice
     * 
     * @param int $invoiceId Invoice ID
     * @return int Number of items matched
     * @throws \Exception When auto-matching fails
     */
    public function autoMatchInvoiceItems(int $invoiceId): int;

    /**
     * Get suggested stock items based on product name
     * 
     * @param string $productName Product name to search for
     * @param int $limit Maximum number of suggestions
     * @return array Array of suggested stock items
     * @throws \Exception When search fails
     */
    public function getSuggestedStockItems(string $productName, int $limit = 10): array;
}
