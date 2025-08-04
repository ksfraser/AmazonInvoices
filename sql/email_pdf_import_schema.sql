-- Email and PDF Import Schema
-- Tables for managing email processing, PDF OCR processing, and import configurations

-- Email processing log table
CREATE TABLE IF NOT EXISTS amazon_email_logs (
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
    FOREIGN KEY (invoice_id) REFERENCES amazon_invoices(id) ON DELETE SET NULL
);

-- PDF processing log table
CREATE TABLE IF NOT EXISTS amazon_pdf_logs (
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
    FOREIGN KEY (invoice_id) REFERENCES amazon_invoices(id) ON DELETE SET NULL
);

-- Gmail credentials table
CREATE TABLE IF NOT EXISTS gmail_credentials (
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
);

-- Email search patterns configuration
CREATE TABLE IF NOT EXISTS email_search_patterns (
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
);

-- PDF OCR configuration
CREATE TABLE IF NOT EXISTS pdf_ocr_config (
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
);

-- Import processing queue
CREATE TABLE IF NOT EXISTS import_processing_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue_type ENUM('email', 'pdf') NOT NULL,
    source_id VARCHAR(255) NOT NULL, -- gmail_message_id or pdf_file_path
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
);

-- Import statistics table
CREATE TABLE IF NOT EXISTS import_statistics (
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
);

-- Insert default email search patterns
INSERT INTO email_search_patterns (pattern_name, pattern_type, pattern_value, description) VALUES
('Amazon Order Confirmation', 'subject', 'Your order has been shipped', 'Standard Amazon shipping confirmation'),
('Amazon Invoice', 'subject', 'Your Amazon order', 'General Amazon order subject'),
('Amazon Receipt', 'subject', 'Your receipt from Amazon', 'Amazon receipt emails'),
('Amazon Digital Receipt', 'subject', 'Your Amazon digital receipt', 'Digital purchases from Amazon'),
('Amazon From Address', 'from', 'auto-confirm@amazon.com', 'Primary Amazon confirmation email address'),
('Amazon No Reply', 'from', 'no-reply@amazon.com', 'Amazon no-reply email address'),
('Amazon Ship Confirm', 'from', 'ship-confirm@amazon.com', 'Amazon shipping confirmation address');

-- Insert default PDF OCR configuration
INSERT INTO pdf_ocr_config (config_name, description) VALUES
('Default OCR Config', 'Default configuration for PDF OCR processing with standard settings');

-- Insert default Gmail credentials placeholder
INSERT INTO gmail_credentials (credential_name, client_id, client_secret) VALUES
('Default Gmail Config', 'your-client-id-here', 'your-client-secret-here');
