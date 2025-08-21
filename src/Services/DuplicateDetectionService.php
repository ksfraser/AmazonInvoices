<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

/**
 * Duplicate Detection Service
 * 
 * Detects duplicate invoices across all import methods (SP-API, Email, PDF)
 * using multiple matching criteria and fuzzy matching techniques
 * 
 * @package AmazonInvoices\Services
 * @author  Assistant
 * @since   1.0.0
 */
class DuplicateDetectionService
{
    /**
     * @var DatabaseRepositoryInterface
     */
    private $database;
    
    public function __construct(DatabaseRepositoryInterface $database)
    {
        $this->database = $database;
    }
    
    /**
     * Check for duplicate invoices across all import sources
     * 
     * @param array $invoiceData Invoice data to check
     * @return array|null Duplicate information if found, null otherwise
     */
    public function findDuplicateInvoice(array $invoiceData): ?array
    {
        $duplicates = [];
        
        // Check by Amazon order number (strongest match)
        if (!empty($invoiceData['order_number'])) {
            $duplicate = $this->findByOrderNumber($invoiceData['order_number']);
            if ($duplicate) {
                $duplicates[] = $duplicate;
            }
        }
        
        // Check by invoice number
        if (!empty($invoiceData['invoice_number'])) {
            $duplicate = $this->findByInvoiceNumber($invoiceData['invoice_number']);
            if ($duplicate) {
                $duplicates[] = $duplicate;
            }
        }
        
        // Check by combination of date, total, and shipping address
        $duplicate = $this->findByDateTotalAddress($invoiceData);
        if ($duplicate) {
            $duplicates[] = $duplicate;
        }
        
        // Check by item combination (for invoices without clear identifiers)
        $duplicate = $this->findByItemCombination($invoiceData);
        if ($duplicate) {
            $duplicates[] = $duplicate;
        }
        
        // Return the most confident match
        if (!empty($duplicates)) {
            // Sort by confidence score and return highest
            usort($duplicates, function($a, $b) {
                return $b['confidence_score'] <=> $a['confidence_score'];
            });
            
            return $duplicates[0];
        }
        
        return null;
    }
    
    /**
     * Find duplicate by Amazon order number
     */
    private function findByOrderNumber(string $orderNumber): ?array
    {
        $escapedOrderNumber = $this->database->escape($orderNumber);
        
        // Check in staging table
        $query = "SELECT 'staging' as source_table, id, invoice_number, invoice_total, 
                         invoice_date, created_at, 'amazon_staging' as source
                  FROM " . TB_PREF . "amazon_invoices_staging 
                  WHERE order_number = '$escapedOrderNumber'";
        
        $result = $this->database->query($query);
        $row = $this->database->fetch($result);
        
        if ($row) {
            $row['confidence_score'] = 100; // Highest confidence for order number match
            $row['match_criteria'] = 'order_number';
            return $row;
        }
        
        // Check in email logs
        $query = "SELECT 'email' as source_table, id, gmail_message_id, 
                         email_subject, processed_date, 'email_import' as source
                  FROM " . TB_PREF . "amazon_email_logs 
                  WHERE extracted_data LIKE '%\"order_number\":\"$escapedOrderNumber\"%'";
        
        $result = $this->database->query($query);
        $row = $this->database->fetch($result);
        
        if ($row) {
            $row['confidence_score'] = 100;
            $row['match_criteria'] = 'order_number';
            return $row;
        }
        
        // Check in PDF logs
        $query = "SELECT 'pdf' as source_table, id, pdf_file_path, 
                         processed_date, 'pdf_import' as source
                  FROM " . TB_PREF . "amazon_pdf_logs 
                  WHERE extracted_data LIKE '%\"order_number\":\"$escapedOrderNumber\"%'";
        
        $result = $this->database->query($query);
        $row = $this->database->fetch($result);
        
        if ($row) {
            $row['confidence_score'] = 100;
            $row['match_criteria'] = 'order_number';
            return $row;
        }
        
        return null;
    }
    
    /**
     * Find duplicate by invoice number
     */
    private function findByInvoiceNumber(string $invoiceNumber): ?array
    {
        $escapedInvoiceNumber = $this->database->escape($invoiceNumber);
        
        // Check in staging table
        $query = "SELECT 'staging' as source_table, id, order_number, invoice_total, 
                         invoice_date, created_at, 'amazon_staging' as source
                  FROM " . TB_PREF . "amazon_invoices_staging 
                  WHERE invoice_number = '$escapedInvoiceNumber'";
        
        $result = $this->database->query($query);
        $row = $this->database->fetch($result);
        
        if ($row) {
            $row['confidence_score'] = 95; // High confidence for invoice number match
            $row['match_criteria'] = 'invoice_number';
            return $row;
        }
        
        // Check other sources similarly...
        return null;
    }
    
    /**
     * Find duplicate by date, total, and shipping address combination
     */
    private function findByDateTotalAddress(array $invoiceData): ?array
    {
        if (empty($invoiceData['invoice_date']) || empty($invoiceData['invoice_total'])) {
            return null;
        }
        
        $invoiceDate = $invoiceData['invoice_date'];
        $invoiceTotal = $invoiceData['invoice_total'];
        $tolerance = 0.01; // Allow small differences in total due to rounding
        
        // Create date range (same day)
        $dateStart = date('Y-m-d 00:00:00', strtotime($invoiceDate));
        $dateEnd = date('Y-m-d 23:59:59', strtotime($invoiceDate));
        
        // Check staging table
        $query = "SELECT 'staging' as source_table, id, invoice_number, order_number,
                         invoice_total, invoice_date, 'amazon_staging' as source
                  FROM " . TB_PREF . "amazon_invoices_staging 
                  WHERE invoice_date BETWEEN '$dateStart' AND '$dateEnd'
                  AND ABS(invoice_total - $invoiceTotal) <= $tolerance";
        
        $result = $this->database->query($query);
        $row = $this->database->fetch($result);
        
        if ($row) {
            $confidence = $this->calculateDateTotalConfidence($invoiceData, $row);
            $row['confidence_score'] = $confidence;
            $row['match_criteria'] = 'date_total_address';
            return $row;
        }
        
        return null;
    }
    
    /**
     * Find duplicate by item combination
     */
    private function findByItemCombination(array $invoiceData): ?array
    {
        if (empty($invoiceData['items']) || !is_array($invoiceData['items'])) {
            return null;
        }
        
        // Create a signature based on items
        $itemSignature = $this->createItemSignature($invoiceData['items']);
        
        // Check for invoices with similar item combinations
        // This is more complex and would require comparing item lists
        // For now, we'll do a simple check based on the number of items and total
        
        $itemCount = count($invoiceData['items']);
        $invoiceTotal = $invoiceData['invoice_total'] ?? 0;
        $tolerance = max($invoiceTotal * 0.05, 1.00); // 5% tolerance or $1
        
        $query = "SELECT s.*, 
                         (SELECT COUNT(*) FROM " . TB_PREF . "amazon_invoice_items_staging 
                          WHERE staging_invoice_id = s.id) as item_count
                  FROM " . TB_PREF . "amazon_invoices_staging s
                  WHERE ABS(s.invoice_total - $invoiceTotal) <= $tolerance
                  HAVING item_count = $itemCount";
        
        $result = $this->database->query($query);
        
        while ($row = $this->database->fetch($result)) {
            // Get items for detailed comparison
            $existingItems = $this->getInvoiceItems($row['id']);
            $similarity = $this->calculateItemSimilarity($invoiceData['items'], $existingItems);
            
            if ($similarity >= 0.8) { // 80% similarity threshold
                $row['confidence_score'] = (int)($similarity * 75); // Max 75 for item matching
                $row['match_criteria'] = 'item_combination';
                $row['source'] = 'amazon_staging';
                return $row;
            }
        }
        
        return null;
    }
    
    /**
     * Calculate confidence score for date/total match
     */
    private function calculateDateTotalConfidence(array $newInvoice, array $existingInvoice): int
    {
        $confidence = 70; // Base confidence for date/total match
        
        // Boost confidence if totals are exact match
        if (abs($newInvoice['invoice_total'] - $existingInvoice['invoice_total']) < 0.01) {
            $confidence += 10;
        }
        
        // Boost confidence if we have shipping address match
        if (!empty($newInvoice['shipping_address']) && !empty($existingInvoice['shipping_address'])) {
            $addressSimilarity = $this->calculateAddressSimilarity(
                $newInvoice['shipping_address'], 
                $existingInvoice['shipping_address']
            );
            $confidence += (int)($addressSimilarity * 15);
        }
        
        return min($confidence, 90); // Cap at 90 for this type of match
    }
    
    /**
     * Create item signature for comparison
     */
    private function createItemSignature(array $items): string
    {
        $signatures = [];
        
        foreach ($items as $item) {
            $signature = '';
            
            // Use ASIN if available (strongest identifier)
            if (!empty($item['asin'])) {
                $signature .= 'A:' . $item['asin'];
            }
            
            // Use SKU if available
            if (!empty($item['sku'])) {
                $signature .= 'S:' . $item['sku'];
            }
            
            // Use product name (normalized)
            if (!empty($item['product_name'])) {
                $normalized = $this->normalizeProductName($item['product_name']);
                $signature .= 'P:' . substr($normalized, 0, 50);
            }
            
            // Include quantity and price
            $quantity = $item['quantity'] ?? 1;
            $price = $item['unit_price'] ?? 0;
            $signature .= "Q:{$quantity}:PR:" . number_format($price, 2);
            
            $signatures[] = $signature;
        }
        
        // Sort signatures for consistent comparison
        sort($signatures);
        
        return md5(implode('|', $signatures));
    }
    
    /**
     * Calculate similarity between two item lists
     */
    private function calculateItemSimilarity(array $items1, array $items2): float
    {
        if (count($items1) !== count($items2)) {
            return 0.0;
        }
        
        $matches = 0;
        $total = count($items1);
        
        foreach ($items1 as $item1) {
            $bestMatch = 0.0;
            
            foreach ($items2 as $item2) {
                $similarity = $this->calculateSingleItemSimilarity($item1, $item2);
                $bestMatch = max($bestMatch, $similarity);
            }
            
            if ($bestMatch >= 0.8) { // Consider it a match if 80% similar
                $matches++;
            }
        }
        
        return $total > 0 ? $matches / $total : 0.0;
    }
    
    /**
     * Calculate similarity between two individual items
     */
    private function calculateSingleItemSimilarity(array $item1, array $item2): float
    {
        $score = 0.0;
        $factors = 0;
        
        // ASIN match (strongest)
        if (!empty($item1['asin']) && !empty($item2['asin'])) {
            $score += ($item1['asin'] === $item2['asin']) ? 1.0 : 0.0;
            $factors++;
        }
        
        // SKU match
        if (!empty($item1['sku']) && !empty($item2['sku'])) {
            $score += ($item1['sku'] === $item2['sku']) ? 1.0 : 0.0;
            $factors++;
        }
        
        // Product name similarity
        if (!empty($item1['product_name']) && !empty($item2['product_name'])) {
            $nameSimilarity = $this->calculateTextSimilarity(
                $item1['product_name'], 
                $item2['product_name']
            );
            $score += $nameSimilarity;
            $factors++;
        }
        
        // Price similarity (within 5%)
        if (isset($item1['unit_price']) && isset($item2['unit_price'])) {
            $price1 = (float)$item1['unit_price'];
            $price2 = (float)$item2['unit_price'];
            
            if ($price1 > 0 && $price2 > 0) {
                $priceDiff = abs($price1 - $price2) / max($price1, $price2);
                $priceScore = $priceDiff <= 0.05 ? 1.0 : max(0.0, 1.0 - ($priceDiff * 2));
                $score += $priceScore;
                $factors++;
            }
        }
        
        // Quantity match
        if (isset($item1['quantity']) && isset($item2['quantity'])) {
            $score += ($item1['quantity'] == $item2['quantity']) ? 1.0 : 0.0;
            $factors++;
        }
        
        return $factors > 0 ? $score / $factors : 0.0;
    }
    
    /**
     * Calculate text similarity using Levenshtein distance
     */
    private function calculateTextSimilarity(string $text1, string $text2): float
    {
        $text1 = $this->normalizeProductName($text1);
        $text2 = $this->normalizeProductName($text2);
        
        $maxLen = max(strlen($text1), strlen($text2));
        if ($maxLen === 0) {
            return 1.0;
        }
        
        $distance = levenshtein($text1, $text2);
        return max(0.0, 1.0 - ($distance / $maxLen));
    }
    
    /**
     * Calculate address similarity
     */
    private function calculateAddressSimilarity(string $address1, string $address2): float
    {
        // Normalize addresses
        $addr1 = $this->normalizeAddress($address1);
        $addr2 = $this->normalizeAddress($address2);
        
        // Simple text similarity for now
        return $this->calculateTextSimilarity($addr1, $addr2);
    }
    
    /**
     * Normalize product name for comparison
     */
    private function normalizeProductName(string $name): string
    {
        // Convert to lowercase
        $name = strtolower($name);
        
        // Remove common words and punctuation
        $name = preg_replace('/[^\w\s]/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        
        // Remove common stop words
        $stopWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'among', 'within', 'without', 'under', 'over'];
        $words = explode(' ', $name);
        $words = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
        
        return implode(' ', $words);
    }
    
    /**
     * Normalize address for comparison
     */
    private function normalizeAddress(string $address): string
    {
        // Convert to lowercase and remove extra whitespace
        $address = strtolower(trim($address));
        $address = preg_replace('/\s+/', ' ', $address);
        
        // Common address abbreviations
        $replacements = [
            'street' => 'st',
            'avenue' => 'ave',
            'boulevard' => 'blvd',
            'drive' => 'dr',
            'road' => 'rd',
            'lane' => 'ln',
            'court' => 'ct',
            'apartment' => 'apt',
            'suite' => 'ste'
        ];
        
        foreach ($replacements as $full => $abbr) {
            $address = str_replace($full, $abbr, $address);
        }
        
        return $address;
    }
    
    /**
     * Get invoice items for comparison
     */
    private function getInvoiceItems(int $invoiceId): array
    {
        $query = "SELECT product_name, asin, sku, quantity, unit_price, total_price
                  FROM " . TB_PREF . "amazon_invoice_items_staging 
                  WHERE staging_invoice_id = $invoiceId";
        
        $result = $this->database->query($query);
        $items = [];
        
        while ($row = $this->database->fetch($result)) {
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Mark invoice as duplicate
     */
    public function markAsDuplicate(string $sourceType, int $sourceId, array $duplicateInfo): bool
    {
        $duplicateData = json_encode($duplicateInfo);
        
        switch ($sourceType) {
            case 'email':
                $query = "UPDATE " . TB_PREF . "amazon_email_logs 
                          SET processing_status = 'duplicate', 
                              error_message = 'Duplicate of: {$duplicateInfo['source']}',
                              updated_at = NOW()
                          WHERE id = $sourceId";
                break;
                
            case 'pdf':
                $query = "UPDATE " . TB_PREF . "amazon_pdf_logs 
                          SET processing_status = 'duplicate',
                              error_message = 'Duplicate of: {$duplicateInfo['source']}',
                              updated_at = NOW()
                          WHERE id = $sourceId";
                break;
                
            case 'staging':
                $query = "UPDATE " . TB_PREF . "amazon_invoices_staging 
                          SET status = 'duplicate',
                              notes = 'Duplicate of: {$duplicateInfo['source']}',
                              updated_at = NOW()
                          WHERE id = $sourceId";
                break;
                
            default:
                return false;
        }
        
        $this->database->query($query);
        return true;
    }
}
