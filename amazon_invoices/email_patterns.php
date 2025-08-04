<?php
/**
 * Email Search Patterns Admin Screen
 * 
 * Provides interface for managing email search patterns
 * for Amazon invoice email processing
 * 
 * @package AmazonInvoices
 * @author  Assistant
 * @since   1.0.0
 */

// Ensure we're in FrontAccounting context
if (!defined('TB_PREF')) {
    require_once 'includes/db/FA_mock_functions.php';
}

// Handle form submissions
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_pattern':
                    $patternId = saveEmailPattern($_POST);
                    $message = "Email pattern saved successfully. ID: $patternId";
                    $messageType = 'success';
                    break;
                    
                case 'test_pattern':
                    $result = testEmailPattern($_POST);
                    $message = "Pattern test result: $result";
                    $messageType = 'success';
                    break;
                    
                case 'delete_pattern':
                    deleteEmailPattern($_POST['pattern_id']);
                    $message = "Email pattern deleted successfully.";
                    $messageType = 'success';
                    break;
                    
                case 'toggle_active':
                    togglePatternActive($_POST['pattern_id'], $_POST['is_active']);
                    $message = "Pattern status updated successfully.";
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current patterns
$patterns = getEmailPatterns();

/**
 * Save email search pattern to database
 */
function saveEmailPattern(array $data): int
{
    $patternData = [
        'pattern_name' => $data['pattern_name'] ?? '',
        'pattern_type' => $data['pattern_type'] ?? 'subject',
        'pattern_value' => $data['pattern_value'] ?? '',
        'pattern_regex' => isset($data['pattern_regex']) ? 1 : 0,
        'is_active' => isset($data['is_active']) ? 1 : 0,
        'priority' => (int)($data['priority'] ?? 1),
        'description' => $data['description'] ?? ''
    ];
    
    if (!empty($data['pattern_id'])) {
        // Update existing
        $id = $data['pattern_id'];
        $fields = [];
        foreach ($patternData as $key => $value) {
            $escapedValue = is_string($value) ? db_escape($value) : $value;
            $fields[] = "$key = " . (is_string($value) ? "'$escapedValue'" : $escapedValue);
        }
        
        $sql = "UPDATE email_search_patterns SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = '$id'";
        db_query($sql);
        return (int)$data['pattern_id'];
    } else {
        // Insert new
        $columns = array_keys($patternData);
        $values = [];
        foreach ($patternData as $value) {
            $escapedValue = is_string($value) ? db_escape($value) : $value;
            $values[] = is_string($value) ? "'$escapedValue'" : $escapedValue;
        }
        
        $sql = "INSERT INTO email_search_patterns (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
        db_query($sql);
        return db_insert_id();
    }
}

/**
 * Test email pattern
 */
function testEmailPattern(array $data): string
{
    $pattern = $data['pattern_value'];
    $isRegex = isset($data['pattern_regex']);
    $testString = $data['test_string'] ?? '';
    
    if (empty($testString)) {
        return "No test string provided";
    }
    
    if ($isRegex) {
        $matches = @preg_match("/$pattern/i", $testString);
        if ($matches === false) {
            return "Invalid regex pattern";
        }
        return $matches ? "Pattern matches!" : "Pattern does not match";
    } else {
        $matches = stripos($testString, $pattern) !== false;
        return $matches ? "Pattern matches!" : "Pattern does not match";
    }
}

/**
 * Delete email pattern
 */
function deleteEmailPattern(int $patternId): void
{
    $sql = "DELETE FROM email_search_patterns WHERE id = '$patternId'";
    db_query($sql);
}

/**
 * Toggle pattern active status
 */
function togglePatternActive(int $patternId, int $isActive): void
{
    $sql = "UPDATE email_search_patterns SET is_active = '$isActive', updated_at = NOW() WHERE id = '$patternId'";
    db_query($sql);
}

/**
 * Get all email patterns
 */
function getEmailPatterns(): array
{
    $sql = "SELECT * FROM email_search_patterns ORDER BY priority, pattern_name";
    $result = db_query($sql);
    
    $patterns = [];
    while ($row = db_fetch($result)) {
        $patterns[] = $row;
    }
    
    return $patterns;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Search Patterns - Amazon Invoice Import</title>
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; 
        }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .btn { padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007cba; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-sm { padding: 5px 10px; font-size: 0.8em; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background: #f2f2f2; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; }
        .help-text { font-size: 0.9em; color: #666; margin-top: 5px; }
        .pattern-examples { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .pattern-examples code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
        .test-section { background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email Search Patterns</h1>
        <p>Configure patterns to identify Amazon invoice emails for automatic processing.</p>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Pattern Examples -->
        <div class="card">
            <h2>Pattern Examples</h2>
            <div class="pattern-examples">
                <h3>Common Amazon Email Patterns:</h3>
                <ul>
                    <li><strong>Subject:</strong> <code>Your order has been shipped</code> - Standard shipping notifications</li>
                    <li><strong>Subject:</strong> <code>Your Amazon order</code> - General order emails</li>
                    <li><strong>Subject:</strong> <code>Your receipt from Amazon</code> - Receipt emails</li>
                    <li><strong>From:</strong> <code>auto-confirm@amazon.com</code> - Confirmation emails</li>
                    <li><strong>From:</strong> <code>ship-confirm@amazon.com</code> - Shipping confirmations</li>
                </ul>
                
                <h3>Regex Examples:</h3>
                <ul>
                    <li><code>Your (order|receipt|invoice) (has been|from) (shipped|Amazon)</code> - Multiple variations</li>
                    <li><code>Order #[A-Z0-9-]{10,}</code> - Order number pattern</li>
                    <li><code>.*amazon\.(com|co\.uk|de|fr)$</code> - Amazon domain variations</li>
                </ul>
            </div>
        </div>
        
        <!-- Add/Edit Pattern Form -->
        <div class="card">
            <h2>Email Pattern Configuration</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_pattern">
                <input type="hidden" name="pattern_id" id="pattern_id" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="pattern_name">Pattern Name:</label>
                        <input type="text" name="pattern_name" id="pattern_name" required 
                               placeholder="e.g., Amazon Shipping Confirmation">
                    </div>
                    <div class="form-group">
                        <label for="pattern_type">Pattern Type:</label>
                        <select name="pattern_type" id="pattern_type" required>
                            <option value="subject">Email Subject</option>
                            <option value="from">From Address</option>
                            <option value="body">Email Body</option>
                            <option value="label">Gmail Label</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pattern_value">Pattern Value:</label>
                    <input type="text" name="pattern_value" id="pattern_value" required 
                           placeholder="e.g., Your order has been shipped">
                    <div class="help-text">The text or regex pattern to match against</div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="priority">Priority:</label>
                        <input type="number" name="priority" id="priority" value="1" min="1" max="100" required>
                        <div class="help-text">Lower numbers = higher priority (1 is highest)</div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="pattern_regex" id="pattern_regex"> 
                            Regular Expression
                        </label>
                        <div class="help-text">Check if this is a regex pattern</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea name="description" id="description" rows="3" 
                              placeholder="Optional description of what this pattern matches"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" checked> 
                        Active Pattern
                    </label>
                </div>
                
                <!-- Pattern Testing Section -->
                <div class="test-section">
                    <h3>Test Pattern</h3>
                    <div class="form-group">
                        <label for="test_string">Test String:</label>
                        <input type="text" id="test_string" placeholder="Enter test text to match against pattern">
                        <div class="help-text">Enter sample email subject/from/body text to test the pattern</div>
                    </div>
                    <button type="button" class="btn btn-warning" onclick="testPattern()">Test Pattern</button>
                    <div id="test-result" style="margin-top: 10px; padding: 10px; display: none;"></div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Save Pattern</button>
                    <button type="button" class="btn" onclick="resetForm()">Reset Form</button>
                </div>
            </form>
        </div>
        
        <!-- Current Patterns -->
        <?php if (!empty($patterns)): ?>
        <div class="card">
            <h2>Current Email Patterns</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Pattern</th>
                        <th>Regex</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patterns as $pattern): ?>
                    <tr>
                        <td><?= $pattern['priority'] ?></td>
                        <td><?= htmlspecialchars($pattern['pattern_name']) ?></td>
                        <td><?= ucfirst($pattern['pattern_type']) ?></td>
                        <td><code><?= htmlspecialchars(substr($pattern['pattern_value'], 0, 50)) ?><?= strlen($pattern['pattern_value']) > 50 ? '...' : '' ?></code></td>
                        <td><?= $pattern['pattern_regex'] ? '✓' : '' ?></td>
                        <td class="<?= $pattern['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $pattern['is_active'] ? 'Active' : 'Inactive' ?>
                        </td>
                        <td><?= htmlspecialchars(substr($pattern['description'] ?? '', 0, 100)) ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="editPattern(<?= htmlspecialchars(json_encode($pattern)) ?>)">
                                Edit
                            </button>
                            <button class="btn btn-<?= $pattern['is_active'] ? 'warning' : 'success' ?> btn-sm" 
                                    onclick="toggleActive(<?= $pattern['id'] ?>, <?= $pattern['is_active'] ? 0 : 1 ?>)">
                                <?= $pattern['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deletePattern(<?= $pattern['id'] ?>)">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Predefined Patterns -->
        <div class="card">
            <h2>Add Predefined Patterns</h2>
            <p>Quick add common Amazon email patterns:</p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-success btn-sm" onclick="addPredefinedPattern('shipping')">Shipping Notifications</button>
                <button class="btn btn-success btn-sm" onclick="addPredefinedPattern('receipt')">Receipt Emails</button>
                <button class="btn btn-success btn-sm" onclick="addPredefinedPattern('digital')">Digital Receipts</button>
                <button class="btn btn-success btn-sm" onclick="addPredefinedPattern('confirmation')">Order Confirmations</button>
                <button class="btn btn-success btn-sm" onclick="addPredefinedPattern('amazon-domains')">Amazon Domains</button>
            </div>
        </div>
    </div>
    
    <script>
        function testPattern() {
            const patternValue = document.getElementById('pattern_value').value;
            const isRegex = document.getElementById('pattern_regex').checked;
            const testString = document.getElementById('test_string').value;
            
            if (!patternValue || !testString) {
                alert('Please enter both pattern and test string');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'test_pattern');
            formData.append('pattern_value', patternValue);
            formData.append('test_string', testString);
            if (isRegex) formData.append('pattern_regex', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const resultDiv = document.getElementById('test-result');
                resultDiv.style.display = 'block';
                if (html.includes('matches!')) {
                    resultDiv.style.background = '#d4edda';
                    resultDiv.style.color = '#155724';
                    resultDiv.innerHTML = '✓ Pattern matches!';
                } else if (html.includes('does not match')) {
                    resultDiv.style.background = '#f8d7da';
                    resultDiv.style.color = '#721c24';
                    resultDiv.innerHTML = '✗ Pattern does not match';
                } else {
                    resultDiv.style.background = '#fff3cd';
                    resultDiv.style.color = '#856404';
                    resultDiv.innerHTML = '⚠ ' + (html.includes('Invalid regex') ? 'Invalid regex pattern' : 'Test error');
                }
            })
            .catch(error => {
                const resultDiv = document.getElementById('test-result');
                resultDiv.style.display = 'block';
                resultDiv.style.background = '#f8d7da';
                resultDiv.style.color = '#721c24';
                resultDiv.innerHTML = '✗ Test error: ' + error.message;
            });
        }
        
        function editPattern(pattern) {
            document.getElementById('pattern_id').value = pattern.id;
            document.getElementById('pattern_name').value = pattern.pattern_name;
            document.getElementById('pattern_type').value = pattern.pattern_type;
            document.getElementById('pattern_value').value = pattern.pattern_value;
            document.getElementById('priority').value = pattern.priority;
            document.getElementById('description').value = pattern.description || '';
            document.getElementById('pattern_regex').checked = pattern.pattern_regex == 1;
            document.getElementById('is_active').checked = pattern.is_active == 1;
        }
        
        function toggleActive(patternId, isActive) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="pattern_id" value="${patternId}">
                <input type="hidden" name="is_active" value="${isActive}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deletePattern(patternId) {
            if (confirm('Are you sure you want to delete this email pattern?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_pattern">
                    <input type="hidden" name="pattern_id" value="${patternId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function addPredefinedPattern(type) {
            const patterns = {
                'shipping': {
                    name: 'Amazon Shipping Notification',
                    type: 'subject',
                    value: 'Your order has been shipped',
                    description: 'Standard Amazon shipping confirmation emails'
                },
                'receipt': {
                    name: 'Amazon Receipt',
                    type: 'subject',
                    value: 'Your receipt from Amazon',
                    description: 'Amazon receipt emails'
                },
                'digital': {
                    name: 'Amazon Digital Receipt',
                    type: 'subject',
                    value: 'Your Amazon digital receipt',
                    description: 'Digital purchase receipts from Amazon'
                },
                'confirmation': {
                    name: 'Order Confirmation',
                    type: 'from',
                    value: 'auto-confirm@amazon.com',
                    description: 'Amazon order confirmation emails'
                },
                'amazon-domains': {
                    name: 'Amazon Domain Pattern',
                    type: 'from',
                    value: '.*@amazon\\.(com|co\\.uk|de|fr|it|es|ca|com\\.au|co\\.jp)$',
                    description: 'Match emails from various Amazon domains',
                    regex: true
                }
            };
            
            const pattern = patterns[type];
            if (pattern) {
                document.getElementById('pattern_id').value = '';
                document.getElementById('pattern_name').value = pattern.name;
                document.getElementById('pattern_type').value = pattern.type;
                document.getElementById('pattern_value').value = pattern.value;
                document.getElementById('description').value = pattern.description;
                document.getElementById('pattern_regex').checked = pattern.regex || false;
                document.getElementById('is_active').checked = true;
                document.getElementById('priority').value = 1;
            }
        }
        
        function resetForm() {
            document.querySelector('form').reset();
            document.getElementById('pattern_id').value = '';
            document.getElementById('test-result').style.display = 'none';
        }
    </script>
</body>
</html>
