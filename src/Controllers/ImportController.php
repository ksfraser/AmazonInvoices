<?php

declare(strict_types=1);

namespace AmazonInvoices\Controllers;

use AmazonInvoices\Services\UnifiedInvoiceImportService;
use AmazonInvoices\Services\DuplicateDetectionService;
use AmazonInvoices\Repositories\FrontAccountingDatabaseRepository;

/**
 * Main Import Controller
 * 
 * Provides a unified interface for all Amazon invoice import operations
 * Handles web requests and coordinates between different import methods
 */
class ImportController
{
    /**
     * @var UnifiedInvoiceImportService
     */
    private $importService;
    
    public function __construct()
    {
        // Initialize services
        $database = new FrontAccountingDatabaseRepository();
        $duplicateDetector = new DuplicateDetectionService($database);
        $this->importService = new UnifiedInvoiceImportService($database, $duplicateDetector);
    }
    
    /**
     * Dashboard showing import statistics and recent activity
     */
    public function dashboard(): void
    {
        $statistics = $this->importService->getProcessingStatistics(30);
        $recentActivity = $this->importService->getRecentActivity(20);
        $pendingInvoices = $this->importService->getPendingInvoices(10);
        
        $this->renderView('dashboard', [
            'title' => 'Amazon Invoice Import Dashboard',
            'statistics' => $statistics,
            'recent_activity' => $recentActivity,
            'pending_invoices' => $pendingInvoices
        ]);
    }
    
    /**
     * Process Gmail emails
     */
    public function processEmails(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $options = [
                'max_emails' => (int)($_POST['max_emails'] ?? 50),
                'days_back' => (int)($_POST['days_back'] ?? 7),
                'search_query' => $_POST['search_query'] ?? ''
            ];
            
            $results = $this->importService->processEmails($options);
            
            header('Content-Type: application/json');
            echo json_encode($results);
            return;
        }
        
        $this->renderView('email_import', [
            'title' => 'Gmail Email Import'
        ]);
    }
    
    /**
     * Upload and process PDF files
     */
    public function uploadPdfs(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['pdf_files'])) {
                $results = $this->importService->processUploadedFiles($_FILES['pdf_files']);
                
                header('Content-Type: application/json');
                echo json_encode($results);
                return;
            }
        }
        
        $this->renderView('pdf_upload', [
            'title' => 'PDF Upload & Processing'
        ]);
    }
    
    /**
     * Process PDFs from server directory
     */
    public function processDirectory(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $directoryPath = $_POST['directory_path'] ?? '';
            
            if (empty($directoryPath) || !is_dir($directoryPath)) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid directory path'
                ]);
                return;
            }
            
            $options = [
                'recursive' => isset($_POST['recursive']),
                'move_processed' => isset($_POST['move_processed']),
                'processed_dir' => $_POST['processed_dir'] ?? ''
            ];
            
            $results = $this->importService->processPdfDirectory($directoryPath, $options);
            
            header('Content-Type: application/json');
            echo json_encode($results);
            return;
        }
        
        $this->renderView('directory_import', [
            'title' => 'Directory PDF Import'
        ]);
    }
    
    /**
     * Review pending invoices
     */
    public function reviewInvoices(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        // Get pending invoices with pagination
        $pendingInvoices = $this->importService->getPendingInvoices($limit);
        
        $this->renderView('invoice_review', [
            'title' => 'Invoice Review',
            'invoices' => $pendingInvoices,
            'current_page' => $page
        ]);
    }
    
    /**
     * Get detailed invoice information (AJAX)
     */
    public function getInvoiceDetails(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            return;
        }
        
        $invoiceId = (int)$_GET['id'];
        $details = $this->importService->getInvoiceDetails($invoiceId);
        
        if (!$details) {
            http_response_code(404);
            echo json_encode(['error' => 'Invoice not found']);
            return;
        }
        
        header('Content-Type: application/json');
        echo json_encode($details);
    }
    
    /**
     * Mark invoice as processed (AJAX)
     */
    public function markInvoiceProcessed(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $invoiceId = (int)($input['invoice_id'] ?? 0);
        $faInvoiceNumber = $input['fa_invoice_number'] ?? null;
        
        if ($invoiceId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid invoice ID']);
            return;
        }
        
        $success = $this->importService->markInvoiceAsProcessed($invoiceId, $faInvoiceNumber);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
    }
    
    /**
     * Get processing statistics (AJAX)
     */
    public function getStatistics(): void
    {
        $daysBack = (int)($_GET['days'] ?? 30);
        $statistics = $this->importService->getProcessingStatistics($daysBack);
        
        header('Content-Type: application/json');
        echo json_encode($statistics);
    }
    
    /**
     * Clean up old records
     */
    public function cleanup(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $daysToKeep = (int)($_POST['days_to_keep'] ?? 90);
            $results = $this->importService->cleanupOldRecords($daysToKeep);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'cleaned' => $results,
                'message' => 'Cleanup completed successfully'
            ]);
            return;
        }
        
        $this->renderView('maintenance', [
            'title' => 'System Maintenance'
        ]);
    }
    
    /**
     * API endpoint for external integrations
     */
    public function api(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Only POST method allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'process_emails':
                $options = $input['options'] ?? [];
                $result = $this->importService->processEmails($options);
                echo json_encode($result);
                break;
                
            case 'get_pending':
                $limit = (int)($input['limit'] ?? 50);
                $result = $this->importService->getPendingInvoices($limit);
                echo json_encode(['invoices' => $result]);
                break;
                
            case 'get_statistics':
                $days = (int)($input['days'] ?? 30);
                $result = $this->importService->getProcessingStatistics($days);
                echo json_encode($result);
                break;
                
            case 'mark_processed':
                $invoiceId = (int)($input['invoice_id'] ?? 0);
                $faNumber = $input['fa_invoice_number'] ?? null;
                $result = $this->importService->markInvoiceAsProcessed($invoiceId, $faNumber);
                echo json_encode(['success' => $result]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Unknown action']);
        }
    }
    
    /**
     * Handle routing based on the request path
     */
    public function handleRequest(): void
    {
        $path = $_GET['action'] ?? 'dashboard';
        
        switch ($path) {
            case 'dashboard':
                $this->dashboard();
                break;
            case 'emails':
                $this->processEmails();
                break;
            case 'upload':
                $this->uploadPdfs();
                break;
            case 'directory':
                $this->processDirectory();
                break;
            case 'review':
                $this->reviewInvoices();
                break;
            case 'invoice-details':
                $this->getInvoiceDetails();
                break;
            case 'mark-processed':
                $this->markInvoiceProcessed();
                break;
            case 'statistics':
                $this->getStatistics();
                break;
            case 'cleanup':
                $this->cleanup();
                break;
            case 'api':
                $this->api();
                break;
            default:
                http_response_code(404);
                $this->renderView('404', ['title' => 'Page Not Found']);
        }
    }
    
    /**
     * Render a view template
     */
    private function renderView(string $view, array $data = []): void
    {
        // Extract data to variables
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the view file
        $viewFile = dirname(__DIR__, 2) . "/amazon_invoices/views/{$view}.php";
        
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            // Fallback to simple JSON output if view file doesn't exist
            header('Content-Type: application/json');
            echo json_encode([
                'view' => $view,
                'data' => $data,
                'error' => 'View template not found'
            ]);
        }
        
        // Get the content and clean the buffer
        $content = ob_get_clean();
        echo $content;
    }
}
