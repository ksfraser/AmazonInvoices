<?php
/**
 * Gmail Credentials Admin Screen
 * 
 * Provides interface for setting up Gmail OAuth credentials
 * for Amazon invoice email processing
 * 
 * @package AmazonInvoices
 * @author  Assistant
 * @since   1.0.0
 */

require_once dirname(__DIR__) . '/src/Services/AmazonCredentialService.php';

use AmazonInvoices\Services\AmazonCredentialService;
use AmazonInvoices\Repositories\FrontAccountingDatabaseRepository;

// Ensure we're in FrontAccounting context
if (!defined('TB_PREF')) {
    require_once 'includes/db/FA_mock_functions.php';
}

// Initialize services
$dbRepository = new FrontAccountingDatabaseRepository();
$credentialService = new AmazonCredentialService($dbRepository);

// Handle form submissions
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_gmail_credentials':
                    $credentialId = saveGmailCredentials($_POST);
                    $message = "Gmail credentials saved successfully. ID: $credentialId";
                    $messageType = 'success';
                    break;
                    
                case 'test_gmail_connection':
                    testGmailConnection($_POST);
                    $message = "Gmail connection test successful!";
                    $messageType = 'success';
                    break;
                    
                case 'authorize_gmail':
                    $authUrl = authorizeGmail($_POST);
                    header("Location: $authUrl");
                    exit;
                    
                case 'save_auth_code':
                    saveAuthorizationCode($_POST);
                    $message = "Gmail authorization completed successfully!";
                    $messageType = 'success';
                    break;
                    
                case 'delete_credentials':
                    deleteGmailCredentials($_POST['credential_id']);
                    $message = "Gmail credentials deleted successfully.";
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current credentials
$credentials = getGmailCredentials();
$emailPatterns = getEmailSearchPatterns();

/**
 * Save Gmail credentials to database
 */
function saveGmailCredentials(array $data): int
{
    global $dbRepository;
    
    $credentialData = [
        'credential_name' => $data['credential_name'] ?? 'Gmail Config',
        'client_id' => $data['client_id'] ?? '',
        'client_secret' => $data['client_secret'] ?? '',
        'scope' => $data['scope'] ?? 'https://www.googleapis.com/auth/gmail.readonly',
        'is_active' => isset($data['is_active']) ? 1 : 0
    ];
    
    if (!empty($data['credential_id'])) {
        // Update existing
        $id = $data['credential_id'];
        $name = db_escape($credentialData['credential_name']);
        $clientId = db_escape($credentialData['client_id']);
        $clientSecret = db_escape($credentialData['client_secret']);
        $scope = db_escape($credentialData['scope']);
        $isActive = $credentialData['is_active'];
        
        $sql = "UPDATE gmail_credentials SET 
                credential_name = '$name', client_id = '$clientId', client_secret = '$clientSecret', 
                scope = '$scope', is_active = '$isActive', updated_at = NOW()
                WHERE id = '$id'";
        db_query($sql);
        return (int)$data['credential_id'];
    } else {
        // Insert new
        $name = db_escape($credentialData['credential_name']);
        $clientId = db_escape($credentialData['client_id']);
        $clientSecret = db_escape($credentialData['client_secret']);
        $scope = db_escape($credentialData['scope']);
        $isActive = $credentialData['is_active'];
        
        $sql = "INSERT INTO gmail_credentials 
                (credential_name, client_id, client_secret, scope, is_active) 
                VALUES ('$name', '$clientId', '$clientSecret', '$scope', '$isActive')";
        db_query($sql);
        return db_insert_id();
    }
}

/**
 * Test Gmail connection
 */
function testGmailConnection(array $data): void
{
    // This would typically test the OAuth credentials
    // For now, we'll just validate the format
    if (empty($data['client_id']) || empty($data['client_secret'])) {
        throw new Exception("Client ID and Client Secret are required");
    }
    
    if (!preg_match('/^[0-9]+-[a-zA-Z0-9_]+\.apps\.googleusercontent\.com$/', $data['client_id'])) {
        throw new Exception("Invalid Client ID format");
    }
}

/**
 * Generate Gmail authorization URL
 */
function authorizeGmail(array $data): string
{
    $clientId = $data['client_id'];
    $scope = urlencode($data['scope'] ?? 'https://www.googleapis.com/auth/gmail.readonly');
    $redirectUri = urlencode(getCurrentUrl() . '?auth_callback=1');
    $state = bin2hex(random_bytes(16));
    
    // Store state in session for verification
    session_start();
    $_SESSION['gmail_oauth_state'] = $state;
    $_SESSION['gmail_credential_id'] = $data['credential_id'] ?? null;
    
    return "https://accounts.google.com/o/oauth2/v2/auth?" .
           "client_id=$clientId&" .
           "redirect_uri=$redirectUri&" .
           "scope=$scope&" .
           "response_type=code&" .
           "access_type=offline&" .
           "state=$state";
}

/**
 * Save authorization code and exchange for tokens
 */
function saveAuthorizationCode(array $data): void
{
    global $dbRepository;
    
    $authCode = $data['auth_code'];
    $credentialId = $data['credential_id'];
    
    // Get credential details
    $sql = "SELECT * FROM gmail_credentials WHERE id = '$credentialId'";
    $result = db_query($sql);
    $credential = db_fetch($result);
    
    if (!$credential) {
        throw new Exception("Credential not found");
    }
    
    // Exchange auth code for tokens (mock implementation)
    // In real implementation, this would call Google's OAuth token endpoint
    $accessToken = 'mock_access_token_' . time();
    $refreshToken = 'mock_refresh_token_' . time();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    
    // Update credential with tokens
    $sql = "UPDATE gmail_credentials SET 
            access_token = '$accessToken', refresh_token = '$refreshToken', token_expires_at = '$expiresAt', 
            last_used = NOW(), updated_at = NOW()
            WHERE id = '$credentialId'";
    db_query($sql);
}

/**
 * Delete Gmail credentials
 */
function deleteGmailCredentials(int $credentialId): void
{
    $sql = "DELETE FROM gmail_credentials WHERE id = '$credentialId'";
    db_query($sql);
}

/**
 * Get all Gmail credentials
 */
function getGmailCredentials(): array
{
    $sql = "SELECT * FROM gmail_credentials ORDER BY created_at DESC";
    $result = db_query($sql);
    
    $credentials = [];
    while ($row = db_fetch($result)) {
        $credentials[] = $row;
    }
    
    return $credentials;
}

/**
 * Get email search patterns
 */
function getEmailSearchPatterns(): array
{
    $sql = "SELECT * FROM email_search_patterns WHERE is_active = 1 ORDER BY priority, pattern_name";
    $result = db_query($sql);
    
    $patterns = [];
    while ($row = db_fetch($result)) {
        $patterns[] = $row;
    }
    
    return $patterns;
}

/**
 * Get current URL for OAuth redirect
 */
function getCurrentUrl(): string
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return "$protocol://$host$path";
}

// Handle OAuth callback
if (isset($_GET['auth_callback']) && isset($_GET['code'])) {
    session_start();
    if (isset($_SESSION['gmail_oauth_state']) && $_GET['state'] === $_SESSION['gmail_oauth_state']) {
        try {
            saveAuthorizationCode([
                'auth_code' => $_GET['code'],
                'credential_id' => $_SESSION['gmail_credential_id']
            ]);
            $message = "Gmail authorization completed successfully!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Authorization error: " . $e->getMessage();
            $messageType = 'error';
        }
        unset($_SESSION['gmail_oauth_state'], $_SESSION['gmail_credential_id']);
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Gmail Credentials Setup - Amazon Invoice Import</title>
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; 
        }
        .btn { padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007cba; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background: #f2f2f2; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; }
        .oauth-steps { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .oauth-steps ol { margin: 0; padding-left: 20px; }
        .oauth-steps li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gmail Credentials Setup</h1>
        <p>Configure Gmail OAuth credentials for importing Amazon invoices from email.</p>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Gmail OAuth Setup Instructions -->
        <div class="card">
            <h2>Setup Instructions</h2>
            <div class="oauth-steps">
                <h3>Google Cloud Console Setup:</h3>
                <ol>
                    <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                    <li>Create a new project or select existing project</li>
                    <li>Enable the Gmail API for your project</li>
                    <li>Go to "Credentials" section</li>
                    <li>Create OAuth 2.0 Client ID credentials</li>
                    <li>Add your domain to authorized redirect URIs: <code><?= getCurrentUrl() ?>?auth_callback=1</code></li>
                    <li>Copy the Client ID and Client Secret below</li>
                </ol>
            </div>
        </div>
        
        <!-- Add/Edit Gmail Credentials Form -->
        <div class="card">
            <h2>Gmail Credentials Configuration</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_gmail_credentials">
                <input type="hidden" name="credential_id" id="credential_id" value="">
                
                <div class="form-group">
                    <label for="credential_name">Credential Name:</label>
                    <input type="text" name="credential_name" id="credential_name" required 
                           placeholder="e.g., Production Gmail Account">
                </div>
                
                <div class="form-group">
                    <label for="client_id">Gmail Client ID:</label>
                    <input type="text" name="client_id" id="client_id" required 
                           placeholder="xxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com">
                </div>
                
                <div class="form-group">
                    <label for="client_secret">Gmail Client Secret:</label>
                    <input type="password" name="client_secret" id="client_secret" required 
                           placeholder="Your Gmail OAuth Client Secret">
                </div>
                
                <div class="form-group">
                    <label for="scope">OAuth Scope:</label>
                    <input type="text" name="scope" id="scope" 
                           value="https://www.googleapis.com/auth/gmail.readonly"
                           placeholder="Gmail API scope">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" checked> 
                        Active
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Credentials</button>
                <button type="button" class="btn btn-warning" onclick="testConnection()">Test Connection</button>
                <button type="button" class="btn btn-success" onclick="authorizeGmail()">Authorize Gmail Access</button>
                <button type="button" class="btn" onclick="resetForm()">Reset Form</button>
            </form>
        </div>
        
        <!-- Current Gmail Credentials -->
        <?php if (!empty($credentials)): ?>
        <div class="card">
            <h2>Current Gmail Credentials</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Client ID</th>
                        <th>Status</th>
                        <th>Token Status</th>
                        <th>Last Used</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($credentials as $cred): ?>
                    <tr>
                        <td><?= htmlspecialchars($cred['credential_name']) ?></td>
                        <td><?= htmlspecialchars(substr($cred['client_id'], 0, 20)) ?>...</td>
                        <td class="<?= $cred['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $cred['is_active'] ? 'Active' : 'Inactive' ?>
                        </td>
                        <td>
                            <?php if ($cred['access_token']): ?>
                                <span class="status-active">Authorized</span>
                                <?php if ($cred['token_expires_at'] && strtotime($cred['token_expires_at']) < time()): ?>
                                    <br><small style="color: #ffc107;">Token Expired</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="status-inactive">Not Authorized</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $cred['last_used'] ? date('Y-m-d H:i', strtotime($cred['last_used'])) : 'Never' ?></td>
                        <td>
                            <button class="btn btn-primary" onclick="editCredential(<?= htmlspecialchars(json_encode($cred)) ?>)">
                                Edit
                            </button>
                            <button class="btn btn-success" onclick="reauthorize(<?= $cred['id'] ?>)">
                                Re-authorize
                            </button>
                            <button class="btn btn-danger" onclick="deleteCredential(<?= $cred['id'] ?>)">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Email Search Patterns -->
        <?php if (!empty($emailPatterns)): ?>
        <div class="card">
            <h2>Email Search Patterns</h2>
            <p>These patterns are used to identify Amazon invoice emails.</p>
            <table class="table">
                <thead>
                    <tr>
                        <th>Pattern Name</th>
                        <th>Type</th>
                        <th>Pattern Value</th>
                        <th>Priority</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emailPatterns as $pattern): ?>
                    <tr>
                        <td><?= htmlspecialchars($pattern['pattern_name']) ?></td>
                        <td><?= ucfirst($pattern['pattern_type']) ?></td>
                        <td><code><?= htmlspecialchars($pattern['pattern_value']) ?></code></td>
                        <td><?= $pattern['priority'] ?></td>
                        <td><?= htmlspecialchars($pattern['description']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><small><a href="email_patterns.php">Manage Email Patterns</a></small></p>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function testConnection() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            formData.set('action', 'test_gmail_connection');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Parse response and show result
                if (html.includes('successful')) {
                    alert('Gmail connection test successful!');
                } else {
                    alert('Gmail connection test failed. Please check your credentials.');
                }
            })
            .catch(error => {
                alert('Error testing connection: ' + error.message);
            });
        }
        
        function authorizeGmail() {
            const clientId = document.getElementById('client_id').value;
            const credentialId = document.getElementById('credential_id').value;
            
            if (!clientId) {
                alert('Please enter Client ID first');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="authorize_gmail">
                <input type="hidden" name="client_id" value="${clientId}">
                <input type="hidden" name="credential_id" value="${credentialId}">
                <input type="hidden" name="scope" value="${document.getElementById('scope').value}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function editCredential(cred) {
            document.getElementById('credential_id').value = cred.id;
            document.getElementById('credential_name').value = cred.credential_name;
            document.getElementById('client_id').value = cred.client_id;
            document.getElementById('client_secret').value = cred.client_secret;
            document.getElementById('scope').value = cred.scope;
            document.getElementById('is_active').checked = cred.is_active == 1;
        }
        
        function reauthorize(credentialId) {
            document.getElementById('credential_id').value = credentialId;
            authorizeGmail();
        }
        
        function deleteCredential(credentialId) {
            if (confirm('Are you sure you want to delete this Gmail credential?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_credentials">
                    <input type="hidden" name="credential_id" value="${credentialId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resetForm() {
            document.querySelector('form').reset();
            document.getElementById('credential_id').value = '';
        }
    </script>
</body>
</html>
