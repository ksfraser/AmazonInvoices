<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use PdfOcrProcessor\PdfOcrProcessor;
use PdfOcrProcessor\Interfaces\FileSystemInterface;
use PdfOcrProcessor\Interfaces\OcrEngineInterface;
use PdfOcrProcessor\Interfaces\InvoiceParserInterface as PdfInvoiceParserInterface;
use PdfOcrProcessor\Interfaces\StorageInterface as PdfStorageInterface;
use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

/**
 * PDF OCR Processor Wrapper for FrontAccounting Integration
 * 
 * Wraps the standalone PDF OCR processor library with FrontAccounting-specific
 * implementations and business logic
 */
class PdfOcrProcessorWrapper implements FileSystemInterface, OcrEngineInterface, PdfInvoiceParserInterface, PdfStorageInterface
{
    /**
     * @var DatabaseRepositoryInterface
     */
    private $database;

    /**
     * @var DuplicateDetectionService
     */
    private $duplicateDetector;

    /**
     * @var PdfOcrProcessor
     */
    private $processor;

    /**
     * @var string
     */
    private $uploadPath;

    /**
     * @var string
     */
    private $tempPath;
    
    public function __construct(
        DatabaseRepositoryInterface $database,
        DuplicateDetectionService $duplicateDetector,
        string $uploadPath = null,
        string $tempPath = null
    ) {
        $this->database = $database;
        $this->duplicateDetector = $duplicateDetector;
        $this->uploadPath = $uploadPath ?? dirname(__DIR__, 2) . '/amazon_invoices/uploads/';
        $this->tempPath = $tempPath ?? sys_get_temp_dir() . '/amazon_invoices/';
        
        // Ensure directories exist
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
        
        // Create the processor with this wrapper as the implementation
        $this->processor = new PdfOcrProcessor($this, $this, $this, $this);
    }
    
    /**
     * Process a single PDF file
     */
    public function processPdfFile(string $filePath, array $options = []): array
    {
        return $this->processor->processPdf($filePath, $options);
    }
    
    /**
     * Process multiple PDF files from a directory
     */
    public function processDirectory(string $directoryPath, array $options = []): array
    {
        return $this->processor->processDirectory($directoryPath, $options);
    }
    
    /**
     * Process uploaded files
     */
    public function processUploadedFiles(array $uploadedFiles, array $options = []): array
    {
        $results = [];
        
        foreach ($uploadedFiles as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $results[] = [
                    'file' => $file['name'],
                    'success' => false,
                    'error' => $this->getUploadErrorMessage($file['error'])
                ];
                continue;
            }
            
            // Validate file type
            if (!$this->isValidPdfFile($file['tmp_name'], $file['type'])) {
                $results[] = [
                    'file' => $file['name'],
                    'success' => false,
                    'error' => 'Invalid PDF file'
                ];
                continue;
            }
            
            // Move to upload directory with unique name
            $fileName = $this->generateUniqueFileName($file['name']);
            $targetPath = $this->uploadPath . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $results[] = [
                    'file' => $file['name'],
                    'success' => false,
                    'error' => 'Failed to save uploaded file'
                ];
                continue;
            }
            
            try {
                // Process the PDF
                $processResult = $this->processPdfFile($targetPath, $options);
                $results[] = array_merge($processResult, [
                    'original_filename' => $file['name'],
                    'stored_filename' => $fileName
                ]);
            } catch (\Exception $e) {
                $results[] = [
                    'file' => $file['name'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    // ================================
    // FileSystemInterface Implementation
    // ================================
    
    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }
    
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }
    
    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }
    
    public function readFile(string $path): string
    {
        if (!$this->fileExists($path) || !$this->isReadable($path)) {
            throw new \Exception("File not found or not readable: $path");
        }
        
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \Exception("Failed to read file: $path");
        }
        
        return $content;
    }
    
    public function writeFile(string $path, string $content): bool
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        return file_put_contents($path, $content) !== false;
    }
    
    public function deleteFile(string $path): bool
    {
        if (!$this->fileExists($path)) {
            return true; // Already deleted
        }
        
        return unlink($path);
    }
    
    public function getFileSize(string $path): int
    {
        if (!$this->fileExists($path)) {
            throw new \Exception("File not found: $path");
        }
        
        $size = filesize($path);
        if ($size === false) {
            throw new \Exception("Could not get file size: $path");
        }
        
        return $size;
    }
    
    public function getFileModifiedTime(string $path): int
    {
        if (!$this->fileExists($path)) {
            throw new \Exception("File not found: $path");
        }
        
        $time = filemtime($path);
        if ($time === false) {
            throw new \Exception("Could not get file modification time: $path");
        }
        
        return $time;
    }
    
    public function copyFile(string $source, string $destination): bool
    {
        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        return copy($source, $destination);
    }
    
    public function moveFile(string $source, string $destination): bool
    {
        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        return rename($source, $destination);
    }
    
    public function createTempFile(string $prefix = 'amazon_pdf_'): string
    {
        return tempnam($this->tempPath, $prefix);
    }
    
    public function listDirectory(string $path, string $extension = null): array
    {
        if (!is_dir($path)) {
            throw new \Exception("Directory not found: $path");
        }
        
        $files = [];
        $iterator = new \DirectoryIterator($path);
        
        foreach ($iterator as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }
            
            if ($extension && strtolower($file->getExtension()) !== strtolower($extension)) {
                continue;
            }
            
            $files[] = $file->getPathname();
        }
        
        return $files;
    }
    
    // ==============================
    // OcrEngineInterface Implementation
    // ==============================
    
    public function extractTextFromPdf(string $pdfPath): string
    {
        // First try to extract text directly from PDF
        $directText = $this->extractTextDirectly($pdfPath);
        if (!empty(trim($directText))) {
            return $directText;
        }
        
        // If direct extraction fails, convert to images and use OCR
        return $this->extractTextWithOcr($pdfPath);
    }
    
    public function extractTextFromImage(string $imagePath): string
    {
        // Use Tesseract to extract text from image
        $escapedPath = escapeshellarg($imagePath);
        $tempFile = $this->createTempFile('tesseract_');
        $escapedTempFile = escapeshellarg($tempFile);
        
        // Run Tesseract
        $command = "tesseract $escapedPath $escapedTempFile -l eng 2>&1";
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \Exception("Tesseract OCR failed: " . implode("\n", $output));
        }
        
        // Read the output file
        $textFile = $tempFile . '.txt';
        if (!file_exists($textFile)) {
            throw new \Exception("Tesseract output file not found");
        }
        
        $text = file_get_contents($textFile);
        
        // Clean up temp files
        unlink($tempFile);
        unlink($textFile);
        
        return $text ?: '';
    }
    
    public function convertPdfToImages(string $pdfPath): array
    {
        $tempDir = $this->tempPath . 'pdf_' . uniqid() . '/';
        mkdir($tempDir, 0755);
        
        $escapedPdfPath = escapeshellarg($pdfPath);
        $escapedTempDir = escapeshellarg($tempDir . 'page');
        
        // Use pdftoppm to convert PDF pages to images
        $command = "pdftoppm -png $escapedPdfPath $escapedTempDir 2>&1";
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \Exception("PDF to image conversion failed: " . implode("\n", $output));
        }
        
        // Get list of generated images
        $images = $this->listDirectory($tempDir, 'png');
        sort($images); // Ensure proper page order
        
        return $images;
    }
    
    public function preprocessImage(string $imagePath): string
    {
        $processedPath = $this->createTempFile('processed_') . '.png';
        $escapedInput = escapeshellarg($imagePath);
        $escapedOutput = escapeshellarg($processedPath);
        
        // Use ImageMagick to improve image quality for OCR
        $command = "convert $escapedInput -density 300 -background white -alpha remove " .
                   "-enhance -normalize -sharpen 0x1 $escapedOutput 2>&1";
        
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            // If ImageMagick fails, return original path
            return $imagePath;
        }
        
        return $processedPath;
    }
    
    // ======================================
    // InvoiceParserInterface Implementation
    // ======================================
    
    public function parseInvoiceText(string $text): ?array
    {
        $invoiceData = [];
        
        // Extract order number
        if (preg_match('/Order\s*#?\s*([A-Z0-9-]+)/i', $text, $matches)) {
            $invoiceData['order_number'] = $matches[1];
        }
        
        // Extract invoice number
        if (preg_match('/Invoice\s*#?\s*([A-Z0-9-]+)/i', $text, $matches)) {
            $invoiceData['invoice_number'] = $matches[1];
        }
        
        // Extract total amount
        if (preg_match('/Total[:\s]*\$?([0-9,]+\.?\d*)/i', $text, $matches)) {
            $invoiceData['invoice_total'] = (float)str_replace(',', '', $matches[1]);
        } elseif (preg_match('/\$([0-9,]+\.?\d*)\s*total/i', $text, $matches)) {
            $invoiceData['invoice_total'] = (float)str_replace(',', '', $matches[1]);
        }
        
        // Extract tax amount
        if (preg_match('/Tax[:\s]*\$?([0-9,]+\.?\d*)/i', $text, $matches)) {
            $invoiceData['tax_amount'] = (float)str_replace(',', '', $matches[1]);
        }
        
        // Extract shipping amount
        if (preg_match('/Shipping[:\s]*\$?([0-9,]+\.?\d*)/i', $text, $matches)) {
            $invoiceData['shipping_amount'] = (float)str_replace(',', '', $matches[1]);
        }
        
        // Extract date
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $text, $matches)) {
            $invoiceData['invoice_date'] = date('Y-m-d', strtotime($matches[1]));
        } elseif (preg_match('/(\w+\s+\d{1,2},?\s*\d{4})/i', $text, $matches)) {
            $invoiceData['invoice_date'] = date('Y-m-d', strtotime($matches[1]));
        }
        
        // Extract billing address
        $billingAddress = $this->extractBillingAddress($text);
        if ($billingAddress) {
            $invoiceData['billing_address'] = $billingAddress;
        }
        
        // Extract items
        $items = $this->extractItems($text);
        if (!empty($items)) {
            $invoiceData['items'] = $items;
        }
        
        // Return null if we couldn't extract meaningful data
        if (empty($invoiceData['order_number']) && empty($invoiceData['invoice_number']) && empty($invoiceData['invoice_total'])) {
            return null;
        }
        
        return $invoiceData;
    }
    
    public function extractItems(string $text): array
    {
        $items = [];
        
        // Look for common item patterns
        $patterns = [
            // Pattern: Quantity x Product Name $Price
            '/(\d+)\s*x\s*(.+?)\s*\$([0-9,]+\.?\d*)/',
            // Pattern: Product Name (Qty: N) $Price
            '/(.+?)\s*\(Qty:\s*(\d+)\)\s*\$([0-9,]+\.?\d*)/',
            // Pattern: Product Name - $Price (x N)
            '/(.+?)\s*-\s*\$([0-9,]+\.?\d*)\s*\(x\s*(\d+)\)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $item = [];
                    
                    if (count($match) === 4) {
                        if (is_numeric($match[1])) {
                            // Pattern 1: qty, name, price
                            $item['quantity'] = (int)$match[1];
                            $item['product_name'] = trim($match[2]);
                            $item['unit_price'] = (float)str_replace(',', '', $match[3]);
                        } else {
                            // Pattern 2: name, qty, price
                            $item['product_name'] = trim($match[1]);
                            $item['quantity'] = (int)$match[2];
                            $item['unit_price'] = (float)str_replace(',', '', $match[3]);
                        }
                    }
                    
                    if (!empty($item)) {
                        $item['total_price'] = $item['quantity'] * $item['unit_price'];
                        
                        // Try to extract ASIN if present
                        if (preg_match('/ASIN[:\s]*([A-Z0-9]{10})/i', $match[0], $asinMatch)) {
                            $item['asin'] = $asinMatch[1];
                        }
                        
                        $items[] = $item;
                    }
                }
                
                if (!empty($items)) {
                    break; // Use first successful pattern
                }
            }
        }
        
        return $items;
    }
    
    public function extractBillingAddress(string $text): ?string
    {
        // Look for billing address patterns
        $patterns = [
            '/Billing\s+Address[:\s]*(.*?)(?=\n\s*\n|\n[A-Z]|\n\d|\Z)/is',
            '/Bill\s+to[:\s]*(.*?)(?=\n\s*\n|\n[A-Z]|\n\d|\Z)/is'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $address = trim($matches[1]);
                // Clean up the address
                $address = preg_replace('/\s+/', ' ', $address);
                $address = trim($address, " \t\n\r\0\x0B,");
                
                if (strlen($address) > 10) { // Reasonable minimum length
                    return $address;
                }
            }
        }
        
        return null;
    }
    
    // ==============================
    // StorageInterface Implementation
    // ==============================
    
    public function storePdfLog(array $pdfData): int
    {
        $fields = [];
        $values = [];
        
        foreach ($pdfData as $key => $value) {
            $fields[] = $key;
            $values[] = is_string($value) ? "'" . $this->database->escape($value) . "'" : $value;
        }
        
        $query = "INSERT INTO " . TB_PREF . "amazon_pdf_logs (" . 
                 implode(', ', $fields) . ") VALUES (" . 
                 implode(', ', $values) . ")";
        
        $this->database->query($query);
        return $this->database->getLastInsertId();
    }
    
    public function isPdfProcessed(string $filePath): bool
    {
        $fileHash = hash_file('sha256', $filePath);
        $escapedHash = $this->database->escape($fileHash);
        
        $query = "SELECT id FROM " . TB_PREF . "amazon_pdf_logs 
                  WHERE file_hash = '$escapedHash'";
        
        $result = $this->database->query($query);
        return $this->database->fetch($result) !== null;
    }
    
    public function storeInvoice(array $invoiceData): int
    {
        // Map invoice data to staging table format
        $stagingData = [
            'invoice_number' => $invoiceData['invoice_number'] ?? '',
            'order_number' => $invoiceData['order_number'] ?? '',
            'invoice_date' => $invoiceData['invoice_date'] ?? date('Y-m-d'),
            'invoice_total' => $invoiceData['invoice_total'] ?? 0,
            'tax_amount' => $invoiceData['tax_amount'] ?? 0,
            'shipping_amount' => $invoiceData['shipping_amount'] ?? 0,
            'currency' => $invoiceData['currency'] ?? 'USD',
            'raw_data' => json_encode($invoiceData),
            'status' => 'pending',
            'source_type' => 'pdf',
            'source_id' => $invoiceData['source_file'] ?? ''
        ];
        
        $fields = [];
        $values = [];
        
        foreach ($stagingData as $key => $value) {
            $fields[] = $key;
            $values[] = is_string($value) ? "'" . $this->database->escape($value) . "'" : $value;
        }
        
        $query = "INSERT INTO " . TB_PREF . "amazon_invoices_staging (" . 
                 implode(', ', $fields) . ") VALUES (" . 
                 implode(', ', $values) . ")";
        
        $this->database->query($query);
        $invoiceId = $this->database->getLastInsertId();
        
        // Store invoice items if present
        if (!empty($invoiceData['items']) && is_array($invoiceData['items'])) {
            $this->storeInvoiceItems($invoiceId, $invoiceData['items']);
        }
        
        return $invoiceId;
    }
    
    public function findDuplicateInvoice(array $invoiceData): ?array
    {
        return $this->duplicateDetector->findDuplicateInvoice($invoiceData);
    }
    
    // =================
    // Helper Methods
    // =================
    
    private function extractTextDirectly(string $pdfPath): string
    {
        $escapedPath = escapeshellarg($pdfPath);
        $tempFile = $this->createTempFile('pdftext_');
        $escapedTempFile = escapeshellarg($tempFile);
        
        // Try pdftotext first
        $command = "pdftotext $escapedPath $escapedTempFile 2>&1";
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($tempFile)) {
            $text = file_get_contents($tempFile);
            unlink($tempFile);
            return $text ?: '';
        }
        
        return '';
    }
    
    private function extractTextWithOcr(string $pdfPath): string
    {
        try {
            $images = $this->convertPdfToImages($pdfPath);
            $allText = '';
            
            foreach ($images as $imagePath) {
                $processedImage = $this->preprocessImage($imagePath);
                $text = $this->extractTextFromImage($processedImage);
                $allText .= $text . "\n";
                
                // Clean up processed image if it's different from original
                if ($processedImage !== $imagePath) {
                    $this->deleteFile($processedImage);
                }
            }
            
            // Clean up image files
            foreach ($images as $imagePath) {
                $this->deleteFile($imagePath);
            }
            
            // Clean up temp directory
            $tempDir = dirname($images[0]);
            rmdir($tempDir);
            
            return $allText;
        } catch (\Exception $e) {
            throw new \Exception("OCR processing failed: " . $e->getMessage());
        }
    }
    
    private function storeInvoiceItems(int $invoiceId, array $items): void
    {
        foreach ($items as $lineNumber => $item) {
            $itemData = [
                'staging_invoice_id' => $invoiceId,
                'line_number' => $lineNumber + 1,
                'product_name' => $item['product_name'] ?? '',
                'asin' => $item['asin'] ?? '',
                'sku' => $item['sku'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? 0,
                'total_price' => $item['total_price'] ?? 0
            ];
            
            $fields = [];
            $values = [];
            
            foreach ($itemData as $key => $value) {
                $fields[] = $key;
                $values[] = is_string($value) ? "'" . $this->database->escape($value) . "'" : $value;
            }
            
            $query = "INSERT INTO " . TB_PREF . "amazon_invoice_items_staging (" . 
                     implode(', ', $fields) . ") VALUES (" . 
                     implode(', ', $values) . ")";
            
            $this->database->query($query);
        }
    }
    
    private function generateUniqueFileName(string $originalName): string
    {
        $pathInfo = pathinfo($originalName);
        $extension = $pathInfo['extension'] ?? '';
        $baseName = $pathInfo['filename'] ?? 'uploaded_file';
        
        // Sanitize filename
        $baseName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $baseName);
        
        // Add timestamp and random component
        $uniqueName = $baseName . '_' . date('Ymd_His') . '_' . uniqid();
        
        if ($extension) {
            $uniqueName .= '.' . $extension;
        }
        
        return $uniqueName;
    }
    
    private function isValidPdfFile(string $filePath, string $mimeType): bool
    {
        // Check MIME type
        if ($mimeType !== 'application/pdf') {
            return false;
        }
        
        // Check file signature (PDF magic bytes)
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }
        
        $header = fread($handle, 4);
        fclose($handle);
        
        return $header === '%PDF';
    }
    
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds maximum upload size';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds form maximum size';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}
