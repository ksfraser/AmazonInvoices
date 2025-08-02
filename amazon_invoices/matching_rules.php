<?php
/**
 * Amazon Item Matching Rules Management
 */

$page_security = 'SA_AMAZON_INVOICES';
$path_to_root = "../..";

include($path_to_root . "/includes/session.inc");
include($path_to_root . "/includes/ui.inc");
include($path_to_root . "/inventory/includes/inventory_db.inc");

include_once(dirname(__FILE__) . "/includes/db_functions.php");

page(_($help_context = "Amazon Item Matching Rules"));

if (isset($_POST['add_rule'])) {
    $match_type = $_POST['match_type'];
    $match_value = $_POST['match_value'];
    $fa_stock_id = $_POST['fa_stock_id'];
    $priority = $_POST['priority'];
    
    if ($match_type && $match_value && $fa_stock_id) {
        add_amazon_matching_rule($match_type, $match_value, $fa_stock_id, $priority);
        display_notification(_("Matching rule added successfully"));
        
        // Clear form
        unset($_POST['match_type'], $_POST['match_value'], $_POST['fa_stock_id'], $_POST['priority']);
    } else {
        display_error(_("Please fill in all required fields"));
    }
}

if (isset($_POST['delete_rule'])) {
    $rule_id = $_POST['rule_id'];
    
    $sql = "DELETE FROM ".TB_PREF."amazon_item_matching_rules WHERE id = ".db_escape($rule_id);
    db_query($sql, "Failed to delete matching rule");
    
    display_notification(_("Matching rule deleted"));
}

if (isset($_POST['toggle_rule'])) {
    $rule_id = $_POST['rule_id'];
    $current_status = $_POST['current_status'];
    $new_status = $current_status ? 0 : 1;
    
    $sql = "UPDATE ".TB_PREF."amazon_item_matching_rules 
            SET active = ".db_escape($new_status)." 
            WHERE id = ".db_escape($rule_id);
    db_query($sql, "Failed to update matching rule status");
    
    display_notification($new_status ? _("Rule activated") : _("Rule deactivated"));
}

start_form();

// Add new rule form
start_table(TABLESTYLE2);
table_section_title(_("Add New Matching Rule"));

$match_types = array(
    'asin' => _('ASIN'),
    'sku' => _('SKU'),
    'product_name' => _('Product Name (Exact)'),
    'keyword' => _('Keyword (Contains)')
);

array_selector_row(_("Match Type:"), 'match_type', get_post('match_type'), $match_types);
text_row(_("Match Value:"), 'match_value', get_post('match_value'), 40, 255);

// Stock item selection
$stock_items = array();
$stock_items[''] = _("Select stock item...");

$sql = "SELECT stock_id, description FROM ".TB_PREF."stock_master 
        WHERE NOT inactive ORDER BY description";
$result = db_query($sql, "Failed to get stock items");

while ($row = db_fetch($result)) {
    $stock_items[$row['stock_id']] = $row['stock_id'] . " - " . $row['description'];
}

array_selector_row(_("FA Stock Item:"), 'fa_stock_id', get_post('fa_stock_id'), $stock_items);
text_row(_("Priority:"), 'priority', get_post('priority', 1), 5, 5);

end_table(1);

submit_center('add_rule', _("Add Rule"), true, false, 'default');

end_form();

echo "<br>";

// Existing rules list
start_table(TABLESTYLE);
table_header(array(
    _("ID"),
    _("Match Type"),
    _("Match Value"), 
    _("FA Stock Item"),
    _("Priority"),
    _("Status"),
    _("Created By"),
    _("Created Date"),
    _("Actions")
));

$rules = get_amazon_matching_rules(false); // Get all rules including inactive

while ($rule = db_fetch($rules)) {
    alt_table_row_color($k);
    
    label_cell($rule['id']);
    label_cell(ucwords(str_replace('_', ' ', $rule['match_type'])));
    label_cell($rule['match_value']);
    label_cell($rule['fa_stock_id'] . " - " . $rule['stock_description']);
    label_cell($rule['priority']);
    
    if ($rule['active']) {
        echo "<td class='success'>" . _("Active") . "</td>";
    } else {
        echo "<td class='error'>" . _("Inactive") . "</td>";
    }
    
    label_cell($rule['created_by']);
    label_cell($rule['created_at']);
    
    start_cell();
    
    // Toggle active/inactive
    start_form();
    hidden('rule_id', $rule['id']);
    hidden('current_status', $rule['active']);
    
    if ($rule['active']) {
        submit('toggle_rule', _("Deactivate"), false, "", false);
    } else {
        submit('toggle_rule', _("Activate"), false, "", false);
    }
    end_form();
    
    echo " ";
    
    // Delete rule
    start_form();
    hidden('rule_id', $rule['id']);
    submit('delete_rule', _("Delete"), false, _("Delete this rule?"), false);
    end_form();
    
    end_cell();
    
    end_row();
}

end_table();

// Test matching section
echo "<br>";
start_form();

start_table(TABLESTYLE2);
table_section_title(_("Test Item Matching"));

text_row(_("Test Product Name:"), 'test_product_name', get_post('test_product_name'), 40, 255);
text_row(_("Test ASIN:"), 'test_asin', get_post('test_asin'), 20, 20);
text_row(_("Test SKU:"), 'test_sku', get_post('test_sku'), 20, 50);

submit_row('test_matching', _("Test Matching"), false, "", false);

end_table(1);

if (isset($_POST['test_matching'])) {
    $test_product_name = $_POST['test_product_name'];
    $test_asin = $_POST['test_asin'];
    $test_sku = $_POST['test_sku'];
    
    if ($test_product_name || $test_asin || $test_sku) {
        $matched_stock_id = find_matching_fa_item($test_asin, $test_sku, $test_product_name);
        
        echo "<br>";
        start_table(TABLESTYLE2);
        table_section_title(_("Matching Results"));
        
        if ($matched_stock_id) {
            $stock_item = get_item($matched_stock_id);
            label_row(_("Matched Stock ID:"), $matched_stock_id);
            label_row(_("Stock Description:"), $stock_item['description']);
            
            // Show which rule matched
            $matching_rule = find_matching_rule($test_asin, $test_sku, $test_product_name);
            if ($matching_rule) {
                label_row(_("Matched by Rule:"), ucwords(str_replace('_', ' ', $matching_rule['match_type'])) . ": " . $matching_rule['match_value']);
                label_row(_("Rule Priority:"), $matching_rule['priority']);
            }
        } else {
            echo "<tr><td colspan='2' class='error'>" . _("No matching stock item found") . "</td></tr>";
        }
        
        end_table();
    }
}

end_form();

/**
 * Find which rule matched an item
 */
function find_matching_rule($asin, $sku, $product_name) 
{
    // Try ASIN match first
    if ($asin) {
        $sql = "SELECT * FROM ".TB_PREF."amazon_item_matching_rules 
                WHERE match_type = 'asin' AND match_value = ".db_escape($asin)." 
                AND active = 1 ORDER BY priority LIMIT 1";
        $result = db_query($sql);
        if (db_num_rows($result) > 0) {
            return db_fetch($result);
        }
    }
    
    // Try SKU match
    if ($sku) {
        $sql = "SELECT * FROM ".TB_PREF."amazon_item_matching_rules 
                WHERE match_type = 'sku' AND match_value = ".db_escape($sku)." 
                AND active = 1 ORDER BY priority LIMIT 1";
        $result = db_query($sql);
        if (db_num_rows($result) > 0) {
            return db_fetch($result);
        }
    }
    
    // Try product name exact match
    $sql = "SELECT * FROM ".TB_PREF."amazon_item_matching_rules 
            WHERE match_type = 'product_name' AND match_value = ".db_escape($product_name)." 
            AND active = 1 ORDER BY priority LIMIT 1";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        return db_fetch($result);
    }
    
    // Try keyword matching
    $sql = "SELECT * FROM ".TB_PREF."amazon_item_matching_rules 
            WHERE match_type = 'keyword' AND ".db_escape($product_name)." LIKE CONCAT('%', match_value, '%') 
            AND active = 1 ORDER BY priority LIMIT 1";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        return db_fetch($result);
    }
    
    return false;
}

end_page();
?>
