<?php

declare(strict_types=1);

namespace AmazonInvoices\PdfProcessor\Interfaces;

/**
 * Storage Interface for PDF Processing
 */
interface StorageInterface
{
    /**
     * Store processed PDF data
     */
    public function storePdfLog(array $pdfData): int;
    
    /**
     * Check if PDF already processed
     */
    public function isPdfProcessed(string $fileHash): bool;
    
    /**
     * Store extracted invoice data
     */
    public function storeInvoice(array $invoiceData): int;
    
    /**
     * Check for duplicate invoices
     */
    public function findDuplicateInvoice(array $invoiceData): ?array;
    
    /**
     * Get OCR configuration
     */
    public function getOcrConfig(): array;
    
    /**
     * Store file metadata
     */
    public function storeFileMetadata(string $filePath, array $metadata): int;
}

/**
 * File System Interface
 */
interface FileSystemInterface
{
    /**
     * Store uploaded file
     */
    public function storeUploadedFile(array $fileData, string $targetPath): string;
    
    /**
     * List files in directory
     */
    public function listFiles(string $directory, array $extensions = []): array;
    
    /**
     * Get file hash
     */
    public function getFileHash(string $filePath): string;
    
    /**
     * Get file metadata
     */
    public function getFileMetadata(string $filePath): array;
    
    /**
     * Create directory if not exists
     */
    public function ensureDirectory(string $path): bool;
    
    /**
     * Delete file
     */
    public function deleteFile(string $filePath): bool;
}

/**
 * OCR Engine Interface
 */
interface OcrEngineInterface
{
    /**
     * Perform OCR on image file
     */
    public function performOcr(string $imagePath, array $options = []): string;
    
    /**
     * Convert PDF to images
     */
    public function pdfToImages(string $pdfPath, string $outputDir, array $options = []): array;
    
    /**
     * Preprocess image for better OCR
     */
    public function preprocessImage(string $imagePath, string $outputPath, array $options = []): string;
    
    /**
     * Get OCR confidence score
     */
    public function getConfidenceScore(string $imagePath): float;
}

/**
 * Invoice Parser Interface
 */
interface InvoiceParserInterface
{
    /**
     * Parse Amazon invoice from OCR text
     */
    public function parseAmazonInvoice(string $ocrText, array $metadata = []): ?array;
    
    /**
     * Extract structured data from text
     */
    public function extractInvoiceData(string $text): ?array;
    
    /**
     * Validate extracted invoice data
     */
    public function validateInvoiceData(array $data): bool;
}
