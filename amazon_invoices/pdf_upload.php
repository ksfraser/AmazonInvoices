<?php
/**
 * PDF Upload and Import Management
 * 
 * Interface for uploading PDF files and managing directory imports
 * for Amazon invoice PDF processing
 * 
 * @package AmazonInvoices
 * @author  Assistant
 * @since   1.0.0
 */

require_once dirname(__DIR__) . '/src/Services/PdfOcrProcessor.php';

use AmazonInvoices\Services\PdfOcrProcessor;
use AmazonInvoices\Repositories\FrontAccountingDatabaseRepository;

// Ensure we're in FrontAccounting context
if (!defined('TB_PREF')) {
    require_once 'includes/db/FA_mock_functions.php';
}

// Initialize services
$dbRepository = new FrontAccountingDatabaseRepository();
$pdfProcessor = new PdfOcrProcessor($dbRepository);

// Define upload and storage directories
$uploadDir = dirname(__DIR__) . '/uploads/pdfs';
$storageDir = dirname(__DIR__) . '/storage/pdfs';
$importDir = dirname(__DIR__) . '/import/pdfs';

// Ensure directories exist
foreach ([$uploadDir, $storageDir, $importDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Handle form submissions
$message = '';
$messageType = 'info';
$processingResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'upload_pdfs':
                    $processingResults = handlePdfUpload();
                    $message = "PDF upload processing completed. " . count($processingResults) . " files processed.";
                    $messageType = 'success';
                    break;
                    
                case 'import_directory':
                    $processingResults = handleDirectoryImport($_POST);
                    $message = "Directory import completed. " . count($processingResults) . " files processed.";
                    $messageType = 'success';
                    break;
                    
                case 'reprocess_file':
                    $result = reprocessFile($_POST['file_id']);
                    $message = "File reprocessed: " . $result['status'];
                    $messageType = $result['status'] === 'success' ? 'success' : 'error';
                    break;
                    
                case 'delete_file':
                    deleteProcessedFile($_POST['file_id']);
                    $message = "File deleted successfully.";
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get processing history
$processingHistory = getPdfProcessingHistory();
$importDirectories = getAvailableImportDirectories();

/**
 * Handle PDF file upload
 */
function handlePdfUpload(): array
{
    global $pdfProcessor, $storageDir;
    
    if (!isset($_FILES['pdf_files']) || empty($_FILES['pdf_files']['tmp_name'])) {
        throw new Exception("No files uploaded");
    }
    
    $uploadedFiles = [];
    $fileCount = is_array($_FILES['pdf_files']['tmp_name']) ? 
                 count($_FILES['pdf_files']['tmp_name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileData = [
            'name' => is_array($_FILES['pdf_files']['name']) ? 
                     $_FILES['pdf_files']['name'][$i] : $_FILES['pdf_files']['name'],
            'type' => is_array($_FILES['pdf_files']['type']) ? 
                     $_FILES['pdf_files']['type'][$i] : $_FILES['pdf_files']['type'],
            'tmp_name' => is_array($_FILES['pdf_files']['tmp_name']) ? 
                         $_FILES['pdf_files']['tmp_name'][$i] : $_FILES['pdf_files']['tmp_name'],
            'size' => is_array($_FILES['pdf_files']['size']) ? 
                     $_FILES['pdf_files']['size'][$i] : $_FILES['pdf_files']['size']
        ];
        
        if ($fileData['tmp_name'] && is_uploaded_file($fileData['tmp_name'])) {
            $uploadedFiles[] = $fileData;
        }
    }
    
    return $pdfProcessor->processUploadedFiles($uploadedFiles, $storageDir);
}

/**
 * Handle directory import
 */
function handleDirectoryImport(array $data): array
{
    global $pdfProcessor, $storageDir;
    
    $sourceDir = $data['import_directory'] ?? '';
    if (!$sourceDir || !is_dir($sourceDir)) {
        throw new Exception("Invalid import directory");
    }
    
    $options = [
        'extensions' => ['pdf'],
        'recursive' => isset($data['recursive']),
        'move_files' => isset($data['move_processed'])
    ];
    
    return $pdfProcessor->processDirectory($sourceDir, $storageDir, $options);
}

/**
 * Reprocess a file
 */
function reprocessFile(int $fileId): array
{
    global $pdfProcessor, $storageDir;
    
    // Get file info from database
    $sql = "SELECT pdf_file_path FROM amazon_pdf_logs WHERE id = '$fileId'";
    $result = db_query($sql);
    $row = db_fetch($result);
    
    if (!$row) {
        throw new Exception("File not found");
    }
    
    $filePath = $row['pdf_file_path'];
    if (!file_exists($filePath)) {
        throw new Exception("File no longer exists: $filePath");
    }
    
    // Clear previous processing status
    $sql = "UPDATE amazon_pdf_logs SET processing_status = 'pending' WHERE id = '$fileId'";
    db_query($sql);
    
    return $pdfProcessor->processPdfFile($filePath, $storageDir);
}

/**
 * Delete processed file
 */
function deleteProcessedFile(int $fileId): void
{
    // Get file info
    $sql = "SELECT pdf_file_path FROM amazon_pdf_logs WHERE id = '$fileId'";
    $result = db_query($sql);
    $row = db_fetch($result);
    
    if ($row && file_exists($row['pdf_file_path'])) {
        unlink($row['pdf_file_path']);
    }
    
    // Delete database records
    $sql = "DELETE FROM amazon_pdf_logs WHERE id = '$fileId'";
    db_query($sql);
}

/**
 * Get PDF processing history
 */
function getPdfProcessingHistory(int $limit = 50): array
{
    $sql = "SELECT p.*, i.invoice_number, i.invoice_total, i.status as invoice_status
            FROM amazon_pdf_logs p
            LEFT JOIN amazon_invoices_staging i ON i.id = p.invoice_id
            ORDER BY p.processed_date DESC
            LIMIT $limit";
    $result = db_query($sql);
    
    $history = [];
    while ($row = db_fetch($result)) {
        $history[] = $row;
    }
    
    return $history;
}

/**
 * Get available import directories
 */
function getAvailableImportDirectories(): array
{
    global $importDir;
    
    $directories = [];
    
    // Add configured import directory
    if (is_dir($importDir)) {
        $directories[] = [
            'path' => $importDir,
            'name' => 'Default Import Directory',
            'file_count' => count(glob($importDir . '/*.pdf'))
        ];
    }
    
    // Add any other configured directories from settings
    $sql = "SELECT setting_value FROM amazon_invoice_settings 
            WHERE setting_key = 'pdf_import_directories'";
    $result = db_query($sql);
    $row = db_fetch($result);
    
    if ($row) {
        $configuredDirs = json_decode($row['setting_value'], true);
        if (is_array($configuredDirs)) {
            foreach ($configuredDirs as $dir) {
                if (is_dir($dir)) {
                    $directories[] = [
                        'path' => $dir,
                        'name' => basename($dir),
                        'file_count' => count(glob($dir . '/*.pdf'))
                    ];
                }
            }
        }
    }
    
    return $directories;
}

/**
 * Format file size
 */
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes > 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>PDF Upload & Import - Amazon Invoice Import</title>
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
        .status-completed { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-processing { color: #ffc107; font-weight: bold; }
        .status-duplicate { color: #6c757d; font-weight: bold; }
        .upload-zone { 
            border: 2px dashed #ddd; 
            border-radius: 5px; 
            padding: 40px; 
            text-align: center; 
            background: #fafafa;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .upload-zone:hover { border-color: #007cba; }
        .upload-zone.dragover { border-color: #28a745; background: #f0fff0; }
        .file-list { margin-top: 10px; }
        .file-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 5px 10px; 
            background: #e9ecef; 
            margin-bottom: 5px; 
            border-radius: 3px; 
        }
        .processing-results { margin-top: 20px; }
        .result-item { 
            padding: 10px; 
            margin-bottom: 10px; 
            border-radius: 3px; 
            border-left: 4px solid #ddd; 
        }
        .result-success { border-left-color: #28a745; background: #d4edda; }
        .result-error { border-left-color: #dc3545; background: #f8d7da; }
        .result-duplicate { border-left-color: #6c757d; background: #e2e3e5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PDF Upload & Import</h1>
        <p>Upload PDF files or import from server directories for Amazon invoice processing.</p>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- PDF Upload Section -->
        <div class="card">
            <h2>Upload PDF Files</h2>
            <form method="post" enctype="multipart/form-data" id="upload-form">
                <input type="hidden" name="action" value="upload_pdfs">
                
                <div class="upload-zone" onclick="document.getElementById('pdf-files').click()">
                    <p>Click here or drag & drop PDF files to upload</p>
                    <p><small>Supported formats: PDF | Max size: 50MB per file</small></p>
                    <input type="file" name="pdf_files[]" id="pdf-files" multiple accept=".pdf" style="display: none;">
                </div>
                
                <div class="file-list" id="file-list" style="display: none;">
                    <h4>Selected Files:</h4>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn btn-primary" id="upload-btn" disabled>
                        Upload & Process PDFs
                    </button>
                    <button type="button" class="btn" onclick="clearFiles()">Clear Files</button>
                </div>
            </form>
        </div>
        
        <!-- Directory Import Section -->
        <div class="card">
            <h2>Import from Server Directory</h2>
            <form method="post">
                <input type="hidden" name="action" value="import_directory">
                
                <div class="form-group">
                    <label for="import_directory">Import Directory:</label>
                    <select name="import_directory" id="import_directory" required>
                        <option value="">Select directory...</option>
                        <?php foreach ($importDirectories as $dir): ?>
                            <option value="<?= htmlspecialchars($dir['path']) ?>">
                                <?= htmlspecialchars($dir['name']) ?> 
                                (<?= $dir['file_count'] ?> PDF files)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="recursive"> 
                        Include subdirectories
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="move_processed" checked> 
                        Move processed files to archive
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Import Directory</button>
            </form>
        </div>
        
        <!-- Processing Results -->
        <?php if (!empty($processingResults)): ?>
        <div class="card processing-results">
            <h2>Processing Results</h2>
            <?php foreach ($processingResults as $result): ?>
                <div class="result-item result-<?= $result['status'] ?>">
                    <strong><?= htmlspecialchars($result['filename'] ?? $result['file_path'] ?? 'Unknown') ?></strong>
                    <br>
                    Status: <?= ucfirst($result['status']) ?>
                    <?php if (isset($result['error'])): ?>
                        <br>Error: <?= htmlspecialchars($result['error']) ?>
                    <?php endif; ?>
                    <?php if (isset($result['processing_time'])): ?>
                        <br>Processing time: <?= round($result['processing_time'], 2) ?>s
                    <?php endif; ?>
                    <?php if (isset($result['pages_processed'])): ?>
                        <br>Pages processed: <?= $result['pages_processed'] ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Processing History -->
        <?php if (!empty($processingHistory)): ?>
        <div class="card">
            <h2>Processing History</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Processed</th>
                        <th>Status</th>
                        <th>Processing Time</th>
                        <th>Invoice</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processingHistory as $record): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars(basename($record['pdf_file_path'])) ?>
                            <br><small><?= htmlspecialchars($record['pdf_file_hash']) ?></small>
                        </td>
                        <td><?= formatFileSize((int)$record['pdf_file_size']) ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($record['processed_date'])) ?></td>
                        <td class="status-<?= $record['processing_status'] ?>">
                            <?= ucfirst($record['processing_status']) ?>
                            <?php if ($record['error_message']): ?>
                                <br><small><?= htmlspecialchars(substr($record['error_message'], 0, 100)) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $record['ocr_processing_time'] ? round($record['ocr_processing_time'], 2) . 's' : '-' ?>
                        </td>
                        <td>
                            <?php if ($record['invoice_number']): ?>
                                <a href="staging_review.php?invoice_id=<?= $record['invoice_id'] ?>">
                                    <?= htmlspecialchars($record['invoice_number']) ?>
                                </a>
                                <br>$<?= number_format($record['invoice_total'], 2) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['processing_status'] === 'error'): ?>
                                <button class="btn btn-warning btn-sm" onclick="reprocessFile(<?= $record['id'] ?>)">
                                    Reprocess
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteFile(<?= $record['id'] ?>)">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Directory Management -->
        <div class="card">
            <h2>Import Directory Management</h2>
            <p>Current import directories:</p>
            <ul>
                <li><strong>Default:</strong> <?= htmlspecialchars($importDir) ?></li>
                <li><strong>Upload Storage:</strong> <?= htmlspecialchars($storageDir) ?></li>
            </ul>
            
            <p><small>
                To add additional import directories, update the 'pdf_import_directories' setting 
                in the Amazon invoice settings with a JSON array of directory paths.
            </small></p>
        </div>
    </div>
    
    <script>
        // File upload handling
        const fileInput = document.getElementById('pdf-files');
        const fileList = document.getElementById('file-list');
        const uploadBtn = document.getElementById('upload-btn');
        const uploadZone = document.querySelector('.upload-zone');
        
        fileInput.addEventListener('change', handleFileSelect);
        
        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        });
        
        function handleFileSelect() {
            const files = fileInput.files;
            if (files.length === 0) {
                fileList.style.display = 'none';
                uploadBtn.disabled = true;
                return;
            }
            
            fileList.style.display = 'block';
            fileList.innerHTML = '<h4>Selected Files:</h4>';
            
            let totalSize = 0;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                totalSize += file.size;
                
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <span>${file.name} (${formatFileSize(file.size)})</span>
                    <button type="button" onclick="removeFile(${i})" class="btn btn-danger btn-sm">Remove</button>
                `;
                fileList.appendChild(fileItem);
            }
            
            uploadBtn.disabled = false;
            uploadBtn.textContent = `Upload & Process ${files.length} PDF${files.length > 1 ? 's' : ''} (${formatFileSize(totalSize)})`;
        }
        
        function clearFiles() {
            fileInput.value = '';
            fileList.style.display = 'none';
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Upload & Process PDFs';
        }
        
        function removeFile(index) {
            // This is tricky with FileList, so we'll just clear and ask user to reselect
            if (confirm('Remove this file? (You will need to reselect all files)')) {
                clearFiles();
            }
        }
        
        function formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            while (bytes > 1024 && i < units.length - 1) {
                bytes /= 1024;
                i++;
            }
            return Math.round(bytes * 100) / 100 + ' ' + units[i];
        }
        
        function reprocessFile(fileId) {
            if (confirm('Reprocess this PDF file?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reprocess_file">
                    <input type="hidden" name="file_id" value="${fileId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteFile(fileId) {
            if (confirm('Delete this PDF file and all related data? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_file">
                    <input type="hidden" name="file_id" value="${fileId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
