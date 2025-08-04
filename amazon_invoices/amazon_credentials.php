<?php
/**
 * Amazon API Credentials Configuration Screen
 * 
 * This screen provides comprehensive Amazon API credential management
 * supporting multiple authentication methods including SP-API, OAuth, and legacy scraping.
 * 
 * @package AmazonInvoices
 * @author  Your Name
 * @since   1.0.0
 */

$page_security = 'SA_AMAZON_INVOICES';
$path_to_root = "../..";

include($path_to_root . "/includes/session.inc");
include($path_to_root . "/includes/ui.inc");
include($path_to_root . "/admin/db/company_db.inc");

// Include our modern architecture and helpers
require_once(dirname(__FILE__) . '/includes/helpers.php');
require_once(dirname(__FILE__) . '/../src/Services/AmazonCredentialService.php');

page(_($help_context = "Amazon API Credentials"));

// Initialize credential service
$credentialService = new \AmazonInvoices\Services\AmazonCredentialService();

if (isset($_POST['save_credentials'])) {
    try {
        $authMethod = $_POST['auth_method'];
        
        switch ($authMethod) {
            case 'sp_api':
                $credentials = [
                    'auth_method' => 'sp_api',
                    'client_id' => $_POST['client_id'],
                    'client_secret' => $_POST['client_secret'],
                    'refresh_token' => $_POST['refresh_token'],
                    'region' => $_POST['region'],
                    'marketplace_id' => $_POST['marketplace_id'],
                    'role_arn' => $_POST['role_arn'] ?? '',
                    'access_key_id' => $_POST['access_key_id'] ?? '',
                    'secret_access_key' => $_POST['secret_access_key'] ?? ''
                ];
                break;
                
            case 'oauth':
                $credentials = [
                    'auth_method' => 'oauth',
                    'oauth_client_id' => $_POST['oauth_client_id'],
                    'oauth_client_secret' => $_POST['oauth_client_secret'],
                    'oauth_redirect_uri' => $_POST['oauth_redirect_uri'],
                    'oauth_scope' => $_POST['oauth_scope'],
                    'region' => $_POST['region']
                ];
                break;
                
            case 'scraping':
                $credentials = [
                    'auth_method' => 'scraping',
                    'amazon_email' => $_POST['amazon_email'],
                    'amazon_password' => $_POST['amazon_password'],
                    'two_factor_enabled' => isset($_POST['two_factor_enabled']),
                    'backup_email' => $_POST['backup_email'] ?? '',
                    'region' => $_POST['region']
                ];
                break;
                
            default:
                throw new \InvalidArgumentException('Invalid authentication method');
        }
        
        // Test the credentials
        $testResult = $credentialService->testCredentials($credentials);
        
        if ($testResult['success']) {
            // Save credentials (encrypted)
            $credentialService->saveCredentials($credentials);
            
            display_notification(_("Credentials saved and verified successfully"));
            
            // Log the configuration change
            $credentialService->logActivity('credentials_updated', $_SESSION['wa_current_user']->user, 
                                           "Authentication method: {$authMethod}");
        } else {
            display_error(_("Credential test failed: ") . $testResult['error']);
        }
        
    } catch (\Exception $e) {
        display_error(_("Failed to save credentials: ") . $e->getMessage());
    }
}

if (isset($_POST['test_connection'])) {
    try {
        $testResult = $credentialService->testCurrentCredentials();
        
        if ($testResult['success']) {
            display_notification(_("Connection test successful! ") . $testResult['details']);
        } else {
            display_error(_("Connection test failed: ") . $testResult['error']);
        }
        
    } catch (\Exception $e) {
        display_error(_("Connection test error: ") . $e->getMessage());
    }
}

// Get current credentials (for display - sensitive data will be masked)
$currentCredentials = $credentialService->getCurrentCredentials();
$currentMethod = $currentCredentials['auth_method'] ?? 'sp_api';

start_form();

// Authentication Method Selection
start_table(TABLESTYLE2);
table_section_title(_("Authentication Method"));

$auth_methods = [
    'sp_api' => _('Amazon SP-API (Recommended)'),
    'oauth' => _('OAuth 2.0 Authentication'),
    'scraping' => _('Web Scraping (Legacy)')
];

echo "<tr><td>" . _("Authentication Method:") . "</td><td>";
array_selector('auth_method', $currentMethod, $auth_methods, 
               array('spec_option' => 0, 'select_submit' => true));
echo "</td></tr>";

end_table(1);

echo "<div id='credential-forms'>";

// SP-API Credentials Form
echo "<div id='sp-api-form' style='display:" . ($currentMethod == 'sp_api' ? 'block' : 'none') . "'>";
echo "<br>";
start_table(TABLESTYLE2);
table_section_title(_("Amazon SP-API Credentials"));

text_row(_("Client ID:"), 'client_id', 
         $currentCredentials['client_id'] ?? '', 60, 255,
         _("Your SP-API application client ID"));

password_row(_("Client Secret:"), 'client_secret', 
             $currentCredentials['client_secret'] ?? '', 60, 255,
             _("Your SP-API application client secret"));

text_row(_("Refresh Token:"), 'refresh_token', 
         $currentCredentials['refresh_token'] ?? '', 60, 255,
         _("Long-lived refresh token from authorization"));

// Region selection
$regions = [
    'us-east-1' => _('North America (us-east-1)'),
    'eu-west-1' => _('Europe (eu-west-1)'),
    'us-west-2' => _('Far East (us-west-2)')
];

array_selector_row(_("Region:"), 'region', 
                  $currentCredentials['region'] ?? 'us-east-1', $regions);

text_row(_("Marketplace ID:"), 'marketplace_id', 
         $currentCredentials['marketplace_id'] ?? 'ATVPDKIKX0DER', 60, 100,
         _("Amazon marketplace identifier"));

end_table(1);

echo "<br>";
start_table(TABLESTYLE2);
table_section_title(_("AWS IAM Credentials (Optional)"));

text_row(_("Role ARN:"), 'role_arn', 
         $currentCredentials['role_arn'] ?? '', 80, 255,
         _("AWS IAM role ARN for enhanced security"));

text_row(_("Access Key ID:"), 'access_key_id', 
         $currentCredentials['access_key_id'] ?? '', 40, 255,
         _("AWS access key ID"));

password_row(_("Secret Access Key:"), 'secret_access_key', 
             $currentCredentials['secret_access_key'] ?? '', 60, 255,
             _("AWS secret access key"));

end_table(1);
echo "</div>";

// OAuth Credentials Form
echo "<div id='oauth-form' style='display:" . ($currentMethod == 'oauth' ? 'block' : 'none') . "'>";
echo "<br>";
start_table(TABLESTYLE2);
table_section_title(_("OAuth 2.0 Credentials"));

text_row(_("OAuth Client ID:"), 'oauth_client_id', 
         $currentCredentials['oauth_client_id'] ?? '', 60, 255);

password_row(_("OAuth Client Secret:"), 'oauth_client_secret', 
             $currentCredentials['oauth_client_secret'] ?? '', 60, 255);

text_row(_("Redirect URI:"), 'oauth_redirect_uri', 
         $currentCredentials['oauth_redirect_uri'] ?? get_base_url() . '/amazon_invoices/oauth_callback.php', 
         80, 255);

text_row(_("OAuth Scope:"), 'oauth_scope', 
         $currentCredentials['oauth_scope'] ?? 'sellingpartnerapi::notifications', 60, 255);

array_selector_row(_("Region:"), 'region', 
                  $currentCredentials['region'] ?? 'us-east-1', $regions);

end_table(1);
echo "</div>";

// Scraping Credentials Form
echo "<div id='scraping-form' style='display:" . ($currentMethod == 'scraping' ? 'block' : 'none') . "'>";
echo "<br>";
start_table(TABLESTYLE2);
table_section_title(_("Amazon Account Credentials (Legacy)"));

label_row("<span style='color: orange;'>" . _("⚠️ Warning:") . "</span>", 
          _("Web scraping may violate Amazon's Terms of Service. Use SP-API for production."));

text_row(_("Amazon Email:"), 'amazon_email', 
         $currentCredentials['amazon_email'] ?? '', 40, 255);

password_row(_("Amazon Password:"), 'amazon_password', 
             $currentCredentials['amazon_password'] ?? '', 40, 255);

check_row(_("Two-Factor Authentication:"), 'two_factor_enabled', 
          $currentCredentials['two_factor_enabled'] ?? false);

text_row(_("Backup Email:"), 'backup_email', 
         $currentCredentials['backup_email'] ?? '', 40, 255,
         _("Fallback email for notifications"));

array_selector_row(_("Region:"), 'region', 
                  $currentCredentials['region'] ?? 'us-east-1', $regions);

end_table(1);
echo "</div>";

echo "</div>"; // End credential-forms

echo "<br>";

// Action buttons
start_table(TABLESTYLE);
echo "<tr>";
echo "<td>";
submit_center('test_connection', _("Test Connection"), true, false, 'default');
echo "</td>";
echo "<td>";
submit_center('save_credentials', _("Save Credentials"), true, false, 'default');
echo "</td>";
echo "</tr>";
end_table();

end_form();

// Credential Status and Information
echo "<br>";
start_table(TABLESTYLE);
table_header([_("Credential Status"), _("Status"), _("Details"), _("Last Updated")]);

$status = $credentialService->getCredentialStatus();

alt_table_row_color($k);
label_cell(_("Authentication"));
if ($status['configured']) {
    echo "<td class='success'>" . _("✅ Configured") . "</td>";
    label_cell($status['method_display']);
} else {
    echo "<td class='error'>" . _("❌ Not Configured") . "</td>";
    label_cell(_("No credentials configured"));
}
label_cell($status['last_updated'] ?? _("Never"));
end_row();

alt_table_row_color($k);
label_cell(_("Connection Test"));
if ($status['last_test_success']) {
    echo "<td class='success'>" . _("✅ Success") . "</td>";
    label_cell($status['last_test_details']);
} else {
    echo "<td class='error'>" . _("❌ Failed") . "</td>";
    label_cell($status['last_test_error'] ?? _("No test performed"));
}
label_cell($status['last_test_date'] ?? _("Never"));
end_row();

alt_table_row_color($k);
label_cell(_("Token Status"));
if ($status['token_valid']) {
    echo "<td class='success'>" . _("✅ Valid") . "</td>";
    label_cell(_("Token expires: ") . ($status['token_expires'] ?? _("Unknown")));
} else {
    echo "<td class='warning'>" . _("⚠️ Invalid/Expired") . "</td>";
    label_cell(_("Token refresh required"));
}
label_cell($status['token_last_refresh'] ?? _("Never"));
end_row();

end_table();

// API Usage Statistics
if ($status['configured']) {
    echo "<br>";
    start_table(TABLESTYLE);
    table_header([_("API Usage"), _("Today"), _("This Week"), _("This Month"), _("Limit")]);
    
    $usage = $credentialService->getApiUsageStats();
    
    alt_table_row_color($k);
    label_cell(_("API Calls"));
    label_cell($usage['calls_today'] ?? 0);
    label_cell($usage['calls_week'] ?? 0);
    label_cell($usage['calls_month'] ?? 0);
    label_cell($usage['monthly_limit'] ?? _("N/A"));
    end_row();
    
    alt_table_row_color($k);
    label_cell(_("Download Requests"));
    label_cell($usage['downloads_today'] ?? 0);
    label_cell($usage['downloads_week'] ?? 0);
    label_cell($usage['downloads_month'] ?? 0);
    label_cell($usage['download_limit'] ?? _("N/A"));
    end_row();
    
    end_table();
}

// Setup Instructions
echo "<br>";
start_table(TABLESTYLE2);
table_section_title(_("Setup Instructions"));

echo "<tr><td colspan='2'>";
echo "<h4>" . _("SP-API Setup (Recommended):") . "</h4>";
echo "<ol>";
echo "<li>" . _("Register as Amazon SP-API developer at") . " <a href='https://developer.amazonservices.com/' target='_blank'>developer.amazonservices.com</a></li>";
echo "<li>" . _("Create a new SP-API application") . "</li>";
echo "<li>" . _("Configure OAuth redirect URI:") . " <code>" . get_base_url() . "/amazon_invoices/oauth_callback.php</code></li>";
echo "<li>" . _("Request access to 'Reports API' and 'Orders API'") . "</li>";
echo "<li>" . _("Generate refresh token through OAuth flow") . "</li>";
echo "<li>" . _("Enter credentials above and test connection") . "</li>";
echo "</ol>";

echo "<h4>" . _("OAuth 2.0 Setup:") . "</h4>";
echo "<ol>";
echo "<li>" . _("Register OAuth application with Amazon") . "</li>";
echo "<li>" . _("Configure redirect URI and scopes") . "</li>";
echo "<li>" . _("Complete OAuth authorization flow") . "</li>";
echo "</ol>";

echo "<h4>" . _("Security Notes:") . "</h4>";
echo "<ul>";
echo "<li>" . _("All credentials are encrypted before storage") . "</li>";
echo "<li>" . _("Use SP-API for production environments") . "</li>";
echo "<li>" . _("Regularly rotate credentials for security") . "</li>";
echo "<li>" . _("Monitor API usage to avoid rate limits") . "</li>";
echo "</ul>";
echo "</td></tr>";

end_table();

?>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Handle authentication method changes
    const authSelect = document.querySelector('select[name="auth_method"]');
    const forms = {
        'sp_api': document.getElementById('sp-api-form'),
        'oauth': document.getElementById('oauth-form'),
        'scraping': document.getElementById('scraping-form')
    };
    
    function showAuthForm(method) {
        Object.keys(forms).forEach(key => {
            if (forms[key]) {
                forms[key].style.display = (key === method) ? 'block' : 'none';
            }
        });
    }
    
    if (authSelect) {
        authSelect.addEventListener('change', function() {
            showAuthForm(this.value);
        });
    }
    
    // Auto-refresh token status every 30 seconds
    setInterval(function() {
        fetch('<?php echo get_base_url(); ?>/amazon_invoices/api/token_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.needs_refresh) {
                    location.reload();
                }
            })
            .catch(console.error);
    }, 30000);
});
</script>

<style>
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; font-weight: bold; }

#credential-forms {
    transition: all 0.3s ease;
}

code {
    background-color: #f4f4f4;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
}

.help-text {
    font-size: 0.9em;
    color: #666;
    font-style: italic;
}
</style>

<?php
end_page();
?>
