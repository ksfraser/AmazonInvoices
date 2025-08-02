<?php
/**
 * Amazon Payment Allocation Interface
 */

$page_security = 'SA_AMAZON_PAYMENTS';
$path_to_root = "../..";

include($path_to_root . "/includes/session.inc");
include($path_to_root . "/includes/ui.inc");
include($path_to_root . "/gl/includes/gl_db.inc");

include_once(dirname(__FILE__) . "/includes/db_functions.php");

page(_($help_context = "Amazon Payment Allocation"));

$selected_id = get_post('selected_id', get_get('id'));

if (isset($_POST['allocate_payment'])) {
    $payment_id = $_POST['payment_id'];
    $fa_bank_account = $_POST['fa_bank_account'];
    $fa_payment_type = $_POST['fa_payment_type'];
    $notes = $_POST['notes'];
    
    if ($fa_bank_account) {
        update_amazon_payment_allocation($payment_id, $fa_bank_account, $fa_payment_type, $notes);
        
        $staging_invoice_id = $_POST['staging_invoice_id'];
        add_amazon_processing_log($staging_invoice_id, 'payment_allocated', 
            "Payment allocated to bank account: {$fa_bank_account}");
        
        display_notification(_("Payment allocated successfully"));
    } else {
        display_error(_("Please select a bank account"));
    }
}

if (isset($_POST['split_payment'])) {
    $payment_id = $_POST['payment_id'];
    $split_amounts = $_POST['split_amounts'];
    $split_accounts = $_POST['split_accounts'];
    $split_types = $_POST['split_types'];
    
    // Delete original payment
    $sql = "DELETE FROM ".TB_PREF."amazon_payment_staging WHERE id = ".db_escape($payment_id);
    db_query($sql, "Failed to delete original payment");
    
    // Create split payments
    $staging_invoice_id = $_POST['staging_invoice_id'];
    foreach ($split_amounts as $index => $amount) {
        if ($amount > 0 && $split_accounts[$index]) {
            add_amazon_payment_staging(
                $staging_invoice_id,
                'split',
                $amount,
                "Split payment " . ($index + 1),
                $split_accounts[$index]
            );
            
            // Mark as allocated
            $new_payment_id = db_insert_id();
            update_amazon_payment_allocation($new_payment_id, $split_accounts[$index], $split_types[$index]);
        }
    }
    
    add_amazon_processing_log($staging_invoice_id, 'payment_split', 
        "Payment split into " . count($split_amounts) . " parts");
    
    display_notification(_("Payment split successfully"));
}

start_form();

// Invoice selection
start_table(TABLESTYLE2);
table_section_title(_("Select Invoice for Payment Allocation"));

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
    $payments = get_amazon_payment_staging($selected_id);
    
    echo "<br>";
    start_table(TABLESTYLE2);
    table_section_title(_("Invoice Summary"));
    
    label_row(_("Invoice Number:"), $invoice['invoice_number']);
    label_row(_("Invoice Date:"), sql2date($invoice['invoice_date']));
    amount_row(_("Total Amount:"), $invoice['invoice_total']);
    
    end_table(1);
    
    echo "<br>";
    
    // Payment allocation
    start_table(TABLESTYLE);
    table_header(array(
        _("Payment Method"),
        _("Amount"),
        _("Reference"),
        _("Bank Account"),
        _("Payment Type"),
        _("Status"),
        _("Actions")
    ));
    
    $bank_accounts = get_bank_accounts_list();
    $payment_types = get_payment_types_list();
    
    while ($payment = db_fetch($payments)) {
        alt_table_row_color($k);
        
        label_cell(ucwords(str_replace('_', ' ', $payment['payment_method'])));
        amount_cell($payment['amount']);
        label_cell($payment['payment_reference']);
        
        if ($payment['allocation_complete']) {
            $bank_name = get_bank_account_name($payment['fa_bank_account']);
            label_cell($bank_name);
            
            $payment_type_name = get_payment_type_name($payment['fa_payment_type']);
            label_cell($payment_type_name);
            
            echo "<td class='success'>" . _("Allocated") . "</td>";
            label_cell(_("Completed"));
        } else {
            // Bank account selection
            start_cell();
            array_selector('bank_account_' . $payment['id'], null, $bank_accounts, array('select_submit' => false));
            end_cell();
            
            // Payment type selection
            start_cell();
            array_selector('payment_type_' . $payment['id'], null, $payment_types, array('select_submit' => false));
            end_cell();
            
            echo "<td class='warning'>" . _("Pending") . "</td>";
            
            start_cell();
            submit('allocate_' . $payment['id'], _("Allocate"), false, "", 'default');
            echo " ";
            submit('split_' . $payment['id'], _("Split"), false, "", false);
            end_cell();
        }
        
        end_row();
    }
    
    end_table();
    
    // Notes section
    echo "<br>";
    start_table(TABLESTYLE2);
    textarea_row(_("Notes:"), 'allocation_notes', null, 40, 3);
    end_table();
}

// Handle individual payment allocation
foreach ($_POST as $key => $value) {
    if (strpos($key, 'allocate_') === 0) {
        $payment_id = substr($key, 9);
        $fa_bank_account = $_POST['bank_account_' . $payment_id];
        $fa_payment_type = $_POST['payment_type_' . $payment_id];
        $notes = $_POST['allocation_notes'];
        
        if ($fa_bank_account) {
            update_amazon_payment_allocation($payment_id, $fa_bank_account, $fa_payment_type, $notes);
            add_amazon_processing_log($selected_id, 'payment_allocated', 
                "Payment allocated to bank account: {$fa_bank_account}");
            display_notification(_("Payment allocated successfully"));
            meta_forward($_SERVER['PHP_SELF'] . "?id=" . $selected_id);
        }
    }
    
    if (strpos($key, 'split_') === 0) {
        $payment_id = substr($key, 6);
        show_split_payment_form($payment_id, $selected_id);
    }
}

end_form();

/**
 * Get bank accounts for dropdown
 */
function get_bank_accounts_list() 
{
    $accounts = array();
    $accounts[''] = _("Select bank account...");
    
    $sql = "SELECT id, bank_name, bank_account_name FROM ".TB_PREF."bank_accounts 
            WHERE NOT inactive ORDER BY bank_name, bank_account_name";
    
    $result = db_query($sql, "Failed to get bank accounts");
    
    while ($row = db_fetch($result)) {
        $accounts[$row['id']] = $row['bank_name'] . " - " . $row['bank_account_name'];
    }
    
    return $accounts;
}

/**
 * Get payment types for dropdown
 */
function get_payment_types_list() 
{
    $types = array();
    $types[''] = _("Select payment type...");
    
    $sql = "SELECT id, name FROM ".TB_PREF."payment_terms ORDER BY name";
    
    $result = db_query($sql, "Failed to get payment types");
    
    while ($row = db_fetch($result)) {
        $types[$row['id']] = $row['name'];
    }
    
    return $types;
}

/**
 * Get bank account name by ID
 */
function get_bank_account_name($bank_id) 
{
    $sql = "SELECT CONCAT(bank_name, ' - ', bank_account_name) as name 
            FROM ".TB_PREF."bank_accounts WHERE id = ".db_escape($bank_id);
    
    $result = db_query($sql, "Failed to get bank account name");
    $row = db_fetch($result);
    
    return $row ? $row['name'] : _("Unknown");
}

/**
 * Get payment type name by ID
 */
function get_payment_type_name($type_id) 
{
    $sql = "SELECT name FROM ".TB_PREF."payment_terms WHERE id = ".db_escape($type_id);
    
    $result = db_query($sql, "Failed to get payment type name");
    $row = db_fetch($result);
    
    return $row ? $row['name'] : _("Unknown");
}

/**
 * Show split payment form
 */
function show_split_payment_form($payment_id, $staging_invoice_id) 
{
    global $Ajax;
    
    // Get payment details
    $sql = "SELECT * FROM ".TB_PREF."amazon_payment_staging WHERE id = ".db_escape($payment_id);
    $result = db_query($sql, "Failed to get payment details");
    $payment = db_fetch($result);
    
    if (!$payment) return;
    
    echo "<br>";
    start_table(TABLESTYLE2);
    table_section_title(sprintf(_("Split Payment: %s"), $payment['payment_method']));
    
    label_row(_("Original Amount:"), number_format($payment['amount'], 2));
    
    echo "<tr><td colspan='2'>";
    echo "<table class='tablestyle'>";
    echo "<tr class='tableheader'>";
    echo "<td>" . _("Amount") . "</td>";
    echo "<td>" . _("Bank Account") . "</td>";
    echo "<td>" . _("Payment Type") . "</td>";
    echo "</tr>";
    
    $bank_accounts = get_bank_accounts_list();
    $payment_types = get_payment_types_list();
    
    // Show 4 split options
    for ($i = 1; $i <= 4; $i++) {
        echo "<tr>";
        echo "<td>";
        small_amount_cells(null, "split_amount_{$i}", null);
        echo "</td>";
        echo "<td>";
        array_selector("split_account_{$i}", null, $bank_accounts, array('select_submit' => false));
        echo "</td>";
        echo "<td>";
        array_selector("split_type_{$i}", null, $payment_types, array('select_submit' => false));
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</td></tr>";
    
    hidden('payment_id', $payment_id);
    hidden('staging_invoice_id', $staging_invoice_id);
    
    submit_row('confirm_split', _("Confirm Split"), true, _("Split this payment?"), true);
    
    end_table();
}

end_page();
?>
