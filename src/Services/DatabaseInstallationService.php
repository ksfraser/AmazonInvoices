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
            $this->createCredentialsTable();
            $this->createCredentialTestsTable();
            $this->createApiUsageTable();
            
            // Email and PDF import tables
            $this->createEmailLogsTable();
            $this->createPdfLogsTable();
            $this->createGmailCredentialsTable();
            $this->createEmailSearchPatternsTable();
            $this->createPdfOcrConfigTable();
            $this->createImportProcessingQueueTable();
            $this->createImportStatisticsTable();

            $this->insertDefaultSettings();
            $this->insertDefaultMatchingRules();
            $this->insertDefaultEmailPatterns();
            $this->insertDefaultOcrConfig();

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
                'amazon_api_usage',
                'amazon_credential_tests',
                'amazon_credentials',
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

    /**
     * Create Amazon credentials table
     */
    private function createCredentialsTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_credentials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL DEFAULT 1,
            auth_method ENUM('sp_api', 'oauth', 'scraping') NOT NULL,
            credentials_data TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company (company_id),
            INDEX idx_method (auth_method)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create credential tests table
     */
    private function createCredentialTestsTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_credential_tests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL DEFAULT 1,
            test_success BOOLEAN NOT NULL,
            test_error TEXT,
            test_details TEXT,
            test_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_company (company_id),
            INDEX idx_date (test_date),
            UNIQUE KEY unique_company (company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create API usage tracking table
     */
    private function createApiUsageTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_api_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL DEFAULT 1,
            api_endpoint VARCHAR(255) NOT NULL,
            request_method VARCHAR(10) NOT NULL,
            response_status INT,
            request_size INT DEFAULT 0,
            response_size INT DEFAULT 0,
            execution_time_ms INT DEFAULT 0,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_company (company_id),
            INDEX idx_date (created_at),
            INDEX idx_endpoint (api_endpoint),
            INDEX idx_status (response_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create email processing logs table
     */
    private function createEmailLogsTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gmail_message_id VARCHAR(255) NOT NULL,
            email_subject VARCHAR(500),
            email_from VARCHAR(255),
            email_date DATETIME,
            processed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processing_status ENUM('pending', 'processing', 'completed', 'error', 'duplicate') DEFAULT 'pending',
            error_message TEXT,
            invoice_id INT,
            raw_email_data LONGTEXT,
            extracted_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_gmail_message_id (gmail_message_id),
            INDEX idx_processing_status (processing_status),
            INDEX idx_processed_date (processed_date),
            FOREIGN KEY (invoice_id) REFERENCES {$this->prefix}amazon_invoices_staging(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create PDF processing logs table
     */
    private function createPdfLogsTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}amazon_pdf_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pdf_file_path VARCHAR(1000) NOT NULL,
            pdf_file_hash VARCHAR(64) NOT NULL,
            pdf_file_size BIGINT,
            processed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processing_status ENUM('pending', 'processing', 'completed', 'error', 'duplicate') DEFAULT 'pending',
            ocr_processing_time DECIMAL(10,3),
            error_message TEXT,
            invoice_id INT,
            extracted_text LONGTEXT,
            extracted_data JSON,
            processing_metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pdf_file_hash (pdf_file_hash),
            INDEX idx_processing_status (processing_status),
            INDEX idx_processed_date (processed_date),
            FOREIGN KEY (invoice_id) REFERENCES {$this->prefix}amazon_invoices_staging(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create Gmail credentials table
     */
    private function createGmailCredentialsTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}gmail_credentials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credential_name VARCHAR(100) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            client_secret VARCHAR(500) NOT NULL,
            access_token TEXT,
            refresh_token TEXT,
            token_expires_at DATETIME,
            scope VARCHAR(500) DEFAULT 'https://www.googleapis.com/auth/gmail.readonly',
            is_active BOOLEAN DEFAULT true,
            last_used TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_credential_name (credential_name),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create email search patterns table
     */
    private function createEmailSearchPatternsTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}email_search_patterns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pattern_name VARCHAR(100) NOT NULL,
            pattern_type ENUM('subject', 'from', 'body', 'label') DEFAULT 'subject',
            pattern_value VARCHAR(500) NOT NULL,
            pattern_regex BOOLEAN DEFAULT false,
            is_active BOOLEAN DEFAULT true,
            priority INT DEFAULT 1,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pattern_type (pattern_type),
            INDEX idx_is_active (is_active),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create PDF OCR configuration table
     */
    private function createPdfOcrConfigTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}pdf_ocr_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_name VARCHAR(100) NOT NULL,
            tesseract_path VARCHAR(255) DEFAULT '/usr/bin/tesseract',
            tesseract_data_path VARCHAR(255) DEFAULT '/usr/share/tesseract-ocr/4.00/tessdata',
            tesseract_language VARCHAR(10) DEFAULT 'eng',
            poppler_path VARCHAR(255) DEFAULT '/usr/bin',
            imagemagick_path VARCHAR(255) DEFAULT '/usr/bin',
            temp_directory VARCHAR(255) DEFAULT '/tmp/amazon_invoices_ocr',
            pdf_dpi INT DEFAULT 300,
            image_preprocessing BOOLEAN DEFAULT true,
            image_enhancement_level ENUM('none', 'basic', 'advanced') DEFAULT 'basic',
            ocr_engine_mode INT DEFAULT 3,
            page_segmentation_mode INT DEFAULT 6,
            confidence_threshold DECIMAL(5,2) DEFAULT 60.00,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_config_name (config_name),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create import processing queue table
     */
    private function createImportProcessingQueueTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}import_processing_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            queue_type ENUM('email', 'pdf') NOT NULL,
            source_id VARCHAR(255) NOT NULL,
            priority INT DEFAULT 1,
            processing_status ENUM('pending', 'processing', 'completed', 'error', 'cancelled') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            last_attempt_at TIMESTAMP NULL,
            scheduled_for TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            error_message TEXT,
            processing_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_queue_type (queue_type),
            INDEX idx_processing_status (processing_status),
            INDEX idx_scheduled_for (scheduled_for),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Create import statistics table
     */
    private function createImportStatisticsTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}import_statistics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stat_date DATE NOT NULL,
            import_type ENUM('email', 'pdf') NOT NULL,
            emails_processed INT DEFAULT 0,
            emails_successful INT DEFAULT 0,
            emails_failed INT DEFAULT 0,
            emails_duplicates INT DEFAULT 0,
            pdfs_processed INT DEFAULT 0,
            pdfs_successful INT DEFAULT 0,
            pdfs_failed INT DEFAULT 0,
            pdfs_duplicates INT DEFAULT 0,
            total_invoices_created INT DEFAULT 0,
            total_items_processed INT DEFAULT 0,
            average_processing_time DECIMAL(10,3) DEFAULT 0.000,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_stat_date_type (stat_date, import_type),
            INDEX idx_stat_date (stat_date),
            INDEX idx_import_type (import_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->database->query($query);
    }

    /**
     * Insert default email search patterns
     */
    private function insertDefaultEmailPatterns(): void
    {
        $patterns = [
            [
                'pattern_name' => 'Amazon Order Confirmation',
                'pattern_type' => 'subject',
                'pattern_value' => 'Your order has been shipped',
                'description' => 'Standard Amazon shipping confirmation'
            ],
            [
                'pattern_name' => 'Amazon Invoice',
                'pattern_type' => 'subject',
                'pattern_value' => 'Your Amazon order',
                'description' => 'General Amazon order subject'
            ],
            [
                'pattern_name' => 'Amazon Receipt',
                'pattern_type' => 'subject',
                'pattern_value' => 'Your receipt from Amazon',
                'description' => 'Amazon receipt emails'
            ],
            [
                'pattern_name' => 'Amazon From Address',
                'pattern_type' => 'from',
                'pattern_value' => 'auto-confirm@amazon.com',
                'description' => 'Primary Amazon confirmation email address'
            ]
        ];

        foreach ($patterns as $pattern) {
            $query = "INSERT INTO {$this->prefix}email_search_patterns 
                     (pattern_name, pattern_type, pattern_value, description) 
                     VALUES ('{$pattern['pattern_name']}', '{$pattern['pattern_type']}', 
                            '{$pattern['pattern_value']}', '{$pattern['description']}')";
            $this->database->query($query);
        }
    }

    /**
     * Insert default PDF OCR configuration
     */
    private function insertDefaultOcrConfig(): void
    {
        $config = [
            'config_name' => 'Default OCR Config',
            'tesseract_path' => '/usr/bin/tesseract',
            'tesseract_data_path' => '/usr/share/tesseract-ocr/4.00/tessdata',
            'tesseract_language' => 'eng',
            'poppler_path' => '/usr/bin',
            'imagemagick_path' => '/usr/bin',
            'temp_directory' => '/tmp/amazon_invoices_ocr',
            'pdf_dpi' => 300,
            'image_preprocessing' => true,
            'image_enhancement_level' => 'basic',
            'ocr_engine_mode' => 3,
            'page_segmentation_mode' => 6,
            'confidence_threshold' => 60.00,
            'is_active' => true
        ];

        $imagePreprocessing = $config['image_preprocessing'] ? 1 : 0;
        $isActive = $config['is_active'] ? 1 : 0;
        
        $query = "INSERT INTO {$this->prefix}pdf_ocr_config 
                 (config_name, tesseract_path, tesseract_data_path, tesseract_language, 
                  poppler_path, imagemagick_path, temp_directory, pdf_dpi, 
                  image_preprocessing, image_enhancement_level, ocr_engine_mode, 
                  page_segmentation_mode, confidence_threshold, is_active) 
                 VALUES ('{$config['config_name']}', '{$config['tesseract_path']}', 
                        '{$config['tesseract_data_path']}', '{$config['tesseract_language']}',
                        '{$config['poppler_path']}', '{$config['imagemagick_path']}',
                        '{$config['temp_directory']}', {$config['pdf_dpi']},
                        $imagePreprocessing, '{$config['image_enhancement_level']}',
                        {$config['ocr_engine_mode']}, {$config['page_segmentation_mode']},
                        {$config['confidence_threshold']}, $isActive)";
        
        $this->database->query($query);
    }
}
