<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;
use AmazonInvoices\Models\Invoice;
use AmazonInvoices\Models\InvoiceItem;
use AmazonInvoices\Models\Payment;

/**
 * PDF OCR Processor Service
 * 
 * Handles OCR processing of Amazon PDF invoices using Tesseract
 * to extract invoice data from scanned or image-based PDFs.
 * 
 * @package AmazonInvoices\Services
 * @author  Your Name
 * @since   1.0.0
 */
class PdfOcrProcessor
{
    /**
     * @var DatabaseRepositoryInterface Database repository
     */
    /**
     * @var DatabaseRepositoryInterface
     */
    private $database;

    /**
     * @var string Path to Tesseract executable
     */
    /**
     * @var string
     */
    private $tesseractPath;

    /**
     * @var string Path to temporary directory for processing
     */
    /**
     * @var string
     */
    private $tempPath;

    /**
     * @var array OCR configuration options
     */
    /**
     * @var array
     */
    private $ocrConfig;

    /**
     * @var array PDF processing tools paths
     */
    /**
     * @var array
     */
    private $pdfTools;

    /**
     * Constructor
     * 
     * @param DatabaseRepositoryInterface $database Database repository
     * @param array $config Configuration options
     */
    public function __construct(DatabaseRepositoryInterface $database, array $config = [])
    {
        $this->database = $database;
        
        // Default configuration
        $this->tesseractPath = $config['tesseract_path'] ?? '/usr/bin/tesseract';
        $this->tempPath = $config['temp_path'] ?? '/tmp/amazon_pdf_processing';
        
        $this->ocrConfig = array_merge([
            'language' => 'eng',
            'psm' => '6', // Uniform block of text
            'oem' => '3', // Default OCR Engine Mode
            'dpi' => '300',
            'enhance_image' => true,
            'preprocess' => true
        ], $config['ocr'] ?? []);

        $this->pdfTools = array_merge([
            'poppler_path' => '/usr/bin', // pdftoppm, pdfinfo
            'imagemagick_path' => '/usr/bin', // convert, identify
            'ghostscript_path' => '/usr/bin' // gs
        ], $config['pdf_tools'] ?? []);

        // Ensure temp directory exists
        if (!is_dir($this->tempPath)) {
            if (!mkdir($this->tempPath, 0755, true)) {
                throw new \RuntimeException("Failed to create temp directory: {$this->tempPath}");
            }
        }

        // Verify required tools
        $this->verifyTools();
    }

    /**
     * Process PDF file and extract invoice data
     * 
     * @param string $pdfPath Path to PDF file
     * @return Invoice Invoice object extracted from PDF
     * @throws \Exception If PDF processing fails
     */
    public function processPdf(string $pdfPath): Invoice
    {
        if (!file_exists($pdfPath)) {
            throw new \Exception("PDF file not found: {$pdfPath}");
        }

        $processingId = uniqid('pdf_', true);
        $workDir = $this->tempPath . '/' . $processingId;
        
        if (!mkdir($workDir, 0755, true)) {
            throw new \Exception("Failed to create work directory: {$workDir}");
        }

        try {
            // Convert PDF to images
            $imageFiles = $this->convertPdfToImages($pdfPath, $workDir);
            
            // Process each page with OCR
            $allText = '';
            foreach ($imageFiles as $imageFile) {
                $pageText = $this->performOcr($imageFile);
                $allText .= $pageText . "\n\n";
            }

            // Extract invoice data from OCR text
            $invoice = $this->extractInvoiceFromText($allText, $pdfPath);
            
            // Log successful processing
            $this->logPdfProcessing($pdfPath, 'success', 'Invoice extracted successfully', strlen($allText));

            return $invoice;

        } catch (\Exception $e) {
            // Log processing error
            $this->logPdfProcessing($pdfPath, 'error', $e->getMessage(), 0);
            throw $e;

        } finally {
            // Cleanup temporary files
            $this->cleanupWorkDirectory($workDir);
        }
    }

    /**
     * Process multiple PDF files
     * 
     * @param array $pdfPaths Array of PDF file paths
     * @return array Array of Invoice objects
     */
    public function processPdfBatch(array $pdfPaths): array
    {
        $invoices = [];
        
        foreach ($pdfPaths as $pdfPath) {
            try {
                $invoice = $this->processPdf($pdfPath);
                $invoices[] = $invoice;
            } catch (\Exception $e) {
                // Log error and continue with next PDF
                error_log("Failed to process PDF {$pdfPath}: " . $e->getMessage());
                continue;
            }
        }

        return $invoices;
    }

    /**
     * Convert PDF to image files
     * 
     * @param string $pdfPath Path to PDF file
     * @param string $workDir Working directory
     * @return array Array of image file paths
     * @throws \Exception If conversion fails
     */
    private function convertPdfToImages(string $pdfPath, string $workDir): array
    {
        $outputPrefix = $workDir . '/page';
        $dpi = $this->ocrConfig['dpi'];
        
        // Use pdftoppm (from poppler-utils) for best quality
        $pdfToPpmPath = $this->pdfTools['poppler_path'] . '/pdftoppm';
        
        if (file_exists($pdfToPpmPath)) {
            $command = sprintf(
                '%s -png -r %d "%s" "%s"',
                escapeshellcmd($pdfToPpmPath),
                $dpi,
                escapeshellarg($pdfPath),
                escapeshellarg($outputPrefix)
            );
        } else {
            // Fallback to ImageMagick convert
            $convertPath = $this->pdfTools['imagemagick_path'] . '/convert';
            $command = sprintf(
                '%s -density %d "%s" "%s-%%03d.png"',
                escapeshellcmd($convertPath),
                $dpi,
                escapeshellarg($pdfPath),
                escapeshellarg($outputPrefix)
            );
        }

        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('PDF to image conversion failed: ' . implode("\n", $output));
        }

        // Find generated image files
        $imageFiles = glob($workDir . '/page*.png');
        
        if (empty($imageFiles)) {
            throw new \Exception('No image files generated from PDF');
        }

        sort($imageFiles); // Ensure correct page order
        return $imageFiles;
    }

    /**
     * Perform OCR on image file
     * 
     * @param string $imageFile Path to image file
     * @return string Extracted text
     * @throws \Exception If OCR fails
     */
    private function performOcr(string $imageFile): string
    {
        // Preprocess image if enabled
        if ($this->ocrConfig['preprocess']) {
            $imageFile = $this->preprocessImage($imageFile);
        }

        $outputFile = $imageFile . '_ocr';
        
        $command = sprintf(
            '%s "%s" "%s" -l %s --psm %s --oem %s',
            escapeshellcmd($this->tesseractPath),
            escapeshellarg($imageFile),
            escapeshellarg($outputFile),
            escapeshellarg($this->ocrConfig['language']),
            escapeshellarg($this->ocrConfig['psm']),
            escapeshellarg($this->ocrConfig['oem'])
        );

        // Add additional Tesseract options
        $command .= ' -c tessedit_char_whitelist=0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz.,/$-#:() ';

        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('OCR processing failed: ' . implode("\n", $output));
        }

        $textFile = $outputFile . '.txt';
        
        if (!file_exists($textFile)) {
            throw new \Exception('OCR output file not created');
        }

        $text = file_get_contents($textFile);
        unlink($textFile); // Cleanup OCR output file

        return $text ?: '';
    }

    /**
     * Preprocess image to improve OCR accuracy
     * 
     * @param string $imageFile Path to image file
     * @return string Path to preprocessed image file
     */
    private function preprocessImage(string $imageFile): string
    {
        $processedFile = str_replace('.png', '_processed.png', $imageFile);
        $convertPath = $this->pdfTools['imagemagick_path'] . '/convert';

        // Apply image enhancements
        $command = sprintf(
            '%s "%s" -colorspace Gray -normalize -threshold 50%% -despeckle -median 1 "%s"',
            escapeshellcmd($convertPath),
            escapeshellarg($imageFile),
            escapeshellarg($processedFile)
        );

        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($processedFile)) {
            // Return original file if preprocessing fails
            return $imageFile;
        }

        return $processedFile;
    }

    /**
     * Extract invoice data from OCR text
     * 
     * @param string $text OCR extracted text
     * @param string $pdfPath Original PDF path
     * @return Invoice Invoice object
     * @throws \Exception If extraction fails
     */
    private function extractInvoiceFromText(string $text, string $pdfPath): Invoice
    {
        // Clean up text
        $text = $this->cleanOcrText($text);
        
        // Extract order number
        $orderNumber = $this->extractOrderNumberFromText($text);
        if (!$orderNumber) {
            throw new \Exception('Could not extract order number from PDF text');
        }

        // Extract invoice number
        $invoiceNumber = $this->extractInvoiceNumberFromText($text) ?: 'PDF-' . $orderNumber;

        // Extract date
        $invoiceDate = $this->extractDateFromText($text) ?: new \DateTime();

        // Extract total amount
        $totalAmount = $this->extractTotalAmountFromText($text);
        if ($totalAmount === null) {
            throw new \Exception('Could not extract total amount from PDF text');
        }

        // Extract currency
        $currency = $this->extractCurrencyFromText($text) ?: 'USD';

        // Create invoice
        $invoice = new Invoice(
            $invoiceNumber,
            $orderNumber,
            $invoiceDate,
            $totalAmount,
            $currency
        );

        // Set additional properties
        $invoice->setPdfPath($pdfPath);
        $invoice->setTaxAmount($this->extractTaxAmountFromText($text) ?: 0.0);
        $invoice->setShippingAmount($this->extractShippingAmountFromText($text) ?: 0.0);
        $invoice->setRawData(json_encode([
            'pdf_path' => $pdfPath,
            'ocr_text' => $text,
            'processed_at' => date('Y-m-d H:i:s'),
            'ocr_confidence' => $this->calculateOcrConfidence($text)
        ]));

        // Extract items
        $items = $this->extractItemsFromText($text);
        foreach ($items as $item) {
            $invoice->addItem($item);
        }

        // Extract payment information
        $payment = $this->extractPaymentFromText($text, $totalAmount);
        if ($payment) {
            $invoice->addPayment($payment);
        }

        return $invoice;
    }

    /**
     * Clean OCR text to improve parsing
     * 
     * @param string $text Raw OCR text
     * @return string Cleaned text
     */
    private function cleanOcrText(string $text): string
    {
        // Fix common OCR errors
        $replacements = [
            // Common character misrecognitions
            '/\b0(?=\d)/u' => 'O', // 0 -> O in some contexts
            '/(?<=\d)O\b/u' => '0', // O -> 0 at end of numbers
            '/\bl(?=\d)/u' => '1', // l -> 1 before digits
            '/(?<=\d)l\b/u' => '1', // l -> 1 after digits
            '/\bS(?=\d)/u' => '5', // S -> 5 before digits
            
            // Clean up spacing
            '/\s+/' => ' ', // Multiple spaces to single space
            '/\n\s*\n/' => "\n", // Multiple newlines to single
            
            // Fix common Amazon-specific terms
            '/Arr[au]zon/i' => 'Amazon',
            '/0rder/i' => 'Order',
            '/Tota[li]/i' => 'Total',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return trim($text);
    }

    /**
     * Extract order number from OCR text
     * 
     * @param string $text OCR text
     * @return string|null Order number
     */
    private function extractOrderNumberFromText(string $text): ?string
    {
        $patterns = [
            '/Order\s*#?\s*:?\s*([0-9]{3}-[0-9]{7}-[0-9]{7})/i',
            '/Order\s*(?:Number|ID)?\s*:?\s*([A-Z0-9\-]{15,20})/i',
            '/Amazon\.com\s*order\s*#?\s*:?\s*([A-Z0-9\-]{10,20})/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Extract invoice number from OCR text
     * 
     * @param string $text OCR text
     * @return string|null Invoice number
     */
    private function extractInvoiceNumberFromText(string $text): ?string
    {
        $patterns = [
            '/Invoice\s*#?\s*:?\s*([A-Z0-9\-]{8,20})/i',
            '/Receipt\s*#?\s*:?\s*([A-Z0-9\-]{8,20})/i',
            '/Document\s*#?\s*:?\s*([A-Z0-9\-]{8,20})/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Extract date from OCR text
     * 
     * @param string $text OCR text
     * @return \DateTime|null Date
     */
    private function extractDateFromText(string $text): ?\DateTime
    {
        $patterns = [
            '/(?:Invoice\s*Date|Order\s*Date|Date)\s*:?\s*([A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4})/i',
            '/(?:Invoice\s*Date|Order\s*Date|Date)\s*:?\s*(\d{1,2}\/\d{1,2}\/\d{4})/i',
            '/(?:Invoice\s*Date|Order\s*Date|Date)\s*:?\s*(\d{4}-\d{2}-\d{2})/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                try {
                    return new \DateTime($matches[1]);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Extract total amount from OCR text
     * 
     * @param string $text OCR text
     * @return float|null Total amount
     */
    private function extractTotalAmountFromText(string $text): ?float
    {
        $patterns = [
            '/Total\s*(?:Amount|Price)?\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Grand\s*Total\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Order\s*Total\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Amount\s*(?:Due|Charged)?\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
        }

        return null;
    }

    /**
     * Extract currency from OCR text
     * 
     * @param string $text OCR text
     * @return string|null Currency code
     */
    private function extractCurrencyFromText(string $text): ?string
    {
        $patterns = [
            '/\$[0-9,]+\.?[0-9]*/' => 'USD',
            '/€[0-9,]+\.?[0-9]*/' => 'EUR',
            '/£[0-9,]+\.?[0-9]*/' => 'GBP',
            '/¥[0-9,]+\.?[0-9]*/' => 'JPY',
            '/CAD?\s*\$?[0-9,]+\.?[0-9]*/' => 'CAD',
            '/Currency\s*:?\s*(USD|EUR|GBP|JPY|CAD)/i' => '$1'
        ];

        foreach ($patterns as $pattern => $currency) {
            if (preg_match($pattern, $text, $matches)) {
                return isset($matches[1]) ? strtoupper($matches[1]) : $currency;
            }
        }

        return null;
    }

    /**
     * Extract tax amount from OCR text
     * 
     * @param string $text OCR text
     * @return float|null Tax amount
     */
    private function extractTaxAmountFromText(string $text): ?float
    {
        $patterns = [
            '/Tax\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Sales\s*Tax\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/VAT\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
        }

        return null;
    }

    /**
     * Extract shipping amount from OCR text
     * 
     * @param string $text OCR text
     * @return float|null Shipping amount
     */
    private function extractShippingAmountFromText(string $text): ?float
    {
        $patterns = [
            '/Shipping\s*(?:&\s*Handling)?\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/Delivery\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i',
            '/S&H\s*:?\s*\$?([0-9,]+\.?[0-9]*)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
        }

        return null;
    }

    /**
     * Extract items from OCR text
     * 
     * @param string $text OCR text
     * @return InvoiceItem[] Array of invoice items
     */
    private function extractItemsFromText(string $text): array
    {
        $items = [];
        
        // Look for item patterns in the text
        // This is complex due to varying PDF layouts
        $lines = explode("\n", $text);
        $inItemSection = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Detect start of items section
            if (preg_match('/(?:Items\s*Ordered|Product\s*Details|Order\s*Items)/i', $line)) {
                $inItemSection = true;
                continue;
            }
            
            // Detect end of items section
            if (preg_match('/(?:Subtotal|Tax|Shipping|Total)/i', $line) && $inItemSection) {
                break;
            }
            
            if (!$inItemSection) {
                continue;
            }
            
            // Try to extract item information
            // Pattern: Description ... Quantity ... Price
            if (preg_match('/^(.+?)\s+(?:Qty:?\s*)?(\d+)\s+\$?([0-9,]+\.?[0-9]*)$/i', $line, $matches)) {
                $description = trim($matches[1]);
                $quantity = (int) $matches[2];
                $price = (float) str_replace(',', '', $matches[3]);
                
                if (strlen($description) > 3 && $price > 0) {
                    $totalPrice = $price * $quantity;
                    $item = new InvoiceItem(count($items) + 1, $description, $quantity, $price, $totalPrice);
                    $items[] = $item;
                }
            }
            // Pattern: Description on one line, price on next
            elseif (preg_match('/\$([0-9,]+\.?[0-9]*)$/', $line, $matches) && !empty($lastDescription)) {
                $price = (float) str_replace(',', '', $matches[1]);
                $item = new InvoiceItem(count($items) + 1, $lastDescription, 1, $price, $price);
                $items[] = $item;
                $lastDescription = null;
            }
            // Potential item description
            elseif (strlen($line) > 10 && !preg_match('/[0-9]{3}-[0-9]{7}-[0-9]{7}/', $line)) {
                $lastDescription = $line;
            }
        }
        
        return $items;
    }

    /**
     * Extract payment information from OCR text
     * 
     * @param string $text OCR text
     * @param float $amount Payment amount
     * @return Payment|null Payment object
     */
    private function extractPaymentFromText(string $text, float $amount): ?Payment
    {
        $paymentMethod = 'Credit Card'; // Default
        
        $methods = [
            '/Visa\s*(?:ending\s*in\s*)?([0-9]{4})/i' => 'Visa',
            '/MasterCard\s*(?:ending\s*in\s*)?([0-9]{4})/i' => 'MasterCard',
            '/American\s*Express\s*(?:ending\s*in\s*)?([0-9]{4})/i' => 'American Express',
            '/Discover\s*(?:ending\s*in\s*)?([0-9]{4})/i' => 'Discover',
            '/PayPal/i' => 'PayPal',
            '/Gift\s*Card/i' => 'Gift Card',
            '/Amazon\s*Pay/i' => 'Amazon Pay'
        ];

        foreach ($methods as $pattern => $method) {
            if (preg_match($pattern, $text, $matches)) {
                $paymentMethod = $method;
                if (isset($matches[1])) {
                    $paymentMethod .= ' ending in ' . $matches[1];
                }
                break;
            }
        }

        return new Payment(
            $paymentMethod,
            $amount,
            'PDF-' . date('YmdHis')
        );
    }

    /**
     * Calculate OCR confidence score
     * 
     * @param string $text OCR text
     * @return float Confidence score (0-100)
     */
    private function calculateOcrConfidence(string $text): float
    {
        // Simple heuristic based on text characteristics
        $confidence = 100.0;
        
        // Reduce confidence for excessive special characters
        $specialCharRatio = (strlen($text) - strlen(preg_replace('/[^a-zA-Z0-9\s]/', '', $text))) / strlen($text);
        if ($specialCharRatio > 0.3) {
            $confidence -= ($specialCharRatio - 0.3) * 100;
        }
        
        // Reduce confidence for very short text
        if (strlen($text) < 100) {
            $confidence -= (100 - strlen($text)) * 0.5;
        }
        
        // Increase confidence for Amazon-specific terms
        $amazonTerms = ['amazon', 'order', 'total', 'invoice', 'shipping'];
        $termMatches = 0;
        foreach ($amazonTerms as $term) {
            if (stripos($text, $term) !== false) {
                $termMatches++;
            }
        }
        $confidence += $termMatches * 5;
        
        return max(0, min(100, $confidence));
    }

    /**
     * Verify required tools are available
     * 
     * @throws \Exception If required tools are missing
     */
    private function verifyTools(): void
    {
        $requiredTools = [
            'tesseract' => $this->tesseractPath,
            'pdftoppm' => $this->pdfTools['poppler_path'] . '/pdftoppm',
            'convert' => $this->pdfTools['imagemagick_path'] . '/convert'
        ];

        $missingTools = [];
        
        foreach ($requiredTools as $tool => $path) {
            if (!file_exists($path) || !is_executable($path)) {
                // Try to find tool in PATH
                $whichOutput = shell_exec("which $tool 2>/dev/null");
                if (!$whichOutput || !file_exists(trim($whichOutput))) {
                    $missingTools[] = $tool;
                }
            }
        }

        if (!empty($missingTools)) {
            throw new \Exception('Missing required tools: ' . implode(', ', $missingTools) . 
                               '. Please install: apt-get install tesseract-ocr poppler-utils imagemagick');
        }
    }

    /**
     * Log PDF processing activity
     * 
     * @param string $pdfPath PDF file path
     * @param string $status Processing status
     * @param string $details Processing details
     * @param int $textLength Length of extracted text
     */
    private function logPdfProcessing(string $pdfPath, string $status, string $details, int $textLength): void
    {
        $this->database->query(
            "INSERT INTO " . $this->database->getTablePrefix() . "pdf_processing_log 
             (pdf_path, status, details, text_length, processed_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [basename($pdfPath), $status, $details, $textLength]
        );
    }

    /**
     * Cleanup work directory
     * 
     * @param string $workDir Work directory path
     */
    private function cleanupWorkDirectory(string $workDir): void
    {
        if (is_dir($workDir)) {
            $files = glob($workDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($workDir);
        }
    }

    /**
     * Get OCR configuration
     * 
     * @return array OCR configuration
     */
    public function getOcrConfig(): array
    {
        return $this->ocrConfig;
    }

    /**
     * Update OCR configuration
     * 
     * @param array $config New configuration
     */
    public function updateOcrConfig(array $config): void
    {
        $this->ocrConfig = array_merge($this->ocrConfig, $config);
    }

    /**
     * Test OCR tools
     * 
     * @return array Test results
     */
    public function testOcrTools(): array
    {
        $results = [];
        
        try {
            $this->verifyTools();
            $results['tools_available'] = true;
        } catch (\Exception $e) {
            $results['tools_available'] = false;
            $results['tools_error'] = $e->getMessage();
        }

        // Test Tesseract
        $output = shell_exec($this->tesseractPath . ' --version 2>&1');
        $results['tesseract_version'] = $output ? trim(explode("\n", $output)[0]) : 'Not found';

        // Test temporary directory
        $results['temp_writable'] = is_writable($this->tempPath);

        return $results;
    }
}
