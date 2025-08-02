<?php
/**
 * Amazon Invoices Module Settings
 */

$page_security = 'SA_AMAZON_INVOICES';
$path_to_root = "../..";

include($path_to_root . "/includes/session.inc");
include($path_to_root . "/includes/ui.inc");
include($path_to_root . "/admin/db/company_db.inc");

page(_($help_context = "Amazon Invoices Settings"));

if (isset($_POST['save_settings'])) {
    $settings = array(
        'amazon_email' => $_POST['amazon_email'],
        'amazon_password' => $_POST['amazon_password'],
        'download_path' => $_POST['download_path'],
        'default_supplier' => $_POST['default_supplier'],
        'auto_process' => isset($_POST['auto_process']) ? 1 : 0,
        'notification_email' => $_POST['notification_email'],
        'backup_enabled' => isset($_POST['backup_enabled']) ? 1 : 0,
        'max_file_age_days' => $_POST['max_file_age_days']
    );
    
    foreach ($settings as $key => $value) {
        update_company_pref("amazon_" . $key, $value);
    }
    
    display_notification(_("Settings saved successfully"));
}

// Get current settings
$current_settings = array();
$setting_keys = array('email', 'password', 'download_path', 'default_supplier', 
                     'auto_process', 'notification_email', 'backup_enabled', 'max_file_age_days');

foreach ($setting_keys as $key) {
    $current_settings[$key] = get_company_pref("amazon_" . $key);
}

start_form();

start_table(TABLESTYLE2);
table_section_title(_("Amazon Account Settings"));

text_row(_("Amazon Email:"), 'amazon_email', $current_settings['email'], 40, 255);
password_row(_("Amazon Password:"), 'amazon_password', $current_settings['password'], 40, 255);

end_table(1);

echo "<br>";

start_table(TABLESTYLE2);
table_section_title(_("Download Settings"));

text_row(_("Download Path:"), 'download_path', 
         $current_settings['download_path'] ?: ($path_to_root . '/tmp/amazon_invoices/'), 60, 255);

// Supplier selection
$suppliers = array();
$suppliers[''] = _("Select default supplier...");

$sql = "SELECT supplier_id, supp_name FROM ".TB_PREF."suppliers 
        WHERE NOT inactive ORDER BY supp_name";
$result = db_query($sql, "Failed to get suppliers");

while ($row = db_fetch($result)) {
    $suppliers[$row['supplier_id']] = $row['supp_name'];
}

array_selector_row(_("Default Amazon Supplier:"), 'default_supplier', 
                  $current_settings['default_supplier'], $suppliers);

check_row(_("Enable Auto Processing:"), 'auto_process', $current_settings['auto_process']);

end_table(1);

echo "<br>";

start_table(TABLESTYLE2);
table_section_title(_("Notification Settings"));

text_row(_("Notification Email:"), 'notification_email', $current_settings['notification_email'], 40, 255);

end_table(1);

echo "<br>";

start_table(TABLESTYLE2);
table_section_title(_("File Management"));

check_row(_("Enable Backup:"), 'backup_enabled', $current_settings['backup_enabled']);
text_row(_("Max File Age (Days):"), 'max_file_age_days', 
         $current_settings['max_file_age_days'] ?: 30, 10, 3);

end_table(1);

echo "<br>";

submit_center('save_settings', _("Save Settings"), true, false, 'default');

end_form();

// System status section
echo "<br>";
start_table(TABLESTYLE);
table_header(array(_("System Check"), _("Status"), _("Details")));

// Check download directory
$download_path = $current_settings['download_path'] ?: ($path_to_root . '/tmp/amazon_invoices/');
$download_writable = is_dir($download_path) && is_writable($download_path);

alt_table_row_color($k);
label_cell(_("Download Directory"));
if ($download_writable) {
    echo "<td class='success'>" . _("OK") . "</td>";
    label_cell($download_path);
} else {
    echo "<td class='error'>" . _("Error") . "</td>";
    label_cell(_("Directory not writable or does not exist"));
}
end_row();

// Check database tables
$tables_exist = check_amazon_tables_exist();
alt_table_row_color($k);
label_cell(_("Database Tables"));
if ($tables_exist) {
    echo "<td class='success'>" . _("OK") . "</td>";
    label_cell(_("All required tables exist"));
} else {
    echo "<td class='error'>" . _("Error") . "</td>";
    label_cell(_("Some tables are missing"));
}
end_row();

// Check default supplier
$supplier_configured = !empty($current_settings['default_supplier']);
alt_table_row_color($k);
label_cell(_("Default Supplier"));
if ($supplier_configured) {
    echo "<td class='success'>" . _("OK") . "</td>";
    
    $supplier_name = get_supplier_name($current_settings['default_supplier']);
    label_cell($supplier_name);
} else {
    echo "<td class='warning'>" . _("Warning") . "</td>";
    label_cell(_("No default supplier configured"));
}
end_row();

// Check staging invoices count
$staging_count = get_staging_invoices_count();
alt_table_row_color($k);
label_cell(_("Staging Invoices"));
if ($staging_count == 0) {
    echo "<td class='success'>" . _("OK") . "</td>";
    label_cell(_("No pending invoices"));
} else {
    echo "<td class='info'>" . _("Info") . "</td>";
    label_cell(sprintf(_("%d invoices in staging"), $staging_count));
}
end_row();

end_table();

// Recent activity log
echo "<br>";
start_table(TABLESTYLE);
table_header(array(_("Recent Activity"), _("Date/Time"), _("User"), _("Details")));

$sql = "SELECT l.*, i.invoice_number 
        FROM ".TB_PREF."amazon_processing_log l 
        LEFT JOIN ".TB_PREF."amazon_invoices_staging i ON l.staging_invoice_id = i.id 
        ORDER BY l.created_at DESC LIMIT 20";

$result = db_query($sql, "Failed to get activity log");

while ($log = db_fetch($result)) {
    alt_table_row_color($k);
    
    $action_text = ucwords(str_replace('_', ' ', $log['action']));
    if ($log['invoice_number']) {
        $action_text .= " (" . $log['invoice_number'] . ")";
    }
    
    label_cell($action_text);
    label_cell($log['created_at']);
    label_cell($log['user_id']);
    label_cell($log['details']);
    
    end_row();
}

end_table();

/**
 * Check if all required Amazon tables exist
 */
function check_amazon_tables_exist() 
{
    $required_tables = array(
        'amazon_invoices_staging',
        'amazon_invoice_items_staging', 
        'amazon_payment_staging',
        'amazon_item_matching_rules',
        'amazon_processing_log'
    );
    
    foreach ($required_tables as $table) {
        $sql = "SHOW TABLES LIKE '".TB_PREF.$table."'";
        $result = db_query($sql);
        
        if (db_num_rows($result) == 0) {
            return false;
        }
    }
    
    return true;
}

/**
 * Get supplier name by ID
 */
function get_supplier_name($supplier_id) 
{
    $sql = "SELECT supp_name FROM ".TB_PREF."suppliers WHERE supplier_id = ".db_escape($supplier_id);
    $result = db_query($sql, "Failed to get supplier name");
    $row = db_fetch($result);
    
    return $row ? $row['supp_name'] : _("Unknown Supplier");
}

/**
 * Get count of staging invoices
 */
function get_staging_invoices_count() 
{
    $sql = "SELECT COUNT(*) as count FROM ".TB_PREF."amazon_invoices_staging 
            WHERE status IN ('pending', 'processing')";
    $result = db_query($sql, "Failed to get staging count");
    $row = db_fetch($result);
    
    return $row['count'];
}

end_page();
?>
