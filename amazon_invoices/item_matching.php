<?php
/**
 * Amazon Item Matching Interface
 */

$page_security = 'SA_AMAZON_MATCH';
$path_to_root = "../..";

include($path_to_root . "/includes/session.inc");
include($path_to_root . "/includes/ui.inc");
include($path_to_root . "/inventory/includes/inventory_db.inc");

include_once(dirname(__FILE__) . "/includes/db_functions.php");

page(_($help_context = "Amazon Item Matching"));

$selected_id = get_post('selected_id', get_get('id'));

if (isset($_POST['match_item'])) {
    $item_id = $_POST['item_id'];
    $fa_stock_id = $_POST['fa_stock_id'];
    $supplier_item_code = $_POST['supplier_item_code'];
    
    if ($fa_stock_id) {
        update_amazon_item_matching($item_id, $fa_stock_id, 'manual', $supplier_item_code);
        
        $staging_invoice_id = $_POST['staging_invoice_id'];
        add_amazon_processing_log($staging_invoice_id, 'item_manual_matched', 
            "Item manually matched to stock ID: {$fa_stock_id}");
        
        display_notification(_("Item matched successfully"));
    } else {
        display_error(_("Please select a stock item"));
    }
}

if (isset($_POST['create_new_item'])) {
    $item_id = $_POST['item_id'];
    $item_data = get_amazon_invoice_item_by_id($item_id);
    
    // Redirect to stock item creation with pre-filled data
    $params = array(
        'description' => urlencode($item_data['product_name']),
        'supplier_code' => urlencode($item_data['sku']),
        'notes' => urlencode("Amazon ASIN: " . $item_data['asin'])
    );
    
    $redirect_url = $path_to_root . "/inventory/manage/items.php?" . http_build_query($params);
    meta_forward($redirect_url);
}

if (isset($_POST['auto_match_all'])) {
    $staging_id = $_POST['staging_id'];
    
    $downloader = new AmazonInvoiceDownloader('', '', '');
    $matched_count = $downloader->auto_match_items($staging_id);
    
    if ($matched_count > 0) {
        display_notification(sprintf(_("Auto-matched %d items"), $matched_count));
    } else {
        display_notification(_("No additional items could be auto-matched"));
    }
}

start_form();

// Invoice selection
start_table(TABLESTYLE2);
table_section_title(_("Select Invoice for Item Matching"));

$invoices_list = array();
$invoices = get_amazon_invoices_staging_list();
while ($invoice = db_fetch($invoices)) {
    $invoices_list[$invoice['id']] = $invoice['invoice_number'] . " - " . sql2date($invoice['invoice_date']);
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
    
    // Auto-match button
    start_table(TABLESTYLE2);
    hidden('staging_id', $selected_id);
    submit_row('auto_match_all', _("Auto-match All Items"), false, _("Attempt to auto-match unmatched items?"), false);
    end_table(1);
    
    echo "<br>";
    
    // Items for matching
    start_table(TABLESTYLE);
    table_header(array(
        _("Line"),
        _("Product Name"),
        _("ASIN"),
        _("SKU"),
        _("Qty"),
        _("Unit Price"), 
        _("Current Match"),
        _("Actions")
    ));
    
    while ($item = db_fetch($items)) {
        alt_table_row_color($k);
        
        label_cell($item['line_number']);
        label_cell($item['product_name']);
        label_cell($item['asin']);
        label_cell($item['sku']);
        qty_cell($item['quantity']);
        amount_cell($item['unit_price']);
        
        if ($item['fa_item_matched']) {
            // Get stock item details
            $stock_item = get_item($item['fa_stock_id']);
            label_cell($item['fa_stock_id'] . " - " . $stock_item['description']);
            
            start_cell();
            echo _("Matched");
            if ($item['item_match_type'] == 'auto') {
                echo " (" . _("Auto") . ")";
            }
            end_cell();
        } else {
            label_cell(_("Not matched"));
            
            start_cell();
            // Match to existing item form
            echo "<div style='margin-bottom: 5px;'>";
            echo "<strong>" . _("Match to existing item:") . "</strong><br>";
            
            $stock_items = array();
            $stock_items[''] = _("Select stock item...");
            
            // Get suggested matches based on name similarity
            $suggested_items = find_similar_stock_items($item['product_name']);
            foreach ($suggested_items as $suggestion) {
                $stock_items[$suggestion['stock_id']] = $suggestion['stock_id'] . " - " . $suggestion['description'];
            }
            
            // Add all other stock items
            $all_items = get_stock_items_list();
            while ($stock = db_fetch($all_items)) {
                if (!isset($stock_items[$stock['stock_id']])) {
                    $stock_items[$stock['stock_id']] = $stock['stock_id'] . " - " . $stock['description'];
                }
            }
            
            array_selector('fa_stock_id_' . $item['id'], null, $stock_items, array('select_submit' => false));
            echo "<br>";
            text_cells(_("Supplier Code:"), 'supplier_code_' . $item['id'], $item['sku'], 15);
            echo "<br>";
            submit('match_' . $item['id'], _("Match"), false, "", 'default');
            echo "</div>";
            
            echo "<div>";
            echo "<strong>" . _("Or create new item:") . "</strong><br>";
            submit('create_new_' . $item['id'], _("Create New Item"), false, "", false);
            echo "</div>";
            
            end_cell();
        }
        
        end_row();
    }
    
    end_table();
}

// Handle individual item matching
foreach ($_POST as $key => $value) {
    if (strpos($key, 'match_') === 0) {
        $item_id = substr($key, 6);
        $fa_stock_id = $_POST['fa_stock_id_' . $item_id];
        $supplier_code = $_POST['supplier_code_' . $item_id];
        
        if ($fa_stock_id) {
            update_amazon_item_matching($item_id, $fa_stock_id, 'manual', $supplier_code);
            add_amazon_processing_log($selected_id, 'item_manual_matched', 
                "Item manually matched to stock ID: {$fa_stock_id}");
            display_notification(_("Item matched successfully"));
            meta_forward($_SERVER['PHP_SELF'] . "?id=" . $selected_id);
        }
    }
    
    if (strpos($key, 'create_new_') === 0) {
        $item_id = substr($key, 11);
        $item_data = get_amazon_invoice_item_by_id($item_id);
        
        $params = array(
            'description' => urlencode($item_data['product_name']),
            'supplier_code' => urlencode($item_data['sku']),
            'notes' => urlencode("Amazon ASIN: " . $item_data['asin'])
        );
        
        $redirect_url = $path_to_root . "/inventory/manage/items.php?" . http_build_query($params);
        meta_forward($redirect_url);
    }
}

end_form();

/**
 * Get Amazon invoice item by ID
 */
function get_amazon_invoice_item_by_id($item_id) 
{
    $sql = "SELECT * FROM ".TB_PREF."amazon_invoice_items_staging WHERE id = ".db_escape($item_id);
    $result = db_query($sql, "Failed to get Amazon invoice item");
    return db_fetch($result);
}

/**
 * Find similar stock items based on name
 */
function find_similar_stock_items($product_name, $limit = 10) 
{
    $search_terms = explode(' ', strtolower($product_name));
    $where_clauses = array();
    
    foreach ($search_terms as $term) {
        if (strlen($term) > 2) {
            $where_clauses[] = "LOWER(description) LIKE '%" . db_escape(strtolower($term)) . "%'";
        }
    }
    
    if (empty($where_clauses)) {
        return array();
    }
    
    $sql = "SELECT stock_id, description FROM ".TB_PREF."stock_master 
            WHERE " . implode(' OR ', $where_clauses) . " 
            ORDER BY description LIMIT " . db_escape($limit);
    
    $result = db_query($sql, "Failed to find similar stock items");
    
    $items = array();
    while ($row = db_fetch($result)) {
        $items[] = $row;
    }
    
    return $items;
}

/**
 * Get all stock items for dropdown
 */
function get_stock_items_list() 
{
    $sql = "SELECT stock_id, description FROM ".TB_PREF."stock_master 
            WHERE NOT inactive ORDER BY description";
    
    return db_query($sql, "Failed to get stock items list");
}

end_page();
?>
