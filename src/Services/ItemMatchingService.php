<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use AmazonInvoices\Interfaces\ItemMatchingServiceInterface;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;
use AmazonInvoices\Models\InvoiceItem;

/**
 * Item Matching Service Implementation
 * 
 * Handles matching Amazon invoice items to existing stock items
 * Uses configurable matching rules and learning algorithms
 * 
 * @package AmazonInvoices\Services
 * @author  Your Name
 * @since   1.0.0
 */
class ItemMatchingService implements ItemMatchingServiceInterface
{
    /**
     * @var DatabaseRepositoryInterface Database repository
     */
    /**
     * @var DatabaseRepositoryInterface
     */
    private $database;

    /**
     * @var array Matching configuration
     */
    /**
     * @var array
     */
    private $config;

    /**
     * @var string Stock items table name
     */
    /**
     * @var string
     */
    private $stockTable;

    /**
     * @var string Matching rules table name
     */
    /**
     * @var string
     */
    private $rulesTable;

    /**
     * @var string Matching history table name
     */
    /**
     * @var string
     */
    private $historyTable;

    /**
     * Constructor
     * 
     * @param DatabaseRepositoryInterface $database Database repository
     * @param array                       $config   Matching configuration
     */
    public function __construct(DatabaseRepositoryInterface $database, array $config = [])
    {
        $this->database = $database;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $prefix = $database->getTablePrefix();
        $this->stockTable = $prefix . $this->config['stock_table'];
        $this->rulesTable = $prefix . 'amazon_item_matching_rules';
        $this->historyTable = $prefix . 'amazon_item_matching_history';
    }

    /**
     * {@inheritdoc}
     */
    public function findMatches(InvoiceItem $item, int $maxResults = 10): array
    {
        $matches = [];

        // Try exact ASIN match first
        if ($item->getAsin()) {
            $asinMatches = $this->findByAsin($item->getAsin(), $maxResults);
            foreach ($asinMatches as $match) {
                $match['match_type'] = 'asin';
                $match['confidence'] = 100;
                $matches[] = $match;
            }
        }

        // Try exact SKU match
        if ($item->getSku() && count($matches) < $maxResults) {
            $skuMatches = $this->findBySku($item->getSku(), $maxResults - count($matches));
            foreach ($skuMatches as $match) {
                $match['match_type'] = 'sku';
                $match['confidence'] = 95;
                $matches[] = $match;
            }
        }

        // Try product name matching
        if (count($matches) < $maxResults) {
            $nameMatches = $this->findByProductName(
                $item->getProductName(), 
                $maxResults - count($matches)
            );
            $matches = array_merge($matches, $nameMatches);
        }

        // Try custom matching rules
        if (count($matches) < $maxResults) {
            $ruleMatches = $this->findByCustomRules($item, $maxResults - count($matches));
            $matches = array_merge($matches, $ruleMatches);
        }

        // Sort by confidence score
        usort($matches, function($a, $b) {
            return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
        });

        return array_slice($matches, 0, $maxResults);
    }

    /**
     * {@inheritdoc}
     */
    public function suggestNewItem(InvoiceItem $item): array
    {
        // Generate suggested stock item details
        $suggestion = [
            'stock_id' => $this->generateStockId($item),
            'description' => $this->cleanProductName($item->getProductName()),
            'long_description' => $item->getProductName(),
            'category_id' => $this->suggestCategory($item),
            'units' => $this->suggestUnits($item),
            'material_cost' => $item->getUnitPrice(),
            'labour_cost' => 0,
            'overhead_cost' => 0,
            'dimension_id' => null,
            'dimension2_id' => null,
            'purchase_account' => $this->config['default_purchase_account'],
            'cogs_account' => $this->config['default_cogs_account'],
            'inventory_account' => $this->config['default_inventory_account'],
            'adjustment_account' => $this->config['default_adjustment_account'],
            'assembly_account' => $this->config['default_assembly_account'],
            'supplier_code' => $item->getAsin() ?: $item->getSku(),
            'supplier_reference' => $item->getAsin(),
            'inactive' => 0
        ];

        return $suggestion;
    }

    /**
     * {@inheritdoc}
     */
    public function findMatchingStockItem(?string $asin, ?string $sku, string $productName): ?string
    {
        $item = new InvoiceItem(1, $productName, 1, 0, 0);
        $item->setAsin($asin);
        $item->setSku($sku);
        
        $matches = $this->findMatches($item, 1);
        return !empty($matches) ? $matches[0]['stock_id'] : null;
    }

    /**
     * Save match for invoice item
     * 
     * @param InvoiceItem $item      Item to match
     * @param string      $stockId   Stock ID to match to
     * @param string      $matchType Type of match
     * @return bool True on success
     */
    public function saveMatch(InvoiceItem $item, string $stockId, string $matchType = 'manual'): bool
    {
        try {
            $this->database->beginTransaction();

            // Update the item
            $item->setFaStockId($stockId);
            $item->setMatched(true);
            $item->setMatchType($matchType);

            // Record the match in history
            $this->recordMatchHistory($item, $stockId, $matchType);

            // Update/create matching rule if this creates a pattern
            if ($matchType === 'manual') {
                $this->updateMatchingRules($item, $stockId);
            }

            $this->database->commit();
            return true;

        } catch (\Exception $e) {
            $this->database->rollback();
            throw new \Exception("Failed to save item match: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addMatchingRule(string $matchType, string $matchValue, string $stockId, int $priority = 1): int
    {
        $query = "INSERT INTO {$this->rulesTable} 
                  (rule_type, pattern, stock_id, confidence, priority, active, notes) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

        try {
            $this->database->query($query, [
                $matchType,
                $matchValue,
                $stockId,
                80, // Default confidence
                $priority,
                1, // Active by default
                'Added via API'
            ]);

            return $this->database->getLastInsertId();

        } catch (\Exception $e) {
            throw new \Exception("Failed to add matching rule: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMatchingRules(bool $activeOnly = true): array
    {
        $whereClause = $activeOnly ? 'WHERE r.active = 1' : '';
        
        $query = "SELECT r.*, s.description as stock_description 
                  FROM {$this->rulesTable} r
                  LEFT JOIN {$this->stockTable} s ON r.stock_id = s.stock_id
                  {$whereClause}
                  ORDER BY r.priority ASC, r.confidence DESC";

        $result = $this->database->query($query);
        return $this->database->fetchAll($result);
    }

    /**
     * {@inheritdoc}
     */
    public function updateRuleStatus(int $ruleId, bool $active): bool
    {
        $query = "UPDATE {$this->rulesTable} SET active = ? WHERE id = ?";
        
        try {
            $this->database->query($query, [$active ? 1 : 0, $ruleId]);
            return $this->database->getAffectedRows() > 0;
        } catch (\Exception $e) {
            throw new \Exception("Failed to update rule status: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRule(int $ruleId): bool
    {
        $query = "DELETE FROM {$this->rulesTable} WHERE id = ?";
        
        try {
            $this->database->query($query, [$ruleId]);
            return $this->database->getAffectedRows() > 0;
        } catch (\Exception $e) {
            throw new \Exception("Failed to delete rule: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function autoMatchInvoiceItems(int $invoiceId): int
    {
        // Get unmatched items for the invoice
        $query = "SELECT * FROM {$this->database->getTablePrefix()}amazon_invoice_items_staging 
                  WHERE staging_invoice_id = ? AND fa_item_matched = 0";
        
        $result = $this->database->query($query, [$invoiceId]);
        $items = $this->database->fetchAll($result);
        
        $matchedCount = 0;
        
        foreach ($items as $itemData) {
            $item = new InvoiceItem(
                (int) $itemData['line_number'],
                $itemData['product_name'],
                (int) $itemData['quantity'],
                (float) $itemData['unit_price'],
                (float) $itemData['total_price']
            );
            
            $item->setId((int) $itemData['id']);
            $item->setAsin($itemData['asin']);
            $item->setSku($itemData['sku']);
            
            $stockId = $this->findMatchingStockItem(
                $item->getAsin(), 
                $item->getSku(), 
                $item->getProductName()
            );
            
            if ($stockId) {
                $this->saveMatch($item, $stockId, 'auto');
                $matchedCount++;
            }
        }
        
        return $matchedCount;
    }

    /**
     * {@inheritdoc}
     */
    public function getSuggestedStockItems(string $productName, int $limit = 10): array
    {
        $item = new InvoiceItem(1, $productName, 1, 0, 0);
        return $this->findMatches($item, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function getMatchingHistory(array $filters = [], int $limit = 100): array
    {
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($filters['asin'])) {
            $whereConditions[] = 'asin = ?';
            $params[] = $filters['asin'];
        }

        if (!empty($filters['stock_id'])) {
            $whereConditions[] = 'stock_id = ?';
            $params[] = $filters['stock_id'];
        }

        if (!empty($filters['match_type'])) {
            $whereConditions[] = 'match_type = ?';
            $params[] = $filters['match_type'];
        }

        $query = "SELECT h.*, s.description as stock_description 
                  FROM {$this->historyTable} h
                  LEFT JOIN {$this->stockTable} s ON h.stock_id = s.stock_id
                  WHERE " . implode(' AND ', $whereConditions) . "
                  ORDER BY h.created_at DESC
                  LIMIT ?";

        $params[] = $limit;

        $result = $this->database->query($query, $params);
        return $this->database->fetchAll($result);
    }

    /**
     * Find stock items by ASIN
     * 
     * @param string $asin       ASIN to search for
     * @param int    $maxResults Maximum results to return
     * @return array Array of matching stock items
     */
    private function findByAsin(string $asin, int $maxResults): array
    {
        // Look in supplier codes and references
        $query = "SELECT stock_id, description, long_description, units, material_cost
                  FROM {$this->stockTable}
                  WHERE (supplier_reference = ? OR stock_id LIKE ?)
                  AND inactive = 0
                  LIMIT ?";

        $result = $this->database->query($query, [$asin, "%{$asin}%", $maxResults]);
        return $this->database->fetchAll($result);
    }

    /**
     * Find stock items by SKU
     * 
     * @param string $sku        SKU to search for
     * @param int    $maxResults Maximum results to return
     * @return array Array of matching stock items
     */
    private function findBySku(string $sku, int $maxResults): array
    {
        $query = "SELECT stock_id, description, long_description, units, material_cost
                  FROM {$this->stockTable}
                  WHERE (stock_id = ? OR supplier_reference = ?)
                  AND inactive = 0
                  LIMIT ?";

        $result = $this->database->query($query, [$sku, $sku, $maxResults]);
        return $this->database->fetchAll($result);
    }

    /**
     * Find stock items by product name with fuzzy matching
     * 
     * @param string $productName Product name to search for
     * @param int    $maxResults  Maximum results to return
     * @return array Array of matching stock items with confidence scores
     */
    private function findByProductName(string $productName, int $maxResults): array
    {
        $cleanName = $this->cleanProductName($productName);
        $words = explode(' ', $cleanName);
        $words = array_filter($words, function($word) {
            return strlen($word) > 2; // Ignore short words
        });

        if (empty($words)) {
            return [];
        }

        // Build LIKE conditions for each significant word
        $likeConditions = [];
        $params = [];
        
        foreach ($words as $word) {
            $likeConditions[] = '(description LIKE ? OR long_description LIKE ?)';
            $params[] = "%{$word}%";
            $params[] = "%{$word}%";
        }

        $query = "SELECT stock_id, description, long_description, units, material_cost
                  FROM {$this->stockTable}
                  WHERE (" . implode(' OR ', $likeConditions) . ")
                  AND inactive = 0
                  LIMIT ?";

        $params[] = $maxResults * 2; // Get more results for scoring

        $result = $this->database->query($query, $params);
        $matches = $this->database->fetchAll($result);

        // Calculate confidence scores
        foreach ($matches as &$match) {
            $match['confidence'] = $this->calculateNameMatchConfidence(
                $productName, 
                $match['description'], 
                $match['long_description']
            );
            $match['match_type'] = 'name';
        }

        // Filter by minimum confidence and sort
        $matches = array_filter($matches, function($match) {
            return $match['confidence'] >= $this->config['min_name_confidence'];
        });

        usort($matches, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($matches, 0, $maxResults);
    }

    /**
     * Find matches using custom matching rules
     * 
     * @param InvoiceItem $item       Item to match
     * @param int         $maxResults Maximum results to return
     * @return array Array of matching stock items
     */
    private function findByCustomRules(InvoiceItem $item, int $maxResults): array
    {
        $query = "SELECT * FROM {$this->rulesTable} WHERE active = 1 ORDER BY confidence DESC";
        $result = $this->database->query($query);
        $rules = $this->database->fetchAll($result);

        $matches = [];

        foreach ($rules as $rule) {
            if (count($matches) >= $maxResults) {
                break;
            }

            $isMatch = false;
            $searchValue = '';

            switch ($rule['rule_type']) {
                case 'asin_pattern':
                    $searchValue = $item->getAsin();
                    break;
                case 'sku_pattern':
                    $searchValue = $item->getSku();
                    break;
                case 'name_pattern':
                    $searchValue = $item->getProductName();
                    break;
                case 'price_range':
                    $priceRange = json_decode($rule['pattern'], true);
                    $price = $item->getUnitPrice();
                    $isMatch = $price >= $priceRange['min'] && $price <= $priceRange['max'];
                    break;
            }

            if (!$isMatch && $searchValue) {
                $isMatch = $this->matchesPattern($searchValue, $rule['pattern']);
            }

            if ($isMatch) {
                // Get the stock item details
                $stockQuery = "SELECT stock_id, description, long_description, units, material_cost
                              FROM {$this->stockTable}
                              WHERE stock_id = ? AND inactive = 0";
                
                $stockResult = $this->database->query($stockQuery, [$rule['stock_id']]);
                $stockItem = $this->database->fetch($stockResult);

                if ($stockItem) {
                    $stockItem['match_type'] = 'rule';
                    $stockItem['confidence'] = (int) $rule['confidence'];
                    $stockItem['rule_id'] = $rule['id'];
                    $matches[] = $stockItem;
                }
            }
        }

        return $matches;
    }

    /**
     * Calculate confidence score for name matching
     * 
     * @param string $amazonName    Amazon product name
     * @param string $stockName     Stock item description
     * @param string $stockLongName Stock item long description
     * @return int Confidence score (0-100)
     */
    private function calculateNameMatchConfidence(string $amazonName, string $stockName, ?string $stockLongName = null): int
    {
        $amazonClean = strtolower($this->cleanProductName($amazonName));
        $stockClean = strtolower($this->cleanProductName($stockName));
        $stockLongClean = $stockLongName ? strtolower($this->cleanProductName($stockLongName)) : '';

        // Calculate similarity with both descriptions
        $shortSimilarity = $this->calculateStringSimilarity($amazonClean, $stockClean);
        $longSimilarity = $stockLongClean ? $this->calculateStringSimilarity($amazonClean, $stockLongClean) : 0;

        // Use the higher similarity score
        $similarity = max($shortSimilarity, $longSimilarity);

        // Convert to confidence score
        return min(100, (int) ($similarity * 100));
    }

    /**
     * Calculate string similarity using multiple algorithms
     * 
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score (0-1)
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        // Levenshtein distance similarity
        $maxLen = max(strlen($str1), strlen($str2));
        $levenshteinSim = $maxLen > 0 ? 1 - (levenshtein($str1, $str2) / $maxLen) : 1;

        // Word overlap similarity
        $words1 = explode(' ', $str1);
        $words2 = explode(' ', $str2);
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));
        $wordSim = count($union) > 0 ? count($intersection) / count($union) : 0;

        // Combined score (weighted average)
        return ($levenshteinSim * 0.7) + ($wordSim * 0.3);
    }

    /**
     * Clean product name for matching
     * 
     * @param string $name Product name to clean
     * @return string Cleaned name
     */
    private function cleanProductName(string $name): string
    {
        // Remove common Amazon-specific text
        $name = preg_replace('/\s*\([^)]*\)\s*/', ' ', $name); // Remove parenthetical text
        $name = preg_replace('/\s*\[[^\]]*\]\s*/', ' ', $name); // Remove bracketed text
        $name = preg_replace('/\s*-\s*[0-9]+\s*(pack|count|pcs?)\s*/i', ' ', $name); // Remove pack counts
        $name = preg_replace('/\s+/', ' ', $name); // Normalize spaces
        
        return trim($name);
    }

    /**
     * Generate suggested stock ID for new item
     * 
     * @param InvoiceItem $item Item to generate ID for
     * @return string Suggested stock ID
     */
    private function generateStockId(InvoiceItem $item): string
    {
        // Prefer ASIN if available
        if ($item->getAsin()) {
            return $item->getAsin();
        }

        // Use SKU if available
        if ($item->getSku()) {
            return $item->getSku();
        }

        // Generate from product name
        $name = $this->cleanProductName($item->getProductName());
        $words = explode(' ', $name);
        $acronym = '';
        
        foreach (array_slice($words, 0, 3) as $word) {
            if (strlen($word) > 2) {
                $acronym .= strtoupper(substr($word, 0, 3));
            }
        }

        return $acronym ?: 'AMZ' . date('YmdHis');
    }

    /**
     * Suggest category for new item
     * 
     * @param InvoiceItem $item Item to categorize
     * @return int|null Suggested category ID
     */
    private function suggestCategory(InvoiceItem $item): ?int
    {
        // Simple keyword-based category suggestion
        $productName = strtolower($item->getProductName());
        
        $categoryMappings = $this->config['category_mappings'] ?? [
            'computer' => 1,
            'electronic' => 1,
            'book' => 2,
            'clothing' => 3,
            'home' => 4,
            'kitchen' => 4,
            'office' => 5
        ];

        foreach ($categoryMappings as $keyword => $categoryId) {
            if (strpos($productName, $keyword) !== false) {
                return $categoryId;
            }
        }

        return $this->config['default_category_id'] ?? null;
    }

    /**
     * Suggest units for new item
     * 
     * @param InvoiceItem $item Item to suggest units for
     * @return string Suggested units
     */
    private function suggestUnits(InvoiceItem $item): string
    {
        // Simple quantity-based suggestion
        return $item->getQuantity() == 1 ? 'each' : 'pcs';
    }

    /**
     * Record matching in history table
     * 
     * @param InvoiceItem $item      Matched item
     * @param string      $stockId   Stock ID matched to
     * @param string      $matchType Type of match
     * @return void
     */
    private function recordMatchHistory(InvoiceItem $item, string $stockId, string $matchType): void
    {
        $query = "INSERT INTO {$this->historyTable} 
                  (asin, sku, product_name, stock_id, match_type, confidence, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $this->database->query($query, [
            $item->getAsin(),
            $item->getSku(),
            $item->getProductName(),
            $stockId,
            $matchType,
            100 // Manual matches have 100% confidence
        ]);
    }

    /**
     * Update matching rules based on manual matches
     * 
     * @param InvoiceItem $item    Matched item
     * @param string      $stockId Stock ID matched to
     * @return void
     */
    private function updateMatchingRules(InvoiceItem $item, string $stockId): void
    {
        // Create ASIN rule if available
        if ($item->getAsin()) {
            $this->addMatchingRule(
                'asin_pattern',
                $item->getAsin(),
                $stockId,
                1 // High priority
            );
        }

        // Create SKU rule if available
        if ($item->getSku()) {
            $this->addMatchingRule(
                'sku_pattern',
                $item->getSku(),
                $stockId,
                2 // Medium priority
            );
        }
    }

    /**
     * Check if value matches pattern
     * 
     * @param string $value   Value to check
     * @param string $pattern Pattern to match against
     * @return bool True if matches
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Support simple wildcard patterns and regex
        if (strpos($pattern, '*') !== false) {
            $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
            return (bool) preg_match("/^{$regex}$/i", $value);
        }

        if (strpos($pattern, '/') === 0) {
            return (bool) preg_match($pattern, $value);
        }

        return strcasecmp($value, $pattern) === 0;
    }

    /**
     * Get default configuration
     * 
     * @return array Default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'stock_table' => 'stock_master',
            'min_name_confidence' => 60,
            'default_category_id' => 1,
            'default_purchase_account' => '5010',
            'default_cogs_account' => '5020',
            'default_inventory_account' => '1510',
            'default_adjustment_account' => '5040',
            'default_assembly_account' => '1530',
            'category_mappings' => [
                'computer' => 1,
                'electronic' => 1,
                'book' => 2,
                'clothing' => 3,
                'home' => 4,
                'kitchen' => 4,
                'office' => 5
            ]
        ];
    }
}
