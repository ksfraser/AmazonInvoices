<?php

declare(strict_types=1);

namespace AmazonInvoices\Services;

use AmazonInvoices\Interfaces\DatabaseRepositoryInterface;

/**
 * Unified Invoice Import Service
 * 
 * Coordinates between Gmail and PDF import methods, handles duplicate detection,
 * and provides a unified interface for processing invoices from multiple sources
 */
class UnifiedInvoiceImportService
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
     * @var GmailProcessorWrapper
     */
    private $gmailProcessor;

    /**
     * @var PdfOcrProcessorWrapper
     */
    private $pdfProcessor;
    
    public function __construct(
        DatabaseRepositoryInterface $database,
        DuplicateDetectionService $duplicateDetector
    ) {
        $this->database = $database;
        $this->duplicateDetector = $duplicateDetector;
        
        // Initialize the wrapper services
        $this->gmailProcessor = new GmailProcessorWrapper($database, $duplicateDetector);
        $this->pdfProcessor = new PdfOcrProcessorWrapper($database, $duplicateDetector);
    }
    
    /**
     * Process emails and return results with duplicate checking
     */
    /**
     * @param array $options
     * @return array
     */
    public function processEmails($options = array())
    {
        try {
            $results = $this->gmailProcessor->processEmails($options);
            
            // Enhance results with duplicate information
            foreach ($results as &$result) {
                if ($result['success'] && isset($result['invoice_data'])) {
                    $duplicate = $this->duplicateDetector->findDuplicateInvoice($result['invoice_data']);
                    if ($duplicate) {
                        $result['duplicate_info'] = $duplicate;
                        $result['is_duplicate'] = true;
                    } else {
                        $result['is_duplicate'] = false;
                    }
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Email processing failed: ' . $e->getMessage(),
                'results' => []
            ];
        }
    }
    
    /**
     * Process a single PDF file
     */
    /**
     * @param string $filePath
     * @param array $options
     * @return array
     */
    public function processPdfFile($filePath, $options = array())
    {
        try {
            $result = $this->pdfProcessor->processPdfFile($filePath, $options);
            
            // Check for duplicates
            if ($result['success'] && isset($result['invoice_data'])) {
                $duplicate = $this->duplicateDetector->findDuplicateInvoice($result['invoice_data']);
                if ($duplicate) {
                    $result['duplicate_info'] = $duplicate;
                    $result['is_duplicate'] = true;
                } else {
                    $result['is_duplicate'] = false;
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'PDF processing failed: ' . $e->getMessage(),
                'file' => $filePath
            ];
        }
    }
    
    /**
     * Process multiple PDF files from a directory
     */
    /**
     * @param string $directoryPath
     * @param array $options
     * @return array
     */
    public function processPdfDirectory($directoryPath, $options = array())
    {
        try {
            $results = $this->pdfProcessor->processDirectory($directoryPath, $options);
            
            // Enhance results with duplicate information
            foreach ($results as &$result) {
                if ($result['success'] && isset($result['invoice_data'])) {
                    $duplicate = $this->duplicateDetector->findDuplicateInvoice($result['invoice_data']);
                    if ($duplicate) {
                        $result['duplicate_info'] = $duplicate;
                        $result['is_duplicate'] = true;
                    } else {
                        $result['is_duplicate'] = false;
                    }
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Directory processing failed: ' . $e->getMessage(),
                'directory' => $directoryPath,
                'results' => []
            ];
        }
    }
    
    /**
     * Process uploaded PDF files
     */
    /**
     * @param array $uploadedFiles
     * @param array $options
     * @return array
     */
    public function processUploadedFiles($uploadedFiles, $options = array())
    {
        try {
            $results = $this->pdfProcessor->processUploadedFiles($uploadedFiles, $options);
            
            // Enhance results with duplicate information
            foreach ($results as &$result) {
                if ($result['success'] && isset($result['invoice_data'])) {
                    $duplicate = $this->duplicateDetector->findDuplicateInvoice($result['invoice_data']);
                    if ($duplicate) {
                        $result['duplicate_info'] = $duplicate;
                        $result['is_duplicate'] = true;
                    } else {
                        $result['is_duplicate'] = false;
                    }
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'File upload processing failed: ' . $e->getMessage(),
                'results' => []
            ];
        }
    }
    
    /**
     * Get processing statistics across all import methods
     */
    /**
     * @param int $daysBack
     * @return array
     */
    public function getProcessingStatistics($daysBack = 30)
    {
        $sinceDateString = date('Y-m-d', strtotime("-$daysBack days"));
        
        // Email statistics
        $emailQuery = "SELECT 
                           COUNT(*) as total_emails,
                           SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_emails,
                           SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_emails
                       FROM " . TB_PREF . "amazon_email_logs 
                       WHERE created_at >= '$sinceDateString'";
        
        $emailResult = $this->database->query($emailQuery);
        $emailStats = $this->database->fetch($emailResult) ?: [];
        
        // PDF statistics
        $pdfQuery = "SELECT 
                         COUNT(*) as total_pdfs,
                         SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_pdfs,
                         SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_pdfs
                     FROM " . TB_PREF . "amazon_pdf_logs 
                     WHERE created_at >= '$sinceDateString'";
        
        $pdfResult = $this->database->query($pdfQuery);
        $pdfStats = $this->database->fetch($pdfResult) ?: [];
        
        // Invoice statistics
        $invoiceQuery = "SELECT 
                             COUNT(*) as total_invoices,
                             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_invoices,
                             SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as completed_invoices,
                             COUNT(DISTINCT source_type) as import_methods_used
                         FROM " . TB_PREF . "amazon_invoices_staging 
                         WHERE created_at >= '$sinceDateString'";
        
        $invoiceResult = $this->database->query($invoiceQuery);
        $invoiceStats = $this->database->fetch($invoiceResult) ?: [];
        
        // Duplicate statistics
        $duplicateQuery = "SELECT 
                               COUNT(*) as total_duplicates,
                               AVG(confidence_score) as avg_confidence
                           FROM " . TB_PREF . "duplicate_detection_log 
                           WHERE created_at >= '$sinceDateString' AND is_duplicate = 1";
        
        $duplicateResult = $this->database->query($duplicateQuery);
        $duplicateStats = $this->database->fetch($duplicateResult) ?: [];
        
        return [
            'period_days' => $daysBack,
            'since_date' => $sinceDateString,
            'email_import' => [
                'total_emails' => (int)($emailStats['total_emails'] ?? 0),
                'processed_emails' => (int)($emailStats['processed_emails'] ?? 0),
                'failed_emails' => (int)($emailStats['failed_emails'] ?? 0),
                'success_rate' => $this->calculateSuccessRate($emailStats['processed_emails'] ?? 0, $emailStats['total_emails'] ?? 0)
            ],
            'pdf_import' => [
                'total_pdfs' => (int)($pdfStats['total_pdfs'] ?? 0),
                'processed_pdfs' => (int)($pdfStats['processed_pdfs'] ?? 0),
                'failed_pdfs' => (int)($pdfStats['failed_pdfs'] ?? 0),
                'success_rate' => $this->calculateSuccessRate($pdfStats['processed_pdfs'] ?? 0, $pdfStats['total_pdfs'] ?? 0)
            ],
            'invoice_processing' => [
                'total_invoices' => (int)($invoiceStats['total_invoices'] ?? 0),
                'pending_invoices' => (int)($invoiceStats['pending_invoices'] ?? 0),
                'completed_invoices' => (int)($invoiceStats['completed_invoices'] ?? 0),
                'import_methods_used' => (int)($invoiceStats['import_methods_used'] ?? 0)
            ],
            'duplicate_detection' => [
                'total_duplicates_found' => (int)($duplicateStats['total_duplicates'] ?? 0),
                'average_confidence' => round((float)($duplicateStats['avg_confidence'] ?? 0), 2)
            ]
        ];
    }
    
    /**
     * Get recent processing activity across all import methods
     */
    /**
     * @param int $limit
     * @return array
     */
    public function getRecentActivity($limit = 50)
    {
        $activities = [];
        
        // Get recent email activities
        $emailQuery = "SELECT 
                           'email' as source_type,
                           gmail_message_id as source_id,
                           email_subject as description,
                           status,
                           created_at,
                           error_message
                       FROM " . TB_PREF . "amazon_email_logs 
                       ORDER BY created_at DESC 
                       LIMIT " . ($limit / 2);
        
        $emailResult = $this->database->query($emailQuery);
        while ($row = $this->database->fetch($emailResult)) {
            $activities[] = $row;
        }
        
        // Get recent PDF activities
        $pdfQuery = "SELECT 
                         'pdf' as source_type,
                         file_path as source_id,
                         file_name as description,
                         status,
                         created_at,
                         error_message
                     FROM " . TB_PREF . "amazon_pdf_logs 
                     ORDER BY created_at DESC 
                     LIMIT " . ($limit / 2);
        
        $pdfResult = $this->database->query($pdfQuery);
        while ($row = $this->database->fetch($pdfResult)) {
            $activities[] = $row;
        }
        
        // Sort activities by date
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($activities, 0, $limit);
    }
    
    /**
     * Get pending invoices that need review
     */
    /**
     * @param int $limit
     * @return array
     */
    public function getPendingInvoices($limit = 100)
    {
        $query = "SELECT 
                      s.*,
                      COUNT(i.id) as item_count,
                      SUM(i.total_price) as items_total
                  FROM " . TB_PREF . "amazon_invoices_staging s
                  LEFT JOIN " . TB_PREF . "amazon_invoice_items_staging i ON s.id = i.staging_invoice_id
                  WHERE s.status = 'pending'
                  GROUP BY s.id
                  ORDER BY s.created_at DESC
                  LIMIT $limit";
        
        $result = $this->database->query($query);
        $invoices = [];
        
        while ($row = $this->database->fetch($result)) {
            $invoices[] = $row;
        }
        
        return $invoices;
    }
    
    /**
     * Mark invoice as processed
     */
    /**
     * @param int $invoiceId
     * @param string|null $faInvoiceNumber
     * @return bool
     */
    public function markInvoiceAsProcessed($invoiceId, $faInvoiceNumber = null)
    {
        $updates = ['status = "processed"', 'processed_at = NOW()'];
        
        if ($faInvoiceNumber) {
            $escapedInvoiceNumber = $this->database->escape($faInvoiceNumber);
            $updates[] = "fa_invoice_number = '$escapedInvoiceNumber'";
        }
        
        $query = "UPDATE " . TB_PREF . "amazon_invoices_staging 
                  SET " . implode(', ', $updates) . "
                  WHERE id = $invoiceId";
        
        return $this->database->query($query) !== false;
    }
    
    /**
     * Get invoice details with items and payments
     */
    public function getInvoiceDetails(int $invoiceId): ?array
    {
        // Get invoice data
        $invoiceQuery = "SELECT * FROM " . TB_PREF . "amazon_invoices_staging WHERE id = $invoiceId";
        $invoiceResult = $this->database->query($invoiceQuery);
        $invoice = $this->database->fetch($invoiceResult);
        
        if (!$invoice) {
            return null;
        }
        
        // Get items
        $itemsQuery = "SELECT * FROM " . TB_PREF . "amazon_invoice_items_staging 
                       WHERE staging_invoice_id = $invoiceId 
                       ORDER BY line_number";
        $itemsResult = $this->database->query($itemsQuery);
        $items = [];
        
        while ($item = $this->database->fetch($itemsResult)) {
            $items[] = $item;
        }
        
        // Get payments
        $paymentsQuery = "SELECT * FROM " . TB_PREF . "amazon_payment_staging 
                          WHERE staging_invoice_id = $invoiceId";
        $paymentsResult = $this->database->query($paymentsQuery);
        $payments = [];
        
        while ($payment = $this->database->fetch($paymentsResult)) {
            $payments[] = $payment;
        }
        
        $invoice['items'] = $items;
        $invoice['payments'] = $payments;
        
        return $invoice;
    }
    
    /**
     * Clean up old processed records
     */
    /**
     * @param int $daysToKeep
     * @return array
     */
    public function cleanupOldRecords($daysToKeep = 90)
    {
        $cutoffDate = date('Y-m-d', strtotime("-$daysToKeep days"));
        $cleaned = [];
        
        // Clean up processed invoices
        $invoiceQuery = "DELETE FROM " . TB_PREF . "amazon_invoices_staging 
                         WHERE status = 'processed' AND processed_at < '$cutoffDate'";
        if ($this->database->query($invoiceQuery)) {
            $cleaned['invoices'] = $this->database->getAffectedRows();
        }
        
        // Clean up email logs
        $emailQuery = "DELETE FROM " . TB_PREF . "amazon_email_logs 
                       WHERE status = 'processed' AND created_at < '$cutoffDate'";
        if ($this->database->query($emailQuery)) {
            $cleaned['email_logs'] = $this->database->getAffectedRows();
        }
        
        // Clean up PDF logs
        $pdfQuery = "DELETE FROM " . TB_PREF . "amazon_pdf_logs 
                     WHERE status = 'processed' AND created_at < '$cutoffDate'";
        if ($this->database->query($pdfQuery)) {
            $cleaned['pdf_logs'] = $this->database->getAffectedRows();
        }
        
        // Clean up duplicate detection logs
        $duplicateQuery = "DELETE FROM " . TB_PREF . "duplicate_detection_log 
                           WHERE created_at < '$cutoffDate'";
        if ($this->database->query($duplicateQuery)) {
            $cleaned['duplicate_logs'] = $this->database->getAffectedRows();
        }
        
        return $cleaned;
    }
    
    // =================
    // Helper Methods
    // =================
    
    /**
     * @param int $successful
     * @param int $total
     * @return float
     */
    private function calculateSuccessRate($successful, $total)
    {
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($successful / $total) * 100, 2);
    }
}
