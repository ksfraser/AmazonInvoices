<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

/**
 * Database Installation Service
 * 
 * Handles creation and management of Amazon invoice database tables
 * Works with any database repository implementation
 * 
 * @package AmazonInvoices\Services
 * @author  Your Name
 * @since   1.0.0
 */
class DatabaseInstallationService
{
    /**
     * @var DatabaseRepositoryInterface Database repository
     */
    private DatabaseRepositoryInterface $database;

    /**
     * @var string Table prefix
     */
    private string $prefix;

    /**
     * Constructor
     * 
     * @param DatabaseRepositoryInterface $database Database repository
     */
    public function __construct(DatabaseRepositoryInterface $database)
    {
        $this->database = $database;
        $this->prefix = $database->getTablePrefix();
    }

    /**
     * Install all required tables
     * 
     * @return bool True on success
     * @throws \Exception If installation fails
     */
    public function install(): bool
    {
        try {
            $this->database->beginTransaction();

            $this->createInvoicesTable();
            $this->createInvoiceItemsTable();
            $this->createPaymentsTable();
            $this->createMatchingRulesTable();
            $this->createMatchingHistoryTable();
            $this->createSettingsTable();

            $this->insertDefaultSettings();
            $this->insertDefaultMatchingRules();

            $this->database->commit();
            return true;

        } catch (\Exception $e) {
            $this->database->rollback();
            throw new \Exception("Database installation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Uninstall all tables (for development/testing)
     * 
     * @return bool True on success
     * @throws \Exception If uninstallation fails
     */
    public function uninstall(): bool
    {
        try {
            $this->database->beginTransaction();

            // Drop tables in reverse order of dependencies
            $tables = [
                'amazon_item_matching_history',
                'amazon_item_matching_rules',
                'amazon_payment_staging',
                'amazon_invoice_items_staging',
                'amazon_invoices_staging',
                'amazon_invoice_settings'
            ];

            foreach ($tables as $table) {
                $this->database->query("DROP TABLE IF EXISTS {$this->prefix}{$table}");
            }

            $this->database->commit();
            return true;

        } catch (\Exception $e) {
            $this->database->rollback();
            throw new \Exception("Database uninstallation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if tables are installed
     * 
     * @return bool True if all tables exist
     */
    public function isInstalled(): bool
    {
        $requiredTables = [
            'amazon_invoices_staging',
            'amazon_invoice_items_staging',
            'amazon_payment_staging',
            'amazon_item_matching_rules',
            'amazon_item_matching_history',
            'amazon_invoice_settings'
        ];

        foreach ($requiredTables as $table) {
            if (!$this->database->tableExists($this->prefix . $table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get database schema version
     * 
     * @return string Schema version
     */
    public function getSchemaVersion(): string
    {
        try {
            $query = "SELECT setting_value FROM {$this->prefix}amazon_invoice_settings 
                      WHERE setting_key = 'schema_version'";
            $result = $this->database->query($query);
            $row = $this->database->fetch($result);
            
            return $row['setting_value'] ?? '1.0.0';
        } catch (\Exception $e) {
            return '1.0.0';
        }
    }

    /**
     * Create invoices staging table
     * 
     * @return void
     */
    private function createInvoicesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_invoices_staging (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) NOT NULL UNIQUE,
            order_number VARCHAR(50) NOT NULL,
            invoice_date DATE NOT NULL,
            invoice_total DECIMAL(15,4) NOT NULL,
            tax_amount DECIMAL(15,4) DEFAULT 0,
            shipping_amount DECIMAL(15,4) DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            pdf_path VARCHAR(255) NULL,
            raw_data TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            fa_trans_no INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            INDEX idx_invoice_number (invoice_number),
            INDEX idx_order_number (order_number),
            INDEX idx_status (status),
            INDEX idx_invoice_date (invoice_date)
        ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($sql);
    }

    /**
     * Create invoice items staging table
     * 
     * @return void
     */
    private function createInvoiceItemsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_invoice_items_staging (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staging_invoice_id INT NOT NULL,
            line_number INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            asin VARCHAR(20) NULL,
            sku VARCHAR(50) NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(15,4) NOT NULL,
            total_price DECIMAL(15,4) NOT NULL,
            tax_amount DECIMAL(15,4) DEFAULT 0,
            fa_stock_id VARCHAR(20) NULL,
            fa_item_matched TINYINT(1) DEFAULT 0,
            item_match_type VARCHAR(20) NULL,
            supplier_item_code VARCHAR(50) NULL,
            notes TEXT NULL,
            FOREIGN KEY (staging_invoice_id) REFERENCES {$this->prefix}amazon_invoices_staging(id) ON DELETE CASCADE,
            INDEX idx_staging_invoice (staging_invoice_id),
            INDEX idx_asin (asin),
            INDEX idx_sku (sku),
            INDEX idx_fa_stock_id (fa_stock_id),
            INDEX idx_matched (fa_item_matched)
        ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($sql);
    }

    /**
     * Create payment staging table
     * 
     * @return void
     */
    private function createPaymentsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_payment_staging (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staging_invoice_id INT NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_reference VARCHAR(100) NULL,
            amount DECIMAL(15,4) NOT NULL,
            fa_bank_account INT NULL,
            fa_payment_type INT NULL,
            allocation_complete TINYINT(1) DEFAULT 0,
            notes TEXT NULL,
            FOREIGN KEY (staging_invoice_id) REFERENCES {$this->prefix}amazon_invoices_staging(id) ON DELETE CASCADE,
            INDEX idx_staging_invoice (staging_invoice_id),
            INDEX idx_payment_method (payment_method),
            INDEX idx_fa_bank_account (fa_bank_account)
        ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($sql);
    }

    /**
     * Create matching rules table
     * 
     * @return void
     */
    private function createMatchingRulesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_item_matching_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rule_type VARCHAR(20) NOT NULL,
            pattern VARCHAR(255) NOT NULL,
            stock_id VARCHAR(20) NOT NULL,
            confidence INT DEFAULT 80,
            priority INT DEFAULT 1,
            active TINYINT(1) DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_rule_type (rule_type),
            INDEX idx_pattern (pattern),
            INDEX idx_stock_id (stock_id),
            INDEX idx_active (active),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($sql);
    }

    /**
     * Create matching history table
     * 
     * @return void
     */
    private function createMatchingHistoryTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_item_matching_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asin VARCHAR(20) NULL,
            sku VARCHAR(50) NULL,
            product_name VARCHAR(255) NOT NULL,
            stock_id VARCHAR(20) NOT NULL,
            match_type VARCHAR(20) NOT NULL,
            confidence INT DEFAULT 100,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_asin (asin),
            INDEX idx_sku (sku),
            INDEX idx_stock_id (stock_id),
            INDEX idx_match_type (match_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($sql);
    }

    /**
     * Create settings table
     * 
     * @return void
     */
    private function createSettingsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_invoice_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            setting_type VARCHAR(20) DEFAULT 'string',
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($sql);
    }

    /**
     * Insert default settings
     * 
     * @return void
     */
    private function insertDefaultSettings(): void
    {
        $defaultSettings = [
            [
                'setting_key' => 'schema_version',
                'setting_value' => '1.0.0',
                'setting_type' => 'string',
                'description' => 'Database schema version'
            ],
            [
                'setting_key' => 'auto_download_enabled',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Enable automatic invoice downloading'
            ],
            [
                'setting_key' => 'auto_match_enabled',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Enable automatic item matching'
            ],
            [
                'setting_key' => 'default_supplier_id',
                'setting_value' => '1',
                'setting_type' => 'integer',
                'description' => 'Default supplier ID for Amazon purchases'
            ],
            [
                'setting_key' => 'default_category_id',
                'setting_value' => '1',
                'setting_type' => 'integer',
                'description' => 'Default category for new items'
            ],
            [
                'setting_key' => 'min_match_confidence',
                'setting_value' => '70',
                'setting_type' => 'integer',
                'description' => 'Minimum confidence score for auto-matching'
            ],
            [
                'setting_key' => 'default_purchase_account',
                'setting_value' => '5010',
                'setting_type' => 'string',
                'description' => 'Default purchase account code'
            ],
            [
                'setting_key' => 'default_cogs_account',
                'setting_value' => '5020',
                'setting_type' => 'string',
                'description' => 'Default COGS account code'
            ],
            [
                'setting_key' => 'default_inventory_account',
                'setting_value' => '1510',
                'setting_type' => 'string',
                'description' => 'Default inventory account code'
            ]
        ];

        foreach ($defaultSettings as $setting) {
            $query = "INSERT IGNORE INTO {$this->prefix}amazon_invoice_settings 
                      (setting_key, setting_value, setting_type, description) 
                      VALUES (?, ?, ?, ?)";
            
            $this->database->query($query, [
                $setting['setting_key'],
                $setting['setting_value'],
                $setting['setting_type'],
                $setting['description']
            ]);
        }
    }

    /**
     * Insert default matching rules
     * 
     * @return void
     */
    private function insertDefaultMatchingRules(): void
    {
        $defaultRules = [
            [
                'rule_type' => 'name_pattern',
                'pattern' => '*cable*',
                'stock_id' => 'CABLE-GENERIC',
                'confidence' => 60,
                'priority' => 10,
                'notes' => 'Generic cable matching rule'
            ],
            [
                'rule_type' => 'name_pattern',
                'pattern' => '*adapter*',
                'stock_id' => 'ADAPTER-GENERIC',
                'confidence' => 60,
                'priority' => 10,
                'notes' => 'Generic adapter matching rule'
            ]
        ];

        foreach ($defaultRules as $rule) {
            $query = "INSERT IGNORE INTO {$this->prefix}amazon_item_matching_rules 
                      (rule_type, pattern, stock_id, confidence, priority, notes) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $this->database->query($query, [
                $rule['rule_type'],
                $rule['pattern'],
                $rule['stock_id'],
                $rule['confidence'],
                $rule['priority'],
                $rule['notes']
            ]);
        }
    }
}
