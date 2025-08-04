<?php

declare(strict_types=1);

/**
 * Amazon Invoice Import Integration Example
 * 
 * This file demonstrates how to use the complete modular system
 * with the Gmail and PDF processing libraries integrated through wrappers
 */

require_once __DIR__ . '/vendor/autoload.php';

use AmazonInvoices\Services\UnifiedInvoiceImportService;
use AmazonInvoices\Services\DuplicateDetectionService;
use AmazonInvoices\Repositories\FrontAccountingDatabaseRepository;
use AmazonInvoices\Controllers\ImportController;

// Initialize the system
try {
    // Create database connection
    $database = new FrontAccountingDatabaseRepository();
    
    // Create duplicate detection service
    $duplicateDetector = new DuplicateDetectionService($database);
    
    // Create the unified import service
    $importService = new UnifiedInvoiceImportService($database, $duplicateDetector);
    
    echo "Amazon Invoice Import System Initialized Successfully!\n\n";
    
    // Example 1: Process Gmail emails
    echo "=== Processing Gmail Emails ===\n";
    try {
        $emailResults = $importService->processEmails([
            'max_emails' => 10,
            'days_back' => 7
        ]);
        
        echo "Email processing results:\n";
        foreach ($emailResults as $result) {
            if ($result['success']) {
                echo "✓ Processed email: " . ($result['subject'] ?? 'Unknown') . "\n";
                if ($result['is_duplicate'] ?? false) {
                    echo "  → Duplicate detected (confidence: " . $result['duplicate_info']['confidence'] . ")\n";
                }
            } else {
                echo "✗ Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Email processing failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Example 2: Process a PDF file
    echo "=== Processing PDF File ===\n";
    $pdfPath = __DIR__ . '/sample_invoice.pdf';
    
    if (file_exists($pdfPath)) {
        try {
            $pdfResult = $importService->processPdfFile($pdfPath);
            
            if ($pdfResult['success']) {
                echo "✓ PDF processed successfully\n";
                echo "  Invoice Number: " . ($pdfResult['invoice_data']['invoice_number'] ?? 'N/A') . "\n";
                echo "  Total Amount: $" . ($pdfResult['invoice_data']['invoice_total'] ?? '0.00') . "\n";
                
                if ($pdfResult['is_duplicate'] ?? false) {
                    echo "  → Duplicate detected (confidence: " . $pdfResult['duplicate_info']['confidence'] . ")\n";
                }
            } else {
                echo "✗ PDF processing failed: " . ($pdfResult['error'] ?? 'Unknown error') . "\n";
            }
        } catch (Exception $e) {
            echo "PDF processing failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Sample PDF file not found at: $pdfPath\n";
    }
    
    echo "\n";
    
    // Example 3: Process directory of PDFs
    echo "=== Processing PDF Directory ===\n";
    $pdfDirectory = __DIR__ . '/sample_pdfs/';
    
    if (is_dir($pdfDirectory)) {
        try {
            $directoryResults = $importService->processPdfDirectory($pdfDirectory);
            
            echo "Directory processing results:\n";
            foreach ($directoryResults as $result) {
                if ($result['success']) {
                    echo "✓ Processed: " . basename($result['file']) . "\n";
                    if ($result['is_duplicate'] ?? false) {
                        echo "  → Duplicate detected\n";
                    }
                } else {
                    echo "✗ Failed: " . basename($result['file'] ?? 'unknown') . 
                         " - " . ($result['error'] ?? 'Unknown error') . "\n";
                }
            }
        } catch (Exception $e) {
            echo "Directory processing failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Sample PDF directory not found at: $pdfDirectory\n";
    }
    
    echo "\n";
    
    // Example 4: Get processing statistics
    echo "=== Processing Statistics (Last 30 Days) ===\n";
    try {
        $stats = $importService->getProcessingStatistics(30);
        
        echo "Email Import:\n";
        echo "  Total emails: " . $stats['email_import']['total_emails'] . "\n";
        echo "  Success rate: " . $stats['email_import']['success_rate'] . "%\n";
        
        echo "PDF Import:\n";
        echo "  Total PDFs: " . $stats['pdf_import']['total_pdfs'] . "\n";
        echo "  Success rate: " . $stats['pdf_import']['success_rate'] . "%\n";
        
        echo "Invoice Processing:\n";
        echo "  Total invoices: " . $stats['invoice_processing']['total_invoices'] . "\n";
        echo "  Pending: " . $stats['invoice_processing']['pending_invoices'] . "\n";
        echo "  Completed: " . $stats['invoice_processing']['completed_invoices'] . "\n";
        
        echo "Duplicate Detection:\n";
        echo "  Duplicates found: " . $stats['duplicate_detection']['total_duplicates_found'] . "\n";
        echo "  Average confidence: " . $stats['duplicate_detection']['average_confidence'] . "\n";
        
    } catch (Exception $e) {
        echo "Statistics retrieval failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Example 5: Get pending invoices
    echo "=== Pending Invoices (Need Review) ===\n";
    try {
        $pendingInvoices = $importService->getPendingInvoices(5);
        
        if (empty($pendingInvoices)) {
            echo "No pending invoices found.\n";
        } else {
            foreach ($pendingInvoices as $invoice) {
                echo "Invoice ID: " . $invoice['id'] . "\n";
                echo "  Order Number: " . ($invoice['order_number'] ?? 'N/A') . "\n";
                echo "  Total: $" . ($invoice['invoice_total'] ?? '0.00') . "\n";
                echo "  Items: " . ($invoice['item_count'] ?? 0) . "\n";
                echo "  Source: " . ($invoice['source_type'] ?? 'unknown') . "\n";
                echo "  Date: " . ($invoice['created_at'] ?? 'N/A') . "\n";
                echo "  ---\n";
            }
        }
    } catch (Exception $e) {
        echo "Pending invoices retrieval failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Example 6: Demonstrate web controller usage
    echo "=== Web Controller Usage ===\n";
    echo "The ImportController can be used for web requests:\n";
    echo "  /amazon_invoices/?action=dashboard     - Main dashboard\n";
    echo "  /amazon_invoices/?action=emails       - Email import interface\n";
    echo "  /amazon_invoices/?action=upload       - PDF upload interface\n";
    echo "  /amazon_invoices/?action=directory    - Directory import interface\n";
    echo "  /amazon_invoices/?action=review       - Invoice review interface\n";
    echo "  /amazon_invoices/?action=api          - API endpoint\n";
    
    echo "\nAPI Usage Example:\n";
    echo "POST /amazon_invoices/?action=api\n";
    echo "{\n";
    echo '  "action": "process_emails",'. "\n";
    echo '  "options": {"max_emails": 20, "days_back": 3}' . "\n";
    echo "}\n";
    
    echo "\n=== System Ready for Use ===\n";
    echo "The modular system is now configured and ready to process Amazon invoices\n";
    echo "from both Gmail emails and PDF files with comprehensive duplicate detection.\n";
    
} catch (Exception $e) {
    echo "System initialization failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Function to simulate file upload processing (for testing)
function simulateFileUpload(): array
{
    return [
        'pdf_files' => [
            'name' => ['invoice1.pdf', 'invoice2.pdf'],
            'type' => ['application/pdf', 'application/pdf'],
            'tmp_name' => ['/tmp/upload1', '/tmp/upload2'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [1024000, 2048000]
        ]
    ];
}

// Function to test the API endpoint programmatically
function testApiEndpoint(array $data): array
{
    // In a real scenario, this would make an HTTP request
    // For this example, we'll simulate the controller response
    
    $controller = new ImportController();
    
    // Simulate POST data
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    ob_start();
    
    // Simulate API call
    switch ($data['action']) {
        case 'get_statistics':
            $days = $data['days'] ?? 30;
            echo json_encode(['simulated' => true, 'days' => $days]);
            break;
        case 'get_pending':
            echo json_encode(['simulated' => true, 'invoices' => []]);
            break;
        default:
            echo json_encode(['error' => 'Unknown action in simulation']);
    }
    
    $response = ob_get_clean();
    return json_decode($response, true) ?: [];
}

echo "\n=== Testing API Simulation ===\n";
$apiTest = testApiEndpoint(['action' => 'get_statistics', 'days' => 7]);
echo "API test response: " . json_encode($apiTest) . "\n";
