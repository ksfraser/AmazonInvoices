<?php
/**
 * Amazon Invoice Staging Review
 */

$page_security = 'SA_AMAZON_PROCESS';
$path_to_root = "../..";

include($path_to_root . "/includes/session.inc");
include($path_to_root . "/includes/ui.inc");
include($path_to_root . "/includes/date_functions.inc");
include($path_to_root . "/purchasing/includes/purchasing_ui.inc");

include_once(dirname(__FILE__) . "/includes/db_functions.php");
include_once(dirname(__FILE__) . "/includes/amazon_downloader.php");

page(_($help_context = "Amazon Invoice Staging Review"));

$selected_id = get_post('selected_id', get_get('id'));

if (isset($_POST['process_invoice'])) {
    $staging_id = $_POST['staging_id'];
    
    $downloader = new AmazonInvoiceDownloader('', '', '');
    $validation_errors = $downloader->validate_invoice_data($staging_id);
    
    if (empty($validation_errors)) {
        // Process the invoice into FA
        $success = process_amazon_invoice_to_fa($staging_id);
        
        if ($success) {
            update_amazon_invoice_staging_status($staging_id, 'completed');
            add_amazon_processing_log($staging_id, 'processed', 'Invoice successfully processed to FrontAccounting');
            display_notification(_("Invoice successfully processed"));
        } else {
            update_amazon_invoice_staging_status($staging_id, 'error', 'Failed to process to FrontAccounting');
            display_error(_("Failed to process invoice"));
        }
    } else {
        foreach ($validation_errors as $error) {
            display_error($error);
        }
    }
}

if (isset($_POST['delete_staging'])) {
    $staging_id = $_POST['staging_id'];
    
    $sql = "DELETE FROM ".TB_PREF."amazon_invoices_staging WHERE id = ".db_escape($staging_id);
    db_query($sql, "Failed to delete staging invoice");
    
    display_notification(_("Staging invoice deleted"));
    $selected_id = null;
}

start_form();

// Invoice selection
start_table(TABLESTYLE2);
table_section_title(_("Select Invoice to Review"));

$invoices_list = array();
$invoices = get_amazon_invoices_staging_list();
while ($invoice = db_fetch($invoices)) {
    $invoices_list[$invoice['id']] = $invoice['invoice_number'] . " - " . sql2date($invoice['invoice_date']) . " (" . $invoice['status'] . ")";
}

if (empty($invoices_list)) {
    echo "<tr><td colspan='2'>" . _("No invoices in staging") . "</td></tr>";
} else {
    array_selector_row(_("Invoice:"), 'selected_id', $selected_id, $invoices_list, array('select_submit' => true));
}

end_table(1);

if ($selected_id) {
    $invoice = get_amazon_invoice_staging($selected_id);
    $items = get_amazon_invoice_items_staging($selected_id);
    $payments = get_amazon_payment_staging($selected_id);
    
    // Invoice details
    start_table(TABLESTYLE2);
    table_section_title(_("Invoice Details"));
    
    label_row(_("Invoice Number:"), $invoice['invoice_number']);
    label_row(_("Order Number:"), $invoice['order_number']);
    label_row(_("Invoice Date:"), sql2date($invoice['invoice_date']));
    amount_row(_("Total Amount:"), $invoice['invoice_total']);
    amount_row(_("Tax Amount:"), $invoice['tax_amount']);
    amount_row(_("Shipping Amount:"), $invoice['shipping_amount']);
    label_row(_("Currency:"), $invoice['currency']);
    label_row(_("Status:"), ucfirst($invoice['status']));
    
    if ($invoice['notes']) {
        label_row(_("Notes:"), $invoice['notes']);
    }
    
    end_table(1);
    
    // Items details
    echo "<br>";
    start_table(TABLESTYLE);
    table_header(array(
        _("Line"),
        _("Product Name"),
        _("ASIN"),
        _("SKU"), 
        _("Qty"),
        _("Unit Price"),
        _("Total"),
        _("FA Stock ID"),
        _("Matched")
    ));
    
    $all_items_matched = true;
    while ($item = db_fetch($items)) {
        alt_table_row_color($k);
        
        label_cell($item['line_number']);
        label_cell($item['product_name']);
        label_cell($item['asin']);
        label_cell($item['sku']);
        qty_cell($item['quantity']);
        amount_cell($item['unit_price']);
        amount_cell($item['total_price']);
        label_cell($item['fa_stock_id'] ?: _("Not matched"));
        
        if ($item['fa_item_matched']) {
            echo "<td class='success'>" . _("Yes") . "</td>";
        } else {
            echo "<td class='error'>" . _("No") . "</td>";
            $all_items_matched = false;
        }
        
        end_row();
    }
    
    end_table();
    
    // Payment details
    echo "<br>";
    start_table(TABLESTYLE);
    table_header(array(
        _("Payment Method"),
        _("Amount"),
        _("Reference"),
        _("FA Bank Account"),
        _("Allocated")
    ));
    
    $all_payments_allocated = true;
    db_data_seek($payments, 0);
    while ($payment = db_fetch($payments)) {
        alt_table_row_color($k);
        
        label_cell(ucwords(str_replace('_', ' ', $payment['payment_method'])));
        amount_cell($payment['amount']);
        label_cell($payment['payment_reference']);
        label_cell($payment['fa_bank_account'] ?: _("Not allocated"));
        
        if ($payment['allocation_complete']) {
            echo "<td class='success'>" . _("Yes") . "</td>";
        } else {
            echo "<td class='error'>" . _("No") . "</td>";
            $all_payments_allocated = false;
        }
        
        end_row();
    }
    
    end_table();
    
    // Processing log
    echo "<br>";
    start_table(TABLESTYLE);
    table_header(array(_("Date/Time"), _("Action"), _("Details"), _("User")));
    
    $log_entries = get_amazon_processing_log($selected_id);
    while ($log = db_fetch($log_entries)) {
        alt_table_row_color($k);
        
        label_cell($log['created_at']);
        label_cell(ucwords(str_replace('_', ' ', $log['action'])));
        label_cell($log['details']);
        label_cell($log['user_id']);
        
        end_row();
    }
    
    end_table();
    
    echo "<br>";
    start_table(TABLESTYLE2);
    
    if ($invoice['status'] == 'pending' || $invoice['status'] == 'processing') {
        if ($all_items_matched && $all_payments_allocated) {
            echo "<tr><td colspan='2' class='success'>" . _("Invoice is ready for processing") . "</td></tr>";
            
            hidden('staging_id', $selected_id);
            submit_row('process_invoice', _("Process to FrontAccounting"), true, _("Process this invoice?"), true);
        } else {
            echo "<tr><td colspan='2' class='warning'>";
            echo _("Invoice cannot be processed until all items are matched and payments are allocated");
            echo "<br>";
            if (!$all_items_matched) {
                echo pager_link(_("Match Items"), "/amazon_invoices/item_matching.php?id=" . $selected_id) . " ";
            }
            if (!$all_payments_allocated) {
                echo pager_link(_("Allocate Payments"), "/amazon_invoices/payment_allocation.php?id=" . $selected_id);
            }
            echo "</td></tr>";
        }
    }
    
    if ($invoice['status'] != 'completed') {
        hidden('staging_id', $selected_id);
        submit_row('delete_staging', _("Delete Staging Invoice"), true, _("Delete this staging invoice?"), 'default');
    }
    
    end_table();
}

end_form();

/**
 * Process Amazon invoice to FrontAccounting
 */
function process_amazon_invoice_to_fa($staging_id) 
{
    global $Refs;
    
    $invoice = get_amazon_invoice_staging($staging_id);
    $items = get_amazon_invoice_items_staging($staging_id);
    $payments = get_amazon_payment_staging($staging_id);
    
    begin_transaction();
    
    try {
        // Create supplier invoice
        $supplier_id = get_company_pref('default_amazon_supplier');
        if (!$supplier_id) {
            throw new Exception("Default Amazon supplier not configured");
        }
        
        $supp_trans = add_supp_invoice(
            $supplier_id,
            $Refs->get_next(ST_SUPPINVOICE),
            sql2date($invoice['invoice_date']),
            $invoice['invoice_date'], // due date
            $invoice['invoice_total'],
            $invoice['tax_amount'],
            $invoice['currency']
        );
        
        // Add invoice items
        while ($item = db_fetch($items)) {
            if (!$item['fa_stock_id']) {
                throw new Exception("Item '{$item['product_name']}' is not matched to FA stock");
            }
            
            add_supp_invoice_item(
                ST_SUPPINVOICE,
                $supp_trans,
                $item['fa_stock_id'],
                $item['product_name'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price']
            );
        }
        
        // Process payments
        db_data_seek($payments, 0);
        while ($payment = db_fetch($payments)) {
            if (!$payment['fa_bank_account']) {
                throw new Exception("Payment method '{$payment['payment_method']}' is not allocated to FA bank account");
            }
            
            add_bank_payment(
                $payment['fa_bank_account'],
                ST_SUPPAYMENT,
                $supplier_id,
                $invoice['invoice_date'],
                $payment['amount'],
                $payment['payment_reference']
            );
        }
        
        commit_transaction();
        
        // Update staging with FA transaction number
        update_amazon_invoice_staging_status($staging_id, 'completed', 'Processed to FA', $supp_trans);
        
        return true;
        
    } catch (Exception $e) {
        rollback_transaction();
        add_amazon_processing_log($staging_id, 'process_error', $e->getMessage());
        return false;
    }
}

end_page();
?>
