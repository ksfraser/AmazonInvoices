<?php
/**
 * Amazon Invoice Download Interface
 */

$page_security = 'SA_AMAZON_DOWNLOAD';
$path_to_root = "../..";

include($path_to_root . "/includes/session.inc");
include($path_to_root . "/includes/ui.inc");
include($path_to_root . "/includes/date_functions.inc");

include_once(dirname(__FILE__) . "/includes/amazon_downloader.php");

page(_($help_context = "Amazon Invoice Download"));

if (isset($_POST['download_invoices'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if (!$start_date || !$end_date) {
        display_error(_("Please specify both start and end dates"));
    } else {
        $settings = get_company_pref();
        
        $downloader = new AmazonInvoiceDownloader(
            $settings['amazon_email'] ?? '',
            $settings['amazon_password'] ?? '',
            $settings['download_path'] ?? ($path_to_root . '/tmp/amazon_invoices/')
        );
        
        $invoice_ids = $downloader->download_invoices($start_date, $end_date);
        
        if ($invoice_ids && count($invoice_ids) > 0) {
            display_notification(sprintf(_("Successfully downloaded %d invoices"), count($invoice_ids)));
            
            // Auto-match items for all downloaded invoices
            $total_matched = 0;
            foreach ($invoice_ids as $staging_id) {
                $matched = $downloader->auto_match_items($staging_id);
                $total_matched += $matched;
            }
            
            if ($total_matched > 0) {
                display_notification(sprintf(_("Auto-matched %d items"), $total_matched));
            }
        } else {
            display_error(_("No invoices were downloaded or an error occurred"));
        }
    }
}

start_form();

start_table(TABLESTYLE2);
table_section_title(_("Download Amazon Invoices"));

date_row(_("Start Date:"), 'start_date', '', null, 0, 0, 0, null, true);
date_row(_("End Date:"), 'end_date', '', null, 0, 0, 0, null, true);

end_table(1);

submit_center('download_invoices', _("Download Invoices"), true, false, 'default');

end_form();

// Display recent downloads
echo "<br>";
start_table(TABLESTYLE);
table_header(array(
    _("Invoice #"),
    _("Order #"), 
    _("Date"),
    _("Total"),
    _("Status"),
    _("Actions")
));

$recent_invoices = get_amazon_invoices_staging_list(null, 20);

while ($invoice = db_fetch($recent_invoices)) {
    alt_table_row_color($k);
    
    label_cell($invoice['invoice_number']);
    label_cell($invoice['order_number']);
    label_cell(sql2date($invoice['invoice_date']));
    amount_cell($invoice['invoice_total']);
    
    $status_class = '';
    switch ($invoice['status']) {
        case 'pending':
            $status_class = 'class="warning"';
            break;
        case 'completed':
            $status_class = 'class="success"';
            break;
        case 'error':
            $status_class = 'class="error"';
            break;
    }
    
    echo "<td $status_class>" . ucfirst($invoice['status']) . "</td>";
    
    start_cell();
    echo pager_link(_("View"), "/amazon_invoices/staging_review.php?id=" . $invoice['id']);
    echo " | ";
    echo pager_link(_("Items"), "/amazon_invoices/item_matching.php?id=" . $invoice['id']);
    echo " | ";
    echo pager_link(_("Payments"), "/amazon_invoices/payment_allocation.php?id=" . $invoice['id']);
    end_cell();
    
    end_row();
}

end_table();

end_page();
?>
