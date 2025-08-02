<?php
/**
 * Amazon Invoices Module for FrontAccounting
 * Installation and table creation
 */

function install_amazon_invoices() 
{
    global $db;
    
    // Create staging table for Amazon invoices
    $sql = "CREATE TABLE IF NOT EXISTS ".TB_PREF."amazon_invoices_staging (
        id int(11) NOT NULL AUTO_INCREMENT,
        invoice_number varchar(50) NOT NULL,
        order_number varchar(50) NOT NULL,
        invoice_date date NOT NULL,
        invoice_total decimal(10,2) NOT NULL,
        tax_amount decimal(10,2) DEFAULT 0.00,
        shipping_amount decimal(10,2) DEFAULT 0.00,
        currency varchar(3) DEFAULT 'USD',
        pdf_path varchar(255) DEFAULT NULL,
        raw_data text,
        status enum('pending','processing','matched','completed','error') DEFAULT 'pending',
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        processed_at timestamp NULL,
        fa_trans_no int(11) DEFAULT NULL,
        notes text,
        PRIMARY KEY (id),
        UNIQUE KEY invoice_number (invoice_number),
        KEY status (status),
        KEY invoice_date (invoice_date)
    )";
    
    if (!db_query($sql)) {
        display_error("Failed to create amazon_invoices_staging table");
        return false;
    }
    
    // Create staging table for Amazon invoice items
    $sql = "CREATE TABLE IF NOT EXISTS ".TB_PREF."amazon_invoice_items_staging (
        id int(11) NOT NULL AUTO_INCREMENT,
        staging_invoice_id int(11) NOT NULL,
        line_number int(11) NOT NULL,
        product_name varchar(255) NOT NULL,
        asin varchar(20) DEFAULT NULL,
        sku varchar(50) DEFAULT NULL,
        quantity int(11) NOT NULL DEFAULT 1,
        unit_price decimal(10,2) NOT NULL,
        total_price decimal(10,2) NOT NULL,
        tax_amount decimal(10,2) DEFAULT 0.00,
        fa_stock_id varchar(20) DEFAULT NULL,
        fa_item_matched tinyint(1) DEFAULT 0,
        item_match_type enum('existing','new','manual') DEFAULT NULL,
        supplier_item_code varchar(50) DEFAULT NULL,
        category_suggestion varchar(50) DEFAULT NULL,
        notes text,
        PRIMARY KEY (id),
        KEY staging_invoice_id (staging_invoice_id),
        KEY fa_stock_id (fa_stock_id),
        FOREIGN KEY (staging_invoice_id) REFERENCES ".TB_PREF."amazon_invoices_staging(id) ON DELETE CASCADE
    )";
    
    if (!db_query($sql)) {
        display_error("Failed to create amazon_invoice_items_staging table");
        return false;
    }
    
    // Create payment allocation staging table
    $sql = "CREATE TABLE IF NOT EXISTS ".TB_PREF."amazon_payment_staging (
        id int(11) NOT NULL AUTO_INCREMENT,
        staging_invoice_id int(11) NOT NULL,
        payment_method enum('credit_card','bank_transfer','paypal','gift_card','points','split') NOT NULL,
        payment_reference varchar(100) DEFAULT NULL,
        amount decimal(10,2) NOT NULL,
        fa_bank_account int(11) DEFAULT NULL,
        fa_payment_type int(11) DEFAULT NULL,
        allocation_complete tinyint(1) DEFAULT 0,
        notes text,
        PRIMARY KEY (id),
        KEY staging_invoice_id (staging_invoice_id),
        FOREIGN KEY (staging_invoice_id) REFERENCES ".TB_PREF."amazon_invoices_staging(id) ON DELETE CASCADE
    )";
    
    if (!db_query($sql)) {
        display_error("Failed to create amazon_payment_staging table");
        return false;
    }
    
    // Create item matching rules table
    $sql = "CREATE TABLE IF NOT EXISTS ".TB_PREF."amazon_item_matching_rules (
        id int(11) NOT NULL AUTO_INCREMENT,
        match_type enum('asin','sku','product_name','keyword') NOT NULL,
        match_value varchar(255) NOT NULL,
        fa_stock_id varchar(20) NOT NULL,
        priority int(11) DEFAULT 1,
        active tinyint(1) DEFAULT 1,
        created_by varchar(50) NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY match_type (match_type),
        KEY fa_stock_id (fa_stock_id),
        KEY active (active)
    )";
    
    if (!db_query($sql)) {
        display_error("Failed to create amazon_item_matching_rules table");
        return false;
    }
    
    // Create processing log table
    $sql = "CREATE TABLE IF NOT EXISTS ".TB_PREF."amazon_processing_log (
        id int(11) NOT NULL AUTO_INCREMENT,
        staging_invoice_id int(11) NOT NULL,
        action varchar(50) NOT NULL,
        details text,
        user_id varchar(50) NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY staging_invoice_id (staging_invoice_id),
        KEY action (action),
        KEY created_at (created_at)
    )";
    
    if (!db_query($sql)) {
        display_error("Failed to create amazon_processing_log table");
        return false;
    }
    
    return true;
}

function uninstall_amazon_invoices() 
{
    global $db;
    
    $tables = array(
        'amazon_processing_log',
        'amazon_item_matching_rules', 
        'amazon_payment_staging',
        'amazon_invoice_items_staging',
        'amazon_invoices_staging'
    );
    
    foreach ($tables as $table) {
        $sql = "DROP TABLE IF EXISTS ".TB_PREF.$table;
        db_query($sql);
    }
    
    return true;
}
