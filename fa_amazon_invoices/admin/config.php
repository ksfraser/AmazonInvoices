<?php
// Admin UI for FA Amazon Invoices module configuration
// Allows setting and storing config variables in amazon_invoices_prefs table

require_once __DIR__ . '/../../vendor/autoload.php';

function amazon_invoices_admin_config_ui() {
    // Connect to FA DB (assume $db is available in FA context)
    global $db;
    $table = 'amazon_invoices_prefs';
    $fields = [
        'AMAZON_INVOICES_UPLOAD_PATH' => 'Upload Path',
        'AMAZON_INVOICES_TEMP_PATH' => 'Temp Path',
        'AMAZON_INVOICES_ENCRYPTION_KEY' => 'Encryption Key',
        'AMAZON_INVOICES_DEFAULT_SUPPLIER' => 'Default Supplier ID',
        'AMAZON_INVOICES_DEBUG' => 'Enable Debug (1/0)'
    ];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($fields as $key => $label) {
            $value = isset($_POST[$key]) ? $_POST[$key] : '';
            $sql = "REPLACE INTO $table (config_key, config_value) VALUES ('" . addslashes($key) . "', '" . addslashes($value) . "')";
            $db->query($sql);
        }
        echo '<div class="success">Configuration saved.</div>';
    }

    // Fetch current values
    $prefs = [];
    $result = $db->query("SELECT config_key, config_value FROM $table");
    while ($row = $db->fetch_assoc($result)) {
        $prefs[$row['config_key']] = $row['config_value'];
    }

    // Render form
    echo '<h2>Amazon Invoices Module Configuration</h2>';
    echo '<form method="post">';
    foreach ($fields as $key => $label) {
        $val = isset($prefs[$key]) ? htmlspecialchars($prefs[$key]) : '';
        echo "<label for='$key'>$label:</label> <input type='text' name='$key' id='$key' value='$val'><br>";
    }
    echo '<input type="submit" value="Save Configuration">';
    echo '</form>';
}
