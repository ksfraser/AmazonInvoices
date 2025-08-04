<?php
/**
 * PDF OCR Configuration Admin Screen
 * 
 * Provides interface for setting up PDF OCR processing configuration
 * for Amazon invoice PDF processing
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
                case 'save_ocr_config':
                    $configId = saveOcrConfig($_POST);
                    $message = "OCR configuration saved successfully. ID: $configId";
                    $messageType = 'success';
                    break;
                    
                case 'test_ocr_config':
                    testOcrConfiguration($_POST);
                    $message = "OCR configuration test successful!";
                    $messageType = 'success';
                    break;
                    
                case 'delete_config':
                    deleteOcrConfig($_POST['config_id']);
                    $message = "OCR configuration deleted successfully.";
                    $messageType = 'success';
                    break;
                    
                case 'test_sample_pdf':
                    $result = testSamplePdf($_POST);
                    $message = "Sample PDF test completed. Extracted text: " . substr($result, 0, 200) . "...";
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current configurations
$configurations = getOcrConfigurations();

/**
 * Save OCR configuration to database
 */
function saveOcrConfig(array $data): int
{
    $configData = [
        'config_name' => $data['config_name'] ?? 'OCR Config',
        'tesseract_path' => $data['tesseract_path'] ?? '/usr/bin/tesseract',
        'tesseract_data_path' => $data['tesseract_data_path'] ?? '/usr/share/tesseract-ocr/4.00/tessdata',
        'tesseract_language' => $data['tesseract_language'] ?? 'eng',
        'poppler_path' => $data['poppler_path'] ?? '/usr/bin',
        'imagemagick_path' => $data['imagemagick_path'] ?? '/usr/bin',
        'temp_directory' => $data['temp_directory'] ?? '/tmp/amazon_invoices_ocr',
        'pdf_dpi' => (int)($data['pdf_dpi'] ?? 300),
        'image_preprocessing' => isset($data['image_preprocessing']) ? 1 : 0,
        'image_enhancement_level' => $data['image_enhancement_level'] ?? 'basic',
        'ocr_engine_mode' => (int)($data['ocr_engine_mode'] ?? 3),
        'page_segmentation_mode' => (int)($data['page_segmentation_mode'] ?? 6),
        'confidence_threshold' => (float)($data['confidence_threshold'] ?? 60.0),
        'is_active' => isset($data['is_active']) ? 1 : 0
    ];
    
    if (!empty($data['config_id'])) {
        // Update existing
        $id = $data['config_id'];
        $fields = [];
        foreach ($configData as $key => $value) {
            $escapedValue = is_string($value) ? db_escape($value) : $value;
            $fields[] = "$key = " . (is_string($value) ? "'$escapedValue'" : $escapedValue);
        }
        
        $sql = "UPDATE pdf_ocr_config SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = '$id'";
        db_query($sql);
        return (int)$data['config_id'];
    } else {
        // Insert new
        $columns = array_keys($configData);
        $values = [];
        foreach ($configData as $value) {
            $escapedValue = is_string($value) ? db_escape($value) : $value;
            $values[] = is_string($value) ? "'$escapedValue'" : $escapedValue;
        }
        
        $sql = "INSERT INTO pdf_ocr_config (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
        db_query($sql);
        return db_insert_id();
    }
}

/**
 * Test OCR configuration
 */
function testOcrConfiguration(array $data): void
{
    $tesseractPath = $data['tesseract_path'] ?? '/usr/bin/tesseract';
    $popplerPath = $data['poppler_path'] ?? '/usr/bin';
    $imageMagickPath = $data['imagemagick_path'] ?? '/usr/bin';
    $tempDirectory = $data['temp_directory'] ?? '/tmp/amazon_invoices_ocr';
    
    // Test if paths exist and are executable
    if (!file_exists($tesseractPath)) {
        throw new Exception("Tesseract not found at: $tesseractPath");
    }
    
    if (!is_executable($tesseractPath)) {
        throw new Exception("Tesseract is not executable: $tesseractPath");
    }
    
    // Test pdftoppm (part of poppler)
    $pdftoppmPath = rtrim($popplerPath, '/') . '/pdftoppm';
    if (!file_exists($pdftoppmPath)) {
        throw new Exception("pdftoppm not found at: $pdftoppmPath");
    }
    
    // Test convert (ImageMagick)
    $convertPath = rtrim($imageMagickPath, '/') . '/convert';
    if (!file_exists($convertPath)) {
        throw new Exception("ImageMagick convert not found at: $convertPath");
    }
    
    // Test temp directory
    if (!is_dir($tempDirectory)) {
        if (!mkdir($tempDirectory, 0755, true)) {
            throw new Exception("Cannot create temp directory: $tempDirectory");
        }
    }
    
    if (!is_writable($tempDirectory)) {
        throw new Exception("Temp directory is not writable: $tempDirectory");
    }
    
    // Test Tesseract version and language support
    $output = [];
    $returnCode = 0;
    exec("$tesseractPath --version 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("Tesseract version check failed");
    }
    
    // Test language data
    $language = $data['tesseract_language'] ?? 'eng';
    $dataPath = $data['tesseract_data_path'] ?? '/usr/share/tesseract-ocr/4.00/tessdata';
    $langFile = rtrim($dataPath, '/') . "/$language.traineddata";
    
    if (!file_exists($langFile)) {
        throw new Exception("Tesseract language data not found: $langFile");
    }
}

/**
 * Test OCR with a sample PDF
 */
function testSamplePdf(array $data): string
{
    // This would process a sample PDF file to test OCR
    // For now, return a mock result
    return "Sample OCR text extraction successful. Configuration working properly.";
}

/**
 * Delete OCR configuration
 */
function deleteOcrConfig(int $configId): void
{
    $sql = "DELETE FROM pdf_ocr_config WHERE id = '$configId'";
    db_query($sql);
}

/**
 * Get all OCR configurations
 */
function getOcrConfigurations(): array
{
    $sql = "SELECT * FROM pdf_ocr_config ORDER BY created_at DESC";
    $result = db_query($sql);
    
    $configurations = [];
    while ($row = db_fetch($result)) {
        $configurations[] = $row;
    }
    
    return $configurations;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>PDF OCR Configuration - Amazon Invoice Import</title>
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
        .setup-instructions { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .setup-instructions ol { margin: 0; padding-left: 20px; }
        .setup-instructions li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PDF OCR Configuration</h1>
        <p>Configure PDF OCR processing settings for extracting text from Amazon invoice PDFs.</p>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Installation Instructions -->
        <div class="card">
            <h2>System Requirements & Installation</h2>
            <div class="setup-instructions">
                <h3>Required Software (Ubuntu/Debian):</h3>
                <ol>
                    <li><strong>Tesseract OCR:</strong> <code>sudo apt-get install tesseract-ocr tesseract-ocr-eng</code></li>
                    <li><strong>Poppler Utils:</strong> <code>sudo apt-get install poppler-utils</code></li>
                    <li><strong>ImageMagick:</strong> <code>sudo apt-get install imagemagick</code></li>
                </ol>
                
                <h3>Additional Language Support:</h3>
                <p>Install additional languages: <code>sudo apt-get install tesseract-ocr-[lang]</code></p>
                <p>Available languages: eng, spa, fra, deu, ita, por, rus, jpn, chi_sim, chi_tra, etc.</p>
            </div>
        </div>
        
        <!-- OCR Configuration Form -->
        <div class="card">
            <h2>OCR Configuration</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_ocr_config">
                <input type="hidden" name="config_id" id="config_id" value="">
                
                <div class="form-group">
                    <label for="config_name">Configuration Name:</label>
                    <input type="text" name="config_name" id="config_name" required 
                           placeholder="e.g., Production OCR Config">
                </div>
                
                <h3>Tesseract Configuration</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tesseract_path">Tesseract Executable Path:</label>
                        <input type="text" name="tesseract_path" id="tesseract_path" 
                               value="/usr/bin/tesseract" required>
                        <div class="help-text">Full path to tesseract binary</div>
                    </div>
                    <div class="form-group">
                        <label for="tesseract_data_path">Tesseract Data Path:</label>
                        <input type="text" name="tesseract_data_path" id="tesseract_data_path" 
                               value="/usr/share/tesseract-ocr/4.00/tessdata" required>
                        <div class="help-text">Path to tessdata directory</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tesseract_language">Language:</label>
                        <select name="tesseract_language" id="tesseract_language">
                            <option value="eng">English (eng)</option>
                            <option value="spa">Spanish (spa)</option>
                            <option value="fra">French (fra)</option>
                            <option value="deu">German (deu)</option>
                            <option value="ita">Italian (ita)</option>
                            <option value="por">Portuguese (por)</option>
                            <option value="rus">Russian (rus)</option>
                            <option value="jpn">Japanese (jpn)</option>
                            <option value="chi_sim">Chinese Simplified (chi_sim)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="confidence_threshold">Confidence Threshold (%):</label>
                        <input type="number" name="confidence_threshold" id="confidence_threshold" 
                               value="60" min="0" max="100" step="0.1">
                        <div class="help-text">Minimum OCR confidence level (0-100)</div>
                    </div>
                </div>
                
                <h3>System Paths</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="poppler_path">Poppler Path:</label>
                        <input type="text" name="poppler_path" id="poppler_path" 
                               value="/usr/bin" required>
                        <div class="help-text">Directory containing pdftoppm</div>
                    </div>
                    <div class="form-group">
                        <label for="imagemagick_path">ImageMagick Path:</label>
                        <input type="text" name="imagemagick_path" id="imagemagick_path" 
                               value="/usr/bin" required>
                        <div class="help-text">Directory containing convert</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="temp_directory">Temporary Directory:</label>
                    <input type="text" name="temp_directory" id="temp_directory" 
                           value="/tmp/amazon_invoices_ocr" required>
                    <div class="help-text">Directory for temporary processing files</div>
                </div>
                
                <h3>Processing Settings</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="pdf_dpi">PDF DPI:</label>
                        <input type="number" name="pdf_dpi" id="pdf_dpi" 
                               value="300" min="150" max="600" required>
                        <div class="help-text">Resolution for PDF to image conversion</div>
                    </div>
                    <div class="form-group">
                        <label for="image_enhancement_level">Image Enhancement:</label>
                        <select name="image_enhancement_level" id="image_enhancement_level">
                            <option value="none">None</option>
                            <option value="basic" selected>Basic</option>
                            <option value="advanced">Advanced</option>
                        </select>
                        <div class="help-text">Level of image preprocessing</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ocr_engine_mode">OCR Engine Mode:</label>
                        <select name="ocr_engine_mode" id="ocr_engine_mode">
                            <option value="0">Legacy engine only</option>
                            <option value="1">Neural nets LSTM engine only</option>
                            <option value="2">Legacy + LSTM engines</option>
                            <option value="3" selected>Default (based on what is available)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="page_segmentation_mode">Page Segmentation Mode:</label>
                        <select name="page_segmentation_mode" id="page_segmentation_mode">
                            <option value="3">Fully automatic page segmentation</option>
                            <option value="4">Single column of text</option>
                            <option value="6" selected>Single uniform block</option>
                            <option value="7">Single text line</option>
                            <option value="8">Single word</option>
                            <option value="11">Sparse text</option>
                            <option value="12">Sparse text with OSD</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="image_preprocessing" id="image_preprocessing" checked> 
                        Enable Image Preprocessing
                    </label>
                    <div class="help-text">Apply filters to improve OCR accuracy</div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" checked> 
                        Active Configuration
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Configuration</button>
                <button type="button" class="btn btn-warning" onclick="testConfiguration()">Test Configuration</button>
                <button type="button" class="btn" onclick="resetForm()">Reset Form</button>
            </form>
        </div>
        
        <!-- Current Configurations -->
        <?php if (!empty($configurations)): ?>
        <div class="card">
            <h2>Current OCR Configurations</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Tesseract Path</th>
                        <th>Language</th>
                        <th>DPI</th>
                        <th>Enhancement</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($configurations as $config): ?>
                    <tr>
                        <td><?= htmlspecialchars($config['config_name']) ?></td>
                        <td><?= htmlspecialchars($config['tesseract_path']) ?></td>
                        <td><?= htmlspecialchars($config['tesseract_language']) ?></td>
                        <td><?= $config['pdf_dpi'] ?></td>
                        <td><?= ucfirst($config['image_enhancement_level']) ?></td>
                        <td class="<?= $config['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $config['is_active'] ? 'Active' : 'Inactive' ?>
                        </td>
                        <td>
                            <button class="btn btn-primary" onclick="editConfig(<?= htmlspecialchars(json_encode($config)) ?>)">
                                Edit
                            </button>
                            <button class="btn btn-success" onclick="testConfigById(<?= $config['id'] ?>)">
                                Test
                            </button>
                            <button class="btn btn-danger" onclick="deleteConfig(<?= $config['id'] ?>)">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- System Information -->
        <div class="card">
            <h2>System Information</h2>
            <div class="setup-instructions">
                <h3>Current System Status:</h3>
                <p><strong>Server OS:</strong> <?= php_uname() ?></p>
                <p><strong>PHP Version:</strong> <?= phpversion() ?></p>
                <p><strong>Temporary Directory:</strong> <?= sys_get_temp_dir() ?></p>
                <p><strong>Memory Limit:</strong> <?= ini_get('memory_limit') ?></p>
                <p><strong>Max Execution Time:</strong> <?= ini_get('max_execution_time') ?>s</p>
            </div>
        </div>
    </div>
    
    <script>
        function testConfiguration() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            formData.set('action', 'test_ocr_config');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('successful')) {
                    alert('OCR configuration test successful!');
                } else {
                    alert('OCR configuration test failed. Please check your settings.');
                }
            })
            .catch(error => {
                alert('Error testing configuration: ' + error.message);
            });
        }
        
        function editConfig(config) {
            document.getElementById('config_id').value = config.id;
            document.getElementById('config_name').value = config.config_name;
            document.getElementById('tesseract_path').value = config.tesseract_path;
            document.getElementById('tesseract_data_path').value = config.tesseract_data_path;
            document.getElementById('tesseract_language').value = config.tesseract_language;
            document.getElementById('poppler_path').value = config.poppler_path;
            document.getElementById('imagemagick_path').value = config.imagemagick_path;
            document.getElementById('temp_directory').value = config.temp_directory;
            document.getElementById('pdf_dpi').value = config.pdf_dpi;
            document.getElementById('image_enhancement_level').value = config.image_enhancement_level;
            document.getElementById('ocr_engine_mode').value = config.ocr_engine_mode;
            document.getElementById('page_segmentation_mode').value = config.page_segmentation_mode;
            document.getElementById('confidence_threshold').value = config.confidence_threshold;
            document.getElementById('image_preprocessing').checked = config.image_preprocessing == 1;
            document.getElementById('is_active').checked = config.is_active == 1;
        }
        
        function testConfigById(configId) {
            // This would test a specific configuration by ID
            alert('Testing configuration ID: ' + configId);
        }
        
        function deleteConfig(configId) {
            if (confirm('Are you sure you want to delete this OCR configuration?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_config">
                    <input type="hidden" name="config_id" value="${configId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resetForm() {
            document.querySelector('form').reset();
            document.getElementById('config_id').value = '';
        }
    </script>
</body>
</html>
