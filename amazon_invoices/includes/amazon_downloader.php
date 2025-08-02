<?php
/**
 * Amazon Invoice Download Functions
 */

require_once dirname(__FILE__) . '/db_functions.php';

class AmazonInvoiceDownloader 
{
    private $email;
    private $password;
    private $download_path;
    private $selenium_driver;
    
    public function __construct($email, $password, $download_path) 
    {
        $this->email = $email;
        $this->password = $password;
        $this->download_path = $download_path;
        
        // Ensure download directory exists
        if (!is_dir($this->download_path)) {
            mkdir($this->download_path, 0755, true);
        }
    }
    
    /**
     * Download invoices from Amazon for a date range
     * This is a placeholder - actual implementation would use web scraping
     * or Amazon's SP-API if available
     */
    public function download_invoices($start_date, $end_date) 
    {
        $invoices = array();
        
        try {
            // Placeholder for actual Amazon scraping/API logic
            // This would typically involve:
            // 1. Login to Amazon
            // 2. Navigate to order history
            // 3. Filter by date range
            // 4. Download invoice PDFs
            // 5. Parse invoice data
            
            $sample_invoices = $this->generate_sample_data($start_date, $end_date);
            
            foreach ($sample_invoices as $invoice_data) {
                $staging_id = $this->process_invoice_data($invoice_data);
                $invoices[] = $staging_id;
            }
            
        } catch (Exception $e) {
            display_error("Failed to download Amazon invoices: " . $e->getMessage());
            return false;
        }
        
        return $invoices;
    }
    
    /**
     * Process individual invoice data and store in staging
     */
    private function process_invoice_data($invoice_data) 
    {
        // Add invoice to staging
        $staging_id = add_amazon_invoice_staging(
            $invoice_data['invoice_number'],
            $invoice_data['order_number'], 
            $invoice_data['invoice_date'],
            $invoice_data['total'],
            $invoice_data['tax_amount'],
            $invoice_data['shipping_amount'],
            $invoice_data['currency'],
            $invoice_data['pdf_path'],
            json_encode($invoice_data)
        );
        
        // Add invoice items
        foreach ($invoice_data['items'] as $index => $item) {
            add_amazon_invoice_item_staging(
                $staging_id,
                $index + 1,
                $item['product_name'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price'],
                $item['asin'],
                $item['sku'],
                $item['tax_amount']
            );
        }
        
        // Add payment information
        foreach ($invoice_data['payments'] as $payment) {
            add_amazon_payment_staging(
                $staging_id,
                $payment['method'],
                $payment['amount'],
                $payment['reference']
            );
        }
        
        add_amazon_processing_log($staging_id, 'imported', 'Invoice imported from Amazon');
        
        return $staging_id;
    }
    
    /**
     * Generate sample data for testing - remove in production
     */
    private function generate_sample_data($start_date, $end_date) 
    {
        $sample_invoices = array();
        
        // Generate a few sample invoices
        for ($i = 1; $i <= 3; $i++) {
            $invoice_date = date('Y-m-d', strtotime($start_date . " +{$i} days"));
            
            $sample_invoices[] = array(
                'invoice_number' => 'AMZ-' . date('Ymd') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'order_number' => '123-' . rand(1000000, 9999999) . '-' . rand(1000000, 9999999),
                'invoice_date' => $invoice_date,
                'total' => rand(50, 500),
                'tax_amount' => rand(5, 50),
                'shipping_amount' => rand(0, 20),
                'currency' => 'USD',
                'pdf_path' => $this->download_path . '/AMZ-' . date('Ymd') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT) . '.pdf',
                'items' => array(
                    array(
                        'product_name' => 'Sample Product ' . $i,
                        'asin' => 'B' . str_pad($i, 9, '0', STR_PAD_LEFT),
                        'sku' => 'SKU-' . $i,
                        'quantity' => rand(1, 3),
                        'unit_price' => rand(20, 200),
                        'total_price' => rand(20, 200),
                        'tax_amount' => rand(2, 20)
                    )
                ),
                'payments' => array(
                    array(
                        'method' => 'credit_card',
                        'amount' => rand(50, 500),
                        'reference' => '**** **** **** ' . rand(1000, 9999)
                    )
                )
            );
        }
        
        return $sample_invoices;
    }
    
    /**
     * Parse PDF invoice to extract data
     * This would use a PDF parsing library in production
     */
    public function parse_pdf_invoice($pdf_path) 
    {
        // Placeholder for PDF parsing logic
        // Would use libraries like PDF Parser, tcpdf, etc.
        return array(
            'success' => true,
            'data' => array()
        );
    }
    
    /**
     * Auto-match items based on existing rules
     */
    public function auto_match_items($staging_invoice_id) 
    {
        $items = get_amazon_invoice_items_staging($staging_invoice_id);
        $matched_count = 0;
        
        while ($item = db_fetch($items)) {
            if (!$item['fa_item_matched']) {
                $fa_stock_id = find_matching_fa_item(
                    $item['asin'], 
                    $item['sku'], 
                    $item['product_name']
                );
                
                if ($fa_stock_id) {
                    update_amazon_item_matching($item['id'], $fa_stock_id, 'auto');
                    $matched_count++;
                    
                    add_amazon_processing_log(
                        $staging_invoice_id, 
                        'item_auto_matched', 
                        "Item '{$item['product_name']}' auto-matched to stock ID: {$fa_stock_id}"
                    );
                }
            }
        }
        
        return $matched_count;
    }
    
    /**
     * Validate invoice data before processing
     */
    public function validate_invoice_data($staging_invoice_id) 
    {
        $invoice = get_amazon_invoice_staging($staging_invoice_id);
        $items = get_amazon_invoice_items_staging($staging_invoice_id);
        $payments = get_amazon_payment_staging($staging_invoice_id);
        
        $errors = array();
        
        // Check if all items are matched
        $unmatched_items = 0;
        while ($item = db_fetch($items)) {
            if (!$item['fa_item_matched']) {
                $unmatched_items++;
            }
        }
        
        if ($unmatched_items > 0) {
            $errors[] = "Invoice has {$unmatched_items} unmatched items";
        }
        
        // Check if payments are allocated
        $unallocated_payments = 0;
        while ($payment = db_fetch($payments)) {
            if (!$payment['allocation_complete']) {
                $unallocated_payments++;
            }
        }
        
        if ($unallocated_payments > 0) {
            $errors[] = "Invoice has {$unallocated_payments} unallocated payments";
        }
        
        // Validate totals match
        $items_total = 0;
        db_data_seek($items, 0);
        while ($item = db_fetch($items)) {
            $items_total += $item['total_price'];
        }
        
        $payments_total = 0;
        db_data_seek($payments, 0);
        while ($payment = db_fetch($payments)) {
            $payments_total += $payment['amount'];
        }
        
        if (abs($invoice['invoice_total'] - $items_total) > 0.01) {
            $errors[] = "Invoice total doesn't match sum of items";
        }
        
        if (abs($invoice['invoice_total'] - $payments_total) > 0.01) {
            $errors[] = "Invoice total doesn't match sum of payments";
        }
        
        return $errors;
    }
}
