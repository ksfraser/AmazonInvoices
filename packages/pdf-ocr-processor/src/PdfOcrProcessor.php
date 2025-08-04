<?php

declare(strict_types=1);

namespace AmazonInvoices\PdfProcessor;

use AmazonInvoices\PdfProcessor\Interfaces\StorageInterface;
use AmazonInvoices\PdfProcessor\Interfaces\FileSystemInterface;
use AmazonInvoices\PdfProcessor\Interfaces\OcrEngineInterface;
use AmazonInvoices\PdfProcessor\Interfaces\InvoiceParserInterface;

/**
 * PDF OCR Invoice Processor
 * 
 * Standalone library for processing Amazon invoices from PDF files
 * Framework-agnostic with dependency injection
 */
class PdfOcrProcessor
{
    private StorageInterface $storage;
    private FileSystemInterface $fileSystem;
    private OcrEngineInterface $ocrEngine;
    private InvoiceParserInterface $parser;
    
    public function __construct(
        StorageInterface $storage,
        FileSystemInterface $fileSystem,
        OcrEngineInterface $ocrEngine,
        InvoiceParserInterface $parser
    ) {
        $this->storage = $storage;
        $this->fileSystem = $fileSystem;
        $this->ocrEngine = $ocrEngine;
        $this->parser = $parser;
    }
    
    /**
     * Process uploaded PDF files
     */
    public function processUploadedFiles(array $uploadedFiles, string $storageDir): array
    {
        $results = [];
        
        foreach ($uploadedFiles as $fileData) {
            try {
                $result = $this->processUploadedFile($fileData, $storageDir);
                $results[] = $result;
            } catch (\Exception $e) {
                $results[] = [
                    'status' => 'error',
                    'filename' => $fileData['name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Process PDFs from a directory
     */
    public function processDirectory(string $sourceDir, string $storageDir, array $options = []): array
    {
        $extensions = $options['extensions'] ?? ['pdf'];
        $files = $this->fileSystem->listFiles($sourceDir, $extensions);
        $results = [];
        
        foreach ($files as $filePath) {
            try {
                $result = $this->processPdfFile($filePath, $storageDir);
                $results[] = $result;
            } catch (\Exception $e) {
                $results[] = [
                    'status' => 'error',
                    'file_path' => $filePath,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Process a single uploaded file
     */
    public function processUploadedFile(array $fileData, string $storageDir): array
    {
        // Validate file
        if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            throw new \Exception("Invalid uploaded file");
        }
        
        if ($fileData['type'] !== 'application/pdf') {
            throw new \Exception("Only PDF files are supported");
        }
        
        // Store uploaded file
        $filename = $this->sanitizeFilename($fileData['name']);
        $targetPath = $storageDir . '/' . date('Y/m/d') . '/' . $filename;
        $storedPath = $this->fileSystem->storeUploadedFile($fileData, $targetPath);
        
        return $this->processPdfFile($storedPath, $storageDir);
    }
    
    /**
     * Process a single PDF file
     */
    public function processPdfFile(string $filePath, string $storageDir): array
    {
        $fileHash = $this->fileSystem->getFileHash($filePath);
        $fileMetadata = $this->fileSystem->getFileMetadata($filePath);
        
        // Check if already processed
        if ($this->storage->isPdfProcessed($fileHash)) {
            return [
                'status' => 'duplicate',
                'file_path' => $filePath,
                'file_hash' => $fileHash,
                'message' => 'PDF already processed'
            ];
        }
        
        // Initialize PDF log data
        $pdfLogData = [
            'pdf_file_path' => $filePath,
            'pdf_file_hash' => $fileHash,
            'pdf_file_size' => $fileMetadata['size'],
            'processing_status' => 'processing'
        ];
        
        $startTime = microtime(true);
        
        try {
            // Convert PDF to images
            $tempDir = $storageDir . '/temp/' . $fileHash;
            $this->fileSystem->ensureDirectory($tempDir);
            
            $config = $this->storage->getOcrConfig();
            $imageFiles = $this->ocrEngine->pdfToImages($filePath, $tempDir, [
                'dpi' => $config['pdf_dpi'] ?? 300,
                'format' => 'png'
            ]);
            
            if (empty($imageFiles)) {
                throw new \Exception("Failed to convert PDF to images");
            }
            
            // Process each page
            $allText = '';
            $processedPages = [];
            
            foreach ($imageFiles as $imagePath) {
                $pageResult = $this->processImagePage($imagePath, $config);
                $allText .= $pageResult['text'] . "\n";
                $processedPages[] = $pageResult;
            }
            
            $processingTime = microtime(true) - $startTime;
            
            // Parse invoice data from OCR text
            $invoiceData = $this->parser->parseAmazonInvoice($allText, [
                'file_path' => $filePath,
                'file_hash' => $fileHash,
                'pages' => $processedPages
            ]);
            
            if (!$invoiceData) {
                $pdfLogData['processing_status'] = 'error';
                $pdfLogData['error_message'] = 'No invoice data found in PDF';
                $pdfLogData['extracted_text'] = $allText;
                $pdfLogData['ocr_processing_time'] = $processingTime;
                $logId = $this->storage->storePdfLog($pdfLogData);
                
                return [
                    'status' => 'no_invoice_data',
                    'file_path' => $filePath,
                    'log_id' => $logId,
                    'message' => 'No Amazon invoice data found in PDF'
                ];
            }
            
            // Check for duplicates across all import methods
            $duplicate = $this->storage->findDuplicateInvoice($invoiceData);
            if ($duplicate) {
                $pdfLogData['processing_status'] = 'duplicate';
                $pdfLogData['error_message'] = 'Duplicate invoice found: ' . $duplicate['source'];
                $pdfLogData['extracted_text'] = $allText;
                $pdfLogData['extracted_data'] = json_encode($invoiceData);
                $pdfLogData['ocr_processing_time'] = $processingTime;
                $logId = $this->storage->storePdfLog($pdfLogData);
                
                return [
                    'status' => 'duplicate_invoice',
                    'file_path' => $filePath,
                    'log_id' => $logId,
                    'duplicate_source' => $duplicate,
                    'message' => 'Invoice already imported from another source'
                ];
            }
            
            // Store successful processing
            $pdfLogData['processing_status'] = 'completed';
            $pdfLogData['extracted_text'] = $allText;
            $pdfLogData['extracted_data'] = json_encode($invoiceData);
            $pdfLogData['ocr_processing_time'] = $processingTime;
            $pdfLogData['processing_metadata'] = json_encode([
                'pages_processed' => count($imageFiles),
                'total_text_length' => strlen($allText),
                'config_used' => $config
            ]);
            
            $logId = $this->storage->storePdfLog($pdfLogData);
            
            // Store invoice data
            $invoiceData['source_type'] = 'pdf';
            $invoiceData['source_id'] = $fileHash;
            $invoiceData['pdf_log_id'] = $logId;
            $invoiceId = $this->storage->storeInvoice($invoiceData);
            
            // Clean up temporary files
            $this->cleanupTempFiles($tempDir);
            
            return [
                'status' => 'success',
                'file_path' => $filePath,
                'file_hash' => $fileHash,
                'log_id' => $logId,
                'invoice_id' => $invoiceId,
                'processing_time' => $processingTime,
                'pages_processed' => count($imageFiles),
                'invoice_data' => $invoiceData
            ];
            
        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;
            
            $pdfLogData['processing_status'] = 'error';
            $pdfLogData['error_message'] = $e->getMessage();
            $pdfLogData['ocr_processing_time'] = $processingTime;
            $logId = $this->storage->storePdfLog($pdfLogData);
            
            // Clean up temporary files on error
            if (isset($tempDir)) {
                $this->cleanupTempFiles($tempDir);
            }
            
            throw new \Exception("PDF processing failed: " . $e->getMessage());
        }
    }
    
    /**
     * Process a single image page
     */
    private function processImagePage(string $imagePath, array $config): array
    {
        $startTime = microtime(true);
        
        // Preprocess image if enabled
        $processedImagePath = $imagePath;
        if ($config['image_preprocessing'] ?? true) {
            $enhancementLevel = $config['image_enhancement_level'] ?? 'basic';
            $preprocessDir = dirname($imagePath) . '/preprocessed';
            $this->fileSystem->ensureDirectory($preprocessDir);
            
            $processedImagePath = $this->ocrEngine->preprocessImage(
                $imagePath,
                $preprocessDir . '/' . basename($imagePath),
                [
                    'enhancement_level' => $enhancementLevel,
                    'dpi' => $config['pdf_dpi'] ?? 300
                ]
            );
        }
        
        // Perform OCR
        $ocrOptions = [
            'language' => $config['tesseract_language'] ?? 'eng',
            'engine_mode' => $config['ocr_engine_mode'] ?? 3,
            'page_segmentation_mode' => $config['page_segmentation_mode'] ?? 6,
            'tesseract_path' => $config['tesseract_path'] ?? '/usr/bin/tesseract',
            'data_path' => $config['tesseract_data_path'] ?? null
        ];
        
        $extractedText = $this->ocrEngine->performOcr($processedImagePath, $ocrOptions);
        $confidence = $this->ocrEngine->getConfidenceScore($processedImagePath);
        $processingTime = microtime(true) - $startTime;
        
        // Check confidence threshold
        $confidenceThreshold = $config['confidence_threshold'] ?? 60.0;
        if ($confidence < $confidenceThreshold) {
            throw new \Exception("OCR confidence too low: {$confidence}% (threshold: {$confidenceThreshold}%)");
        }
        
        return [
            'image_path' => $imagePath,
            'processed_image_path' => $processedImagePath,
            'text' => $extractedText,
            'confidence' => $confidence,
            'processing_time' => $processingTime,
            'text_length' => strlen($extractedText)
        ];
    }
    
    /**
     * Sanitize filename for storage
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove directory traversal
        $filename = basename($filename);
        
        // Remove special characters but keep extension
        $pathInfo = pathinfo($filename);
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $pathInfo['filename']);
        $extension = $pathInfo['extension'] ?? '';
        
        // Add timestamp to avoid conflicts
        $timestamp = date('YmdHis');
        
        return $name . '_' . $timestamp . ($extension ? '.' . $extension : '');
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles(string $tempDir): bool
    {
        if (!is_dir($tempDir)) {
            return true;
        }
        
        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $this->fileSystem->deleteFile($file);
            }
        }
        
        return rmdir($tempDir);
    }
    
    /**
     * Get processing statistics
     */
    public function getProcessingStats(array $filters = []): array
    {
        // This would be implemented by the storage interface
        // to provide statistics about PDF processing
        return [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'average_processing_time' => 0.0
        ];
    }
}
