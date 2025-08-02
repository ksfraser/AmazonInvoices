<?php
/**
 * Amazon Invoices Database Functions
 */

// Staging invoice functions
function add_amazon_invoice_staging($invoice_number, $order_number, $invoice_date, $invoice_total, 
                                  $tax_amount = 0, $shipping_amount = 0, $currency = 'USD', 
                                  $pdf_path = null, $raw_data = null) 
{
    $sql = "INSERT INTO ".TB_PREF."amazon_invoices_staging 
            (invoice_number, order_number, invoice_date, invoice_total, tax_amount, 
             shipping_amount, currency, pdf_path, raw_data) 
            VALUES (".db_escape($invoice_number).", ".db_escape($order_number).", 
                   '".date2sql($invoice_date)."', ".db_escape($invoice_total).", 
                   ".db_escape($tax_amount).", ".db_escape($shipping_amount).", 
                   ".db_escape($currency).", ".db_escape($pdf_path).", ".db_escape($raw_data).")";
    
    db_query($sql, "Failed to add Amazon invoice to staging");
    return db_insert_id();
}

function get_amazon_invoice_staging($id) 
{
    $sql = "SELECT * FROM ".TB_PREF."amazon_invoices_staging WHERE id = ".db_escape($id);
    $result = db_query($sql, "Failed to get Amazon invoice from staging");
    return db_fetch($result);
}

function update_amazon_invoice_staging_status($id, $status, $notes = null, $fa_trans_no = null) 
{
    $sql = "UPDATE ".TB_PREF."amazon_invoices_staging 
            SET status = ".db_escape($status).", processed_at = NOW()";
    
    if ($notes !== null) {
        $sql .= ", notes = ".db_escape($notes);
    }
    
    if ($fa_trans_no !== null) {
        $sql .= ", fa_trans_no = ".db_escape($fa_trans_no);
    }
    
    $sql .= " WHERE id = ".db_escape($id);
    
    db_query($sql, "Failed to update Amazon invoice staging status");
}

function get_amazon_invoices_staging_list($status = null, $limit = 100) 
{
    $sql = "SELECT * FROM ".TB_PREF."amazon_invoices_staging";
    
    if ($status !== null) {
        $sql .= " WHERE status = ".db_escape($status);
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ".db_escape($limit);
    
    return db_query($sql, "Failed to get Amazon invoices staging list");
}

// Invoice items functions
function add_amazon_invoice_item_staging($staging_invoice_id, $line_number, $product_name, 
                                       $quantity, $unit_price, $total_price, $asin = null, 
                                       $sku = null, $tax_amount = 0) 
{
    $sql = "INSERT INTO ".TB_PREF."amazon_invoice_items_staging 
            (staging_invoice_id, line_number, product_name, asin, sku, quantity, 
             unit_price, total_price, tax_amount) 
            VALUES (".db_escape($staging_invoice_id).", ".db_escape($line_number).", 
                   ".db_escape($product_name).", ".db_escape($asin).", ".db_escape($sku).", 
                   ".db_escape($quantity).", ".db_escape($unit_price).", ".db_escape($total_price).", 
                   ".db_escape($tax_amount).")";
    
    db_query($sql, "Failed to add Amazon invoice item to staging");
    return db_insert_id();
}

function get_amazon_invoice_items_staging($staging_invoice_id) 
{
    $sql = "SELECT * FROM ".TB_PREF."amazon_invoice_items_staging 
            WHERE staging_invoice_id = ".db_escape($staging_invoice_id)." 
            ORDER BY line_number";
    
    return db_query($sql, "Failed to get Amazon invoice items from staging");
}

function update_amazon_item_matching($item_id, $fa_stock_id, $match_type = 'manual', $supplier_item_code = null) 
{
    $sql = "UPDATE ".TB_PREF."amazon_invoice_items_staging 
            SET fa_stock_id = ".db_escape($fa_stock_id).", 
                fa_item_matched = 1, 
                item_match_type = ".db_escape($match_type);
    
    if ($supplier_item_code !== null) {
        $sql .= ", supplier_item_code = ".db_escape($supplier_item_code);
    }
    
    $sql .= " WHERE id = ".db_escape($item_id);
    
    db_query($sql, "Failed to update Amazon item matching");
}

// Payment staging functions
function add_amazon_payment_staging($staging_invoice_id, $payment_method, $amount, 
                                  $payment_reference = null, $fa_bank_account = null) 
{
    $sql = "INSERT INTO ".TB_PREF."amazon_payment_staging 
            (staging_invoice_id, payment_method, amount, payment_reference, fa_bank_account) 
            VALUES (".db_escape($staging_invoice_id).", ".db_escape($payment_method).", 
                   ".db_escape($amount).", ".db_escape($payment_reference).", 
                   ".db_escape($fa_bank_account).")";
    
    db_query($sql, "Failed to add Amazon payment to staging");
    return db_insert_id();
}

function get_amazon_payment_staging($staging_invoice_id) 
{
    $sql = "SELECT * FROM ".TB_PREF."amazon_payment_staging 
            WHERE staging_invoice_id = ".db_escape($staging_invoice_id);
    
    return db_query($sql, "Failed to get Amazon payment staging");
}

function update_amazon_payment_allocation($payment_id, $fa_bank_account, $fa_payment_type, $notes = null) 
{
    $sql = "UPDATE ".TB_PREF."amazon_payment_staging 
            SET fa_bank_account = ".db_escape($fa_bank_account).", 
                fa_payment_type = ".db_escape($fa_payment_type).", 
                allocation_complete = 1";
    
    if ($notes !== null) {
        $sql .= ", notes = ".db_escape($notes);
    }
    
    $sql .= " WHERE id = ".db_escape($payment_id);
    
    db_query($sql, "Failed to update Amazon payment allocation");
}

// Matching rules functions
function add_amazon_matching_rule($match_type, $match_value, $fa_stock_id, $priority = 1) 
{
    global $user;
    
    $sql = "INSERT INTO ".TB_PREF."amazon_item_matching_rules 
            (match_type, match_value, fa_stock_id, priority, created_by) 
            VALUES (".db_escape($match_type).", ".db_escape($match_value).", 
                   ".db_escape($fa_stock_id).", ".db_escape($priority).", 
                   ".db_escape($user).")";
    
    db_query($sql, "Failed to add Amazon matching rule");
    return db_insert_id();
}

function get_amazon_matching_rules($active_only = true) 
{
    $sql = "SELECT r.*, s.description as stock_description 
            FROM ".TB_PREF."amazon_item_matching_rules r 
            LEFT JOIN ".TB_PREF."stock_master s ON r.fa_stock_id = s.stock_id";
    
    if ($active_only) {
        $sql .= " WHERE r.active = 1";
    }
    
    $sql .= " ORDER BY r.priority, r.match_type, r.match_value";
    
    return db_query($sql, "Failed to get Amazon matching rules");
}

function find_matching_fa_item($asin, $sku, $product_name) 
{
    // Try ASIN match first
    if ($asin) {
        $sql = "SELECT fa_stock_id FROM ".TB_PREF."amazon_item_matching_rules 
                WHERE match_type = 'asin' AND match_value = ".db_escape($asin)." 
                AND active = 1 ORDER BY priority LIMIT 1";
        $result = db_query($sql);
        if (db_num_rows($result) > 0) {
            $row = db_fetch($result);
            return $row['fa_stock_id'];
        }
    }
    
    // Try SKU match
    if ($sku) {
        $sql = "SELECT fa_stock_id FROM ".TB_PREF."amazon_item_matching_rules 
                WHERE match_type = 'sku' AND match_value = ".db_escape($sku)." 
                AND active = 1 ORDER BY priority LIMIT 1";
        $result = db_query($sql);
        if (db_num_rows($result) > 0) {
            $row = db_fetch($result);
            return $row['fa_stock_id'];
        }
    }
    
    // Try product name exact match
    $sql = "SELECT fa_stock_id FROM ".TB_PREF."amazon_item_matching_rules 
            WHERE match_type = 'product_name' AND match_value = ".db_escape($product_name)." 
            AND active = 1 ORDER BY priority LIMIT 1";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $row = db_fetch($result);
        return $row['fa_stock_id'];
    }
    
    // Try keyword matching
    $sql = "SELECT fa_stock_id FROM ".TB_PREF."amazon_item_matching_rules 
            WHERE match_type = 'keyword' AND ".db_escape($product_name)." LIKE CONCAT('%', match_value, '%') 
            AND active = 1 ORDER BY priority LIMIT 1";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $row = db_fetch($result);
        return $row['fa_stock_id'];
    }
    
    return false;
}

// Logging functions
function add_amazon_processing_log($staging_invoice_id, $action, $details = null) 
{
    global $user;
    
    $sql = "INSERT INTO ".TB_PREF."amazon_processing_log 
            (staging_invoice_id, action, details, user_id) 
            VALUES (".db_escape($staging_invoice_id).", ".db_escape($action).", 
                   ".db_escape($details).", ".db_escape($user).")";
    
    db_query($sql, "Failed to add Amazon processing log");
}

function get_amazon_processing_log($staging_invoice_id) 
{
    $sql = "SELECT * FROM ".TB_PREF."amazon_processing_log 
            WHERE staging_invoice_id = ".db_escape($staging_invoice_id)." 
            ORDER BY created_at DESC";
    
    return db_query($sql, "Failed to get Amazon processing log");
}
